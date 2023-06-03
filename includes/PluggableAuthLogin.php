<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace MediaWiki\Extension\PluggableAuth;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\PluggableAuth\Group\GroupProcessorRunner;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentityValue;
use Message;
use Psr\Log\LoggerInterface;
use UnlistedSpecialPage;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class PluggableAuthLogin extends UnlistedSpecialPage {

	const RETURNTOURL_SESSION_KEY = 'PluggableAuthLoginReturnToUrl';
	const EXTRALOGINFIELDS_SESSION_KEY = 'PluggableAuthLoginExtraLoginFields';
	const AUTHENTICATIONPLUGINNAME_SESSION_KEY = 'PluggableAuthLoginAuthenticationPluginIndex';
	const USERNAME_SESSION_KEY = 'PluggableAuthLoginUsername';
	const REALNAME_SESSION_KEY = 'PluggableAuthLoginRealname';
	const EMAIL_SESSION_KEY = 'PluggableAuthLoginEmail';
	const ERROR_SESSION_KEY = 'PluggableAuthLoginError';

	/**
	 * @var PluggableAuthFactory
	 */
	private $pluggableAuthFactory;

	/**
	 * @var AuthManager
	 */
	private $authManager;

	/**
	 * @var GroupProcessorRunner
	 */
	private $groupProcessorRunner;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var HookRunner
	 */
	private $hookRunner;

	/**
	 * @param PluggableAuthFactory $pluggableAuthFactory
	 * @param AuthManager $authManager
	 * @param GroupProcessorRunner $groupProcessorRunner
	 */
	public function __construct( PluggableAuthFactory $pluggableAuthFactory, AuthManager $authManager,
		GroupProcessorRunner $groupProcessorRunner ) {
		parent::__construct( 'PluggableAuthLogin' );
		$this->pluggableAuthFactory = $pluggableAuthFactory;
		$this->authManager = $authManager;
		$this->groupProcessorRunner = $groupProcessorRunner;
		$this->logger = LoggerFactory::getInstance( 'PluggableAuth' );
	}

	/**
	 * Will be called automatically by `MediaWiki\SpecialPage\SpecialPageFactory::getPage`
	 * @inheritDoc
	 */
	public function setHookContainer( HookContainer $hookContainer ) {
		parent::setHookContainer( $hookContainer );
		$this->hookRunner = new HookRunner( $hookContainer );
	}

	/**
	 * @param string|null $subPage parameters (ignored)
	 */
	public function execute( $subPage ) {
		$this->logger->debug( 'In execute()' );
		$user = $this->getUser();
		$pluggableauth = $this->pluggableAuthFactory->getInstance();
		$error = null;
		if ( $pluggableauth ) {
			if ( $pluggableauth->authenticate( $id, $username, $realname, $email, $error ) ) {
				if ( !$id ) {
					$user->loadDefaults( $username );
					if ( $realname !== null ) {
						$user->setRealName( $realname );
					}
					$user->mName = $username;
					$user->mEmail = $email;
					$now = ConvertibleTimestamp::now( TS_UNIX );
					$user->mEmailAuthenticated = $now;
					$user->mTouched = $now;
					$this->logger->debug( 'Authenticated new user: ' . $username );
					// Group sync is done in `LocalUserCreated` hook
				} else {
					$user->mId = $id;
					$user->loadFromId();
					$this->logger->debug( 'Authenticated existing user: ' . $user->mName );
					$userIdentity = new UserIdentityValue( $user->getId(), $user->getName() );
					$this->groupProcessorRunner->run( $userIdentity, $pluggableauth );
				}
				$authorized = true;
				$this->hookRunner->onPluggableAuthUserAuthorization( $user, $authorized );
				if ( $authorized ) {
					$this->authManager->setAuthenticationSessionData( self::USERNAME_SESSION_KEY, $username );
					$this->authManager->setAuthenticationSessionData( self::REALNAME_SESSION_KEY, $realname );
					$this->authManager->setAuthenticationSessionData( self::EMAIL_SESSION_KEY, $email );
					$this->logger->debug( 'User is authorized.' );
				} else {
					$this->logger->debug( 'Authorization failure.' );
					$error = ( new Message( 'pluggableauth-not-authorized', [ $username ] ) )->parse();
				}
			} else {
				$this->logger->debug( 'Authentication failure.' );
				if ( $error === null ) {
					$error = ( new Message( 'pluggableauth-authentication-failure' ) )->text();
				} else {
					if ( !is_string( $error ) ) {
						$error = strval( $error );
					}
					$this->logger->debug( 'ERROR: ' . $error );
				}
			}
		} else {
			$error = ( new Message( 'pluggableauth-authentication-plugin-failure' ) )->text();
		}
		if ( $error !== null ) {
			$this->authManager->setAuthenticationSessionData( self::ERROR_SESSION_KEY, $error );
		}
		$returnToUrl = $this->authManager->getRequest()->getSessionData( self::RETURNTOURL_SESSION_KEY );
		if ( $returnToUrl === null || strlen( $returnToUrl ) === 0 ) {
			// This should never happen unless there is an issue in the authentication plugin, most
			// likely resulting in session corruption. Since it is unclear if it is safe to continue,
			// an error message is shown to the user and the authentication flow is terminated.
			$this->logger->debug( 'ERROR: return to URL is null or empty' );
			$this->getOutput()->wrapWikiMsg( "<div class='error'>\n$1\n</div>", 'pluggableauth-fatal-error' );
		} else {
			$this->getOutput()->redirect( $returnToUrl );
		}
	}
}
