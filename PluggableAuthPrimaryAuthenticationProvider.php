<?php

/*
 * Copyright (c) 2016 The MITRE Corporation
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

use \MediaWiki\Auth\AuthenticationRequest;
use \MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use \MediaWiki\Auth\AuthManager;
use \MediaWiki\Auth\AuthenticationResponse;

class PluggableAuthPrimaryAuthenticationProvider extends
	AbstractPrimaryAuthenticationProvider {

	public function beginPrimaryAuthentication( array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			PluggableAuthBeginAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newAbstain();
		}
		$url = Title::newFromText( 'Special:PluggableAuthLogin' )->getFullURL();
		$this->manager->setAuthenticationSessionData(
			PluggableAuth::RETURNURL_SESSION_KEY, $request->returnToUrl );

		return AuthenticationResponse::newRedirect( [
			new PluggableAuthContinueAuthenticationRequest()
		], $url );
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			PluggableAuthContinueAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'PluggableAuthlogin-error-no-authentication-workflow' )
			);
		}
		if ( $request->error ) {
			return AuthenticationResponse::newFail( $request->error );
		}
		$username = $this->manager->getAuthenticationSessionData(
			PluggableAuth::USERNAME_SESSION_KEY );
		return AuthenticationResponse::newPass( $username );
	}

	public function postAuthentication( $user, AuthenticationResponse $response ) {
		if ( $response->status == AuthenticationResponse::PASS ) {
			$realname = $this->manager->getAuthenticationSessionData(
				PluggableAuth::REALNAME_SESSION_KEY );
			$Email = $this->manager->getAuthenticationSessionData(
				PluggableAuth::EMAIL_SESSION_KEY );
			if ( $user->mRealName != $realname || $user->mEmail != $email ) {
				$rights = $user->getRights();
				if ( in_array( 'editmyprivateinfo', $rights ) ) {
					wfDebug( 'User has editmyprivateinfo right.' . PHP_EOL );
					wfDebug( 'Did not save updated real name and email address.' . PHP_EOL );
				} else {
					wfDebug( 'User does not have editmyprivateinfo right.' . PHP_EOL );
					$user->mRealName = $realname;
					$user->mEmail = $email;
					$user->saveSettings();
					wfDebug( 'Saved updated real name and email address.' . PHP_EOL );
				}
			} else {
				wfDebug( 'Real name and email address did not change.' . PHP_EOL );
			}
			$user->setCookies();
			$pluggableauth = PluggableAuth::getInstance();
			if ( $pluggableauth ) {
				$pluggableauth->saveExtraAttributes( $user->mId );
			}
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true ) {
		return StatusValue::newGood( 'dummy' );
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		return null;
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [ new PluggableAuthBeginAuthenticationRequest()
				];
			default:
				return [];
		}
	}
}
