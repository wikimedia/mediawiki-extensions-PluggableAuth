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

use Config;
use IDBAccessObject;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserFactory;
use Message;
use MWException;
use RawMessage;
use Sanitizer;
use SpecialPage;
use StatusValue;
use User;

class PrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

	public const CONSTRUCTOR_OPTIONS = [
		'PluggableAuth_EnableLocalProperties'
	];

	/**
	 * @var bool
	 */
	private $enableLocalProperties;

	/**
	 * @var UserFactory
	 */
	private $userFactory;

	/**
	 * @var PluggableAuthFactory
	 */
	private $pluggableAuthFactory;

	/**
	 * @param Config $mainConfig
	 * @param UserFactory $userFactory
	 * @param PluggableAuthFactory $pluggableAuthFactory
	 */
	public function __construct(
		Config $mainConfig,
		UserFactory $userFactory,
		PluggableAuthFactory $pluggableAuthFactory
	) {
		$options = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $mainConfig );
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->enableLocalProperties = $options->get( 'PluggableAuth_EnableLocalProperties' );
		$this->userFactory = $userFactory;
		$this->pluggableAuthFactory = $pluggableAuthFactory;
	}

	/**
	 * @param string $action
	 * @param array $options
	 * @return array|BeginAuthenticationRequest[]
	 */
	public function getAuthenticationRequests( $action, array $options ): array {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				$requests = [];
				foreach ( $this->pluggableAuthFactory->getConfig() as $name => $entry ) {
					if ( method_exists( $entry['spec']['class'], 'getExtraLoginFields' ) ) {
						$extraLoginFields = $entry['spec']['class']::getExtraLoginFields();
					} else {
						$extraLoginFields = [];
					}
					$requests[$name] = new BeginAuthenticationRequest( $name, $entry['label'], $extraLoginFields );
				}
				return $requests;
			default:
				return [];
		}
	}

	/**
	 * Start an authentication flow
	 * @param array $reqs
	 * @return AuthenticationResponse
	 * @throws MWException
	 */
	public function beginPrimaryAuthentication( array $reqs ): AuthenticationResponse {
		$matches = array_filter( $reqs, static function ( $req ) {
			return $req instanceof BeginAuthenticationRequest;
		} );
		// Reset array indexes
		$matches = array_values( $matches );

		$request = $matches[0] ?? null;
		if ( !$request ) {
			return AuthenticationResponse::newAbstain();
		}

		$url = SpecialPage::getTitleFor( 'PluggableAuthLogin' )->getFullURL();
		$this->manager->getRequest()->setSessionData(
			PluggableAuthLogin::RETURNTOURL_SESSION_KEY, $request->returnToUrl
		);

		$this->manager->getRequest()->setSessionData(
			PluggableAuthLogin::AUTHENTICATIONPLUGINNAME_SESSION_KEY,
			$request->getAuthenticationPluginName()
		);

		$extraLoginFields = [];
		foreach ( $request->getExtraLoginFields() as $key => $value ) {
			if ( isset( $request->$key ) ) {
				$extraLoginFields[$key] = $request->$key;
			}
		}
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY, $extraLoginFields );

		return AuthenticationResponse::newRedirect(
			[ new ContinueAuthenticationRequest() ],
			$url
		);
	}

	/**
	 * Continue an authentication flow
	 * @param array $reqs
	 * @return AuthenticationResponse
	 */
	public function continuePrimaryAuthentication( array $reqs ): AuthenticationResponse {
		$request = AuthenticationRequest::getRequestByClass( $reqs, ContinueAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				new Message( 'pluggableauth-authentication-workflow-failure' ) );
		}
		$error = $this->manager->getAuthenticationSessionData( PluggableAuthLogin::ERROR_SESSION_KEY );
		if ( $error !== null ) {
			$this->manager->removeAuthenticationSessionData( PluggableAuthLogin::ERROR_SESSION_KEY );
			return AuthenticationResponse::newFail( new RawMessage( $error ) );
		}
		$username = $request->username;
		$user = $this->userFactory->newFromName( $username );
		if ( $user && $user->getId() !== 0 ) {
			$this->updateUserRealnameAndEmail( $user );
		}
		return AuthenticationResponse::newPass( $username );
	}

	/**
	 * Determine whether a property can change
	 * @param string $property
	 * @return bool
	 */
	public function providerAllowsPropertyChange( $property ): bool {
		return $this->enableLocalProperties;
	}

	/**
	 * @param User $user
	 * @param bool $force
	 * @return void
	 */
	private function updateUserRealNameAndEmail( User $user, bool $force = false ): void {
		$realname = $this->manager->getAuthenticationSessionData( PluggableAuthLogin::REALNAME_SESSION_KEY ) ?? '';
		$this->manager->removeAuthenticationSessionData( PluggableAuthLogin::REALNAME_SESSION_KEY );
		$email = $this->manager->getAuthenticationSessionData( PluggableAuthLogin::EMAIL_SESSION_KEY ) ?? '';
		$this->manager->removeAuthenticationSessionData( PluggableAuthLogin::EMAIL_SESSION_KEY );
		if ( $user->getRealName() != $realname || $user->mEmail != $email ) {
			if ( $this->enableLocalProperties && !$force ) {
				$this->logger->debug( 'PluggableAuth: Local properties enabled.' );
				$this->logger->debug( 'PluggableAuth: Did not save updated real name and email address.' );
			} else {
				$this->logger->debug( 'PluggableAuth: Local properties disabled or has just been created.' );
				$user->setRealName( $realname );
				if ( $email && Sanitizer::validateEmail( $email ) ) {
					$user->mEmail = $email;
					$user->confirmEmail();
				}
				$user->saveSettings();
				$this->logger->debug( 'PluggableAuth: Saved updated real name and email address.' );
			}
		} else {
			$this->logger->debug( 'PluggableAuth: Real name and email address did not change.' );
		}
	}

	/**
	 * @param User $user
	 * @param string $source
	 */
	public function autoCreatedAccount( $user, $source ): void {
		$this->updateUserRealNameAndEmail( $user, true );
		$pluggableauth = $this->pluggableAuthFactory->getInstance();
		if ( $pluggableauth ) {
			$pluggableauth->saveExtraAttributes( $user->mId );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function testUserCanAuthenticate( $username ) {
		// Additionally check if specified username is not reserved for system users
		$isUsable = $this->userNameUtils->isUsable( $username );

		return $isUsable && $this->testUserExists( $username );
	}

	/**
	 * Test whether the named user exists
	 * @param string $username MediaWiki username
	 * @param int $flags Bitfield of IDBAccessObject:READ_* constants
	 * TODO: change default to Authority::READ_NORMAL once support for MW 1.35 is dropped
	 * @return bool
	 */
	public function testUserExists(
		$username,
		$flags = IDBAccessObject::READ_NORMAL
	): bool {
		$user = $this->userFactory->newFromName( $username );
		return $user && $user->isRegistered();
	}

	/**
	 * Validate a change of authentication data (e.g. passwords)
	 * @param AuthenticationRequest $req
	 * @param bool $checkData
	 * @return StatusValue
	 */
	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req,
		$checkData = true
	): StatusValue {
		return StatusValue::newGood( 'ignored' );
	}

	/**
	 * Fetch the account-creation type
	 * @return string
	 */
	public function accountCreationType(): string {
		return self::TYPE_LINK;
	}

	/**
	 * Start an account creation flow
	 * @param User $user User being created (not added to the database yet).
	 * @param User $creator User doing the creation.
	 * @param AuthenticationRequest[] $reqs
	 * @return AuthenticationResponse
	 */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ): AuthenticationResponse {
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * Change or remove authentication data (e.g. passwords)
	 * @param AuthenticationRequest $req
	 */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ): void {
	}
}
