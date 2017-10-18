<?php

use \MediaWiki\Auth\AuthenticationRequest;
use \MediaWiki\Auth\ButtonAuthenticationRequest;
use \MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use \MediaWiki\Auth\AuthManager;
use \MediaWiki\Auth\AuthenticationResponse;

class PluggableAuthPrimaryAuthenticationProvider extends
	AbstractPrimaryAuthenticationProvider {

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
		$url = Title::newFromText( 'Special:PluggableAuthLogin' )->getFullURL();
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOURL_SESSION_KEY, $request->returnToUrl );
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY, $extraLoginFields );
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
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOQUERY_SESSION_KEY, $returntoquery );

		return AuthenticationResponse::newRedirect( [
			new PluggableAuthContinueAuthenticationRequest()
		], $url );
	}

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
				wfDebug( 'Local properties enabled.' );
				wfDebug( 'Did not save updated real name and email address.' );
			} else {
				wfDebug( 'Local properties disabled or has just been created.' );
				$user->mRealName = $realname;
				if ( $email && Sanitizer::validateEmail( $email ) ) {
					$user->mEmail = $email;
					$user->confirmEmail();
				}
				$user->saveSettings();
				wfDebug( 'Saved updated real name and email address.' );
			}
		} else {
			wfDebug( 'Real name and email address did not change.' );
		}
	}

	public function autoCreatedAccount( $user, $source ) {
		$this->updateUserRealNameAndEmail( $user, true );
		$pluggableauth = PluggableAuth::singleton();
		if ( $pluggableauth ) {
			$pluggableauth->saveExtraAttributes( $user->mId );
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true ) {
		return StatusValue::newGood( 'ignored' );
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
	}

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
