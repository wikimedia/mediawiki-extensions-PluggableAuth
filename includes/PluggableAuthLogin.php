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
use MediaWiki\SpecialPage\SpecialPage;
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
			$id = $username = $realname = $email = null;
			if ( $pluggableauth->authenticate( $id, $username, $realname, $email, $error ) ) {
				if ( !$id ) {
					if ( $username === null ) {
						$this->logger->debug( 'Missing username for new user' );
						$error = ( new Message( 'pluggableauth-no-username' ) )->text();
					} else {
						$user->loadDefaults( $username );
						if ( $realname === null ) {
							$realname = $user->mRealName;
						} else {
							$user->mRealName = $realname;
						}
						$now = ConvertibleTimestamp::now( TS_UNIX );
						if ( $email === null ) {
							$email = $user->mEmail;
						} else {
							$user->mEmail = $email;
							$user->mEmailAuthenticated = $now;
						}
						$user->mTouched = $now;
						$this->logger->debug( 'Authenticated new user: ' . $username );
						// Group sync is done in `LocalUserCreated` hook
					}
				} else {
					$user->mId = $id;
					$user->loadFromId();
					$this->logger->debug( 'Authenticated existing user: ' . $user->mName );
					$userIdentity = new UserIdentityValue( $user->getId(), $user->getName() );
					$this->groupProcessorRunner->run( $userIdentity, $pluggableauth );
					// ignore username returned from plugin for existing users
					$username = $user->mName;
					// if real name is not set by plugin, get it from existing user
					if ( $realname === null ) {
						$realname = $user->mRealName;
					}
					// if email is not set by plugin, get it from existing user
					if ( $email === null ) {
						$email = $user->mEmail;
					}
				}
				if ( $error === null ) {
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
			// This can happen if we've lost session data or the user has a session cookie whose corresponding session
			// has been culled. In this case, we'll send them back to the login page.
			$this->logger->debug( 'ERROR: return to URL is null or empty' );
			$returnToUrl = SpecialPage::getTitleFor( 'Userlogin' )->getFullURL( [
				'error' => 'pluggableauth-fatal-error'
			] );
		}
		$this->getOutput()->redirect( $returnToUrl );
	}
}
