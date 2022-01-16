<?php

namespace MediaWiki\Extension\PluggableAuth;

use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MWException;
use RawMessage;
use Sanitizer;
use SpecialPage;
use StatusValue;
use User;

class PrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

	/**
	 * Start an authentication flow
	 * @param array $reqs
	 * @return AuthenticationResponse
	 * @throws MWException
	 */
	public function beginPrimaryAuthentication( array $reqs ): AuthenticationResponse {
		$request = ButtonAuthenticationRequest::getRequestByName( $reqs,
			'pluggableauthlogin' );
		if ( !$request ) {
			return AuthenticationResponse::newAbstain();
		}
		$extraLoginFields = [];
		foreach ( $GLOBALS['wgPluggableAuth_ExtraLoginFields'] as $key => $value ) {
			if ( isset( $request->$key ) ) {
				$extraLoginFields[$key] = $request->$key;
			}
		}
		$url = SpecialPage::getTitleFor( 'PluggableAuthLogin' )->getFullURL();
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOURL_SESSION_KEY, $request->returnToUrl );
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY, $extraLoginFields );
		// phpcs:disable MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$returnto = $_GET['returnto'] ?? '';
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOPAGE_SESSION_KEY, $returnto );
		$returntoquery = $_GET['returntoquery'] ?? '';
		// phpcs:enable
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOQUERY_SESSION_KEY, $returntoquery );

		return AuthenticationResponse::newRedirect( [
			new ContinueAuthenticationRequest()
		], $url );
	}

	/**
	 * Continue an authentication flow
	 * @param array $reqs
	 * @return AuthenticationResponse
	 */
	public function continuePrimaryAuthentication( array $reqs ): AuthenticationResponse {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			ContinueAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'pluggableauth-authentication-workflow-failure' ) );
		}
		$error = $this->manager->getAuthenticationSessionData(
			PluggableAuthLogin::ERROR_SESSION_KEY );
		if ( $error !== null ) {
			$this->manager->removeAuthenticationSessionData(
				PluggableAuthLogin::ERROR_SESSION_KEY );
			return AuthenticationResponse::newFail( new RawMessage( $error ) );
		}
		$username = $request->username;
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $username );
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
		return $GLOBALS['wgPluggableAuth_EnableLocalProperties'];
	}

	private function updateUserRealNameAndEmail( User $user, bool $force = false ): void {
		$realname = $this->manager->getAuthenticationSessionData(
			PluggableAuthLogin::REALNAME_SESSION_KEY );
		$this->manager->removeAuthenticationSessionData(
			PluggableAuthLogin::REALNAME_SESSION_KEY );
		$email = $this->manager->getAuthenticationSessionData(
			PluggableAuthLogin::EMAIL_SESSION_KEY );
		$this->manager->removeAuthenticationSessionData(
			PluggableAuthLogin::EMAIL_SESSION_KEY );
		if ( $user->mRealName != $realname || $user->mEmail != $email ) {
			if ( $GLOBALS['wgPluggableAuth_EnableLocalProperties'] && !$force ) {
				wfDebugLog( 'PluggableAuth', 'Local properties enabled.' );
				wfDebugLog( 'PluggableAuth', 'Did not save updated real name and email address.' );
			} else {
				wfDebugLog( 'PluggableAuth', 'Local properties disabled or has just been created.' );
				$user->mRealName = $realname;
				if ( $email && Sanitizer::validateEmail( $email ) ) {
					$user->mEmail = $email;
					$user->confirmEmail();
				}
				$user->saveSettings();
				wfDebugLog( 'PluggableAuth', 'Saved updated real name and email address.' );
			}
		} else {
			wfDebugLog( 'PluggableAuth', 'Real name and email address did not change.' );
		}
	}

	/**
	 * @param User $user
	 * @param string $source
	 */
	public function autoCreatedAccount( $user, $source ): void {
		$this->updateUserRealNameAndEmail( $user, true );
		$pluggableauth = PluggableAuth::singleton();
		if ( $pluggableauth ) {
			$pluggableauth->saveExtraAttributes( $user->mId );
		}
	}

	/**
	 * Test whether the named user exists
	 * @param string $username MediaWiki username
	 * @param int $flags Bitfield of User:READ_* constants
	 * @return bool
	 */
	public function testUserExists(
		$username,
		$flags = Authority::READ_NORMAL
	): bool {
		return false;
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

	/**
	 * @param string $action
	 * @param array $options
	 * @return array|BeginAuthenticationRequest[]
	 */
	public function getAuthenticationRequests( $action, array $options ): array {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [
					new BeginAuthenticationRequest()
				];
			default:
				return [];
		}
	}
}
