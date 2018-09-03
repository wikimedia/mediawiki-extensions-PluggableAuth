<?php

use \MediaWiki\Auth\AuthenticationRequest;
use \MediaWiki\Auth\ButtonAuthenticationRequest;
use \MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use \MediaWiki\Auth\AuthManager;
use \MediaWiki\Auth\AuthenticationResponse;

class PluggableAuthPrimaryAuthenticationProvider extends
	AbstractPrimaryAuthenticationProvider {

	/**
	 * Start an authentication flow
	 * @inheritDoc
	 */
	public function beginPrimaryAuthentication( array $reqs ) {
		$request = ButtonAuthenticationRequest::getRequestByName( $reqs,
			'pluggableauthlogin' );
		if ( !$request ) {
			return AuthenticationResponse::newAbstain();
		}
		$extraLoginFields = [];
		foreach ( $GLOBALS['wgPluggableAuth_ExtraLoginFields'] as $key => $value ) {
			if ( isset( $request, $key ) ) {
				$extraLoginFields[$key] = $request->$key;
			}
		}
		$url = SpecialPage::getTitleFor( 'PluggableAuthLogin' )->getFullURL();
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOURL_SESSION_KEY, $request->returnToUrl );
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY, $extraLoginFields );
		// @codingStandardsIgnoreStart
		if ( isset( $_GET['returnto'] ) ) {
			$returnto = $_GET['returnto'];
		} else {
			$returnto = '';
		}
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOPAGE_SESSION_KEY, $returnto );
		if ( isset( $_GET['returntoquery'] ) ) {
			$returntoquery = $_GET['returntoquery'];
		} else {
			$returntoquery = '';
		}
		// @codingStandardsIgnoreEnd
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOQUERY_SESSION_KEY, $returntoquery );

		return AuthenticationResponse::newRedirect( [
			new PluggableAuthContinueAuthenticationRequest()
		], $url );
	}

	/**
	 * Continue an authentication flow
	 * @inheritDoc
	 */
	public function continuePrimaryAuthentication( array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			PluggableAuthContinueAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'pluggableauth-authentication-workflow-failure' ) );
		}
		$error = $this->manager->getAuthenticationSessionData(
			PluggableAuthLogin::ERROR_SESSION_KEY );
		if ( !is_null( $error ) ) {
			$this->manager->removeAuthenticationSessionData(
				PluggableAuthLogin::ERROR_SESSION_KEY );
			return AuthenticationResponse::newFail( new RawMessage( $error ) );
		}
		$username = $request->username;
		$user = User::newFromName( $username );
		if ( $user && $user->getId() !== 0 ) {
			$this->updateUserRealnameAndEmail( $user );
		}
		return AuthenticationResponse::newPass( $username );
	}

	/**
	 * Determine whether a property can change
	 * @inheritDoc
	 */
	public function providerAllowsPropertyChange( $property ) {
		return $GLOBALS['wgPluggableAuth_EnableLocalProperties'];
	}

	private function updateUserRealNameAndEmail( $user, $force = false ) {
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
	 * @inheritDoc
	 */
	public function autoCreatedAccount( $user, $source ) {
		$this->updateUserRealNameAndEmail( $user, true );
		$pluggableauth = PluggableAuth::singleton();
		if ( $pluggableauth ) {
			$pluggableauth->saveExtraAttributes( $user->mId );
		}
	}

	/**
	 * Test whether the named user exists
	 * @inheritDoc
	 */
	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	/**
	 * Validate a change of authentication data (e.g. passwords)
	 * @inheritDoc
	 */
	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true ) {
		return StatusValue::newGood( 'ignored' );
	}

	/**
	 * Fetch the account-creation type
	 * @inheritDoc
	 */
	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	/**
	 * Start an account creation flow
	 * @inheritDoc
	 */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * Change or remove authentication data (e.g. passwords)
	 * @inheritDoc
	 */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
	}

	/**
	 * @inheritDoc
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [
					new PluggableAuthBeginAuthenticationRequest()
				];
			default:
				return [];
		}
	}
}
