<?php

/*
 * Copyright (c) 2014-2016 The MITRE Corporation
 *
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

use \MediaWiki\Auth\AuthManager;

class PluggableAuthLogin extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'PluggableAuthLogin' );
	}

	public function execute( $param ) {
		$authManager = AuthManager::singleton();
		$user = $this->getUser();
		$pluggableauth = PluggableAuth::getInstance();
		$error = null;
		if ( $pluggableauth ) {
			if ( $pluggableauth->authenticate( $id, $username, $realname, $email ) ) {
				if ( is_null( $id ) ) {
					$user->loadDefaults( $username );
					$user->mName = $username;
					$user->mRealName = $realname;
					$user->mEmail = $email;
					$user->mEmailAuthenticated = wfTimestamp();
					$user->mTouched = wfTimestamp();
					wfDebug( 'Authenticated new user: ' . $username . PHP_EOL );
				} else {
					$user->mId = $id;
					$user->loadFromId();
					$new_user = false;
					wfDebug( 'Authenticated existing user: ' . $user->mName . PHP_EOL );
				}
				$authorized = true;
				Hooks::run( 'PluggableAuthUserAuthorization', array( $user,
					&$authorized ) );
				if ( $authorized ) {
					$authManager->setAuthenticationSessionData(
						PluggableAuth::USERNAME_SESSION_KEY, $username );
					$authManager->setAuthenticationSessionData(
						PluggableAuth::REALNAME_SESSION_KEY, $realname );
					$authManager->setAuthenticationSessionData(
						PluggableAuth::EMAIL_SESSION_KEY, $email );
					wfDebug( 'User is authorized.' . PHP_EOL );
				} else {
					$authManager->removeAuthenticationSessionData(
						PluggableAuth::USERNAME_SESSION_KEY );
					$authManager->removeAuthenticationSessionData(
						PluggableAuth::REALNAME_SESSION_KEY );
					$authManager->removeAuthenticationSessionData(
						PluggableAuth::EMAIL_SESSION_KEY );
					wfDebug( 'Authorization failure.' . PHP_EOL );
					$error = 'Not Authorized';
				}
			} else {
				wfDebug( 'Authentication failure.' . PHP_EOL );
				$error = 'Authentication Failure';
			}
		}
		$returnToUrl = $authManager->getAuthenticationSessionData(
			PluggableAuth::RETURNURL_SESSION_KEY );
		if ( !is_null( $error ) ) {
			$returnToUrl = $returnToUrl . "&error=" . $error;
		}
		$this->getOutput()->redirect( $returnToUrl );
	}
}
