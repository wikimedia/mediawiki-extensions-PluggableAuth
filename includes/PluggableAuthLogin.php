<?php

use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

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

	/**
	 * @param string|null $param parameters (ignored)
	 */
	public function execute( $param ) {
		wfDebugLog( 'PluggableAuth', 'In execute()' );
		if ( method_exists( MediaWikiServices::class, 'getAuthManager' ) ) {
			// MediaWiki 1.35+
			$authManager = MediaWikiServices::getInstance()->getAuthManager();
		} else {
			$authManager = AuthManager::singleton();
		}
		$user = $this->getUser();
		$pluggableauth = PluggableAuth::singleton();
		$error = null;
		if ( $pluggableauth ) {
			if ( $pluggableauth->authenticate( $id, $username, $realname, $email,
				$error ) ) {
				if ( $id === null ) {
					$user->loadDefaults( $username );
					$user->mName = $username;
					$user->mRealName = $realname;
					$user->mEmail = $email;
					$user->mEmailAuthenticated = wfTimestamp();
					$user->mTouched = wfTimestamp();
					wfDebugLog( 'PluggableAuth', 'Authenticated new user: ' . $username );
				} else {
					$user->mId = $id;
					$user->loadFromId();
					wfDebugLog( 'PluggableAuth', 'Authenticated existing user: ' . $user->mName );
					Hooks::run( 'PluggableAuthPopulateGroups', [ $user ] );
				}
				$authorized = true;
				Hooks::run( 'PluggableAuthUserAuthorization', [ $user, &$authorized ] );
				if ( $authorized ) {
					$authManager->setAuthenticationSessionData(
						self::USERNAME_SESSION_KEY, $username );
					$authManager->setAuthenticationSessionData(
						self::REALNAME_SESSION_KEY, $realname );
					$authManager->setAuthenticationSessionData(
						self::EMAIL_SESSION_KEY, $email );
					wfDebugLog( 'PluggableAuth', 'User is authorized.' );
				} else {
					wfDebugLog( 'PluggableAuth', 'Authorization failure.' );
					$error = wfMessage( 'pluggableauth-not-authorized', $username )->text();
				}
			} else {
				wfDebugLog( 'PluggableAuth', 'Authentication failure.' );
				if ( $error === null ) {
					$error = wfMessage( 'pluggableauth-authentication-failure' )->text();
				} else {
					if ( !is_string( $error ) ) {
						$error = strval( $error );
					}
					wfDebugLog( 'PluggableAuth', 'ERROR: ' . $error );
				}
			}
		}
		if ( $error !== null ) {
			$authManager->setAuthenticationSessionData( self::ERROR_SESSION_KEY,
				$error );
		}
		$returnToUrl = $authManager->getAuthenticationSessionData(
			self::RETURNTOURL_SESSION_KEY );
		if ( $returnToUrl === null || strlen( $returnToUrl ) === 0 ) {
			wfDebugLog( 'PluggableAuth', 'ERROR: return to URL is null or empty' );
			$this->getOutput()->wrapWikiMsg( "<div class='error'>\n$1\n</div>",
				'pluggableauth-fatal-error' );
		} else {
			$this->getOutput()->redirect( $returnToUrl );
		}
	}
}
