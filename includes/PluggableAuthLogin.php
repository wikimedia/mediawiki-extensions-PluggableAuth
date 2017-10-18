<?php

use \MediaWiki\Auth\AuthManager;

class PluggableAuthLogin extends UnlistedSpecialPage {

	const RETURNTOURL_SESSION_KEY = 'PluggableAuthLoginReturnToUrl';
	const RETURNTOPAGE_SESSION_KEY = 'PluggableAuthLoginReturnToPage';
	const RETURNTOQUERY_SESSION_KEY = 'PluggableAuthLoginReturnToQuery';
	const EXTRALOGINFIELDS_SESSION_KEY = 'PluggableAuthLoginExtraLoginFields';
	const USERNAME_SESSION_KEY = 'PluggableAuthLoginUsername';
	const REALNAME_SESSION_KEY = 'PluggableAuthLoginRealname';
	const EMAIL_SESSION_KEY = 'PluggableAuthLoginEmail';
	const ERROR_SESSION_KEY = 'PluggableAuthLoginError';

	public function __construct() {
		parent::__construct( 'PluggableAuthLogin' );
	}

	public function execute( $param ) {
		$authManager = AuthManager::singleton();
		$user = $this->getUser();
		$pluggableauth = PluggableAuth::singleton();
		$error = null;
		if ( $pluggableauth ) {
			if ( $pluggableauth->authenticate( $id, $username, $realname, $email,
					$error ) ) {
				if ( is_null( $id ) ) {
					$user->loadDefaults( $username );
					$user->mName = $username;
					$user->mRealName = $realname;
					$user->mEmail = $email;
					$user->mEmailAuthenticated = wfTimestamp();
					$user->mTouched = wfTimestamp();
					wfDebug( 'Authenticated new user: ' . $username );
				} else {
					$user->mId = $id;
					$user->loadFromId();
					wfDebug( 'Authenticated existing user: ' . $user->mName );
				}
				Hooks::run( 'PluggableAuthPopulateGroups', [ $user ] );
				$authorized = true;
				Hooks::run( 'PluggableAuthUserAuthorization', [ $user, &$authorized ] );
				if ( $authorized ) {
					$authManager->setAuthenticationSessionData(
						self::USERNAME_SESSION_KEY, $username );
					$authManager->setAuthenticationSessionData(
						self::REALNAME_SESSION_KEY, $realname );
					$authManager->setAuthenticationSessionData(
						self::EMAIL_SESSION_KEY, $email );
					wfDebug( 'User is authorized.' );
				} else {
					wfDebug( 'Authorization failure.' );
					$error = wfMessage( 'pluggableauth-not-authorized', $username )->text();
				}
			} else {
				wfDebug( 'Authentication failure.' );
				if ( is_null( $error ) ) {
					$error = wfMessage( 'pluggableauth-authentication-failure' )->text();
				} else {
					if ( !is_string( $error ) ) {
						$error = strval( $error );
					}
					wfDebug( 'ERROR: ' . $error );
				}
			}
		}
		if ( !is_null( $error ) ) {
			$authManager->setAuthenticationSessionData( self::ERROR_SESSION_KEY,
				$error );
		}
		$returnToUrl = $authManager->getAuthenticationSessionData(
			self::RETURNTOURL_SESSION_KEY );
		if ( is_null( $returnToUrl ) || count( $returnToUrl ) === 0 ) {
			wfDebug( 'ERROR: return to URL is null or empty' );
		} else {
			$this->getOutput()->redirect( $returnToUrl );
		}
	}
}
