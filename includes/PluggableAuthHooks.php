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

use MediaWiki;
use MediaWiki\MediaWikiServices;
use OutputPage;
use SkinTemplate;
use Title;
use User;
use WebRequest;

class PluggableAuthHooks {

	/**
	 * Implements extension registration callback.
	 * See https://www.mediawiki.org/wiki/Manual:Extension_registration#Customizing_registration
	 * Removes password providers if local login is not enabled.
	 *
	 * @since 2.0
	 *
	 */
	public static function onRegistration() {
		if ( $GLOBALS['wgPluggableAuth_EnableLocalLogin'] ) {
			return;
		}
		$passwordProviders = [
			'MediaWiki\Auth\LocalPasswordPrimaryAuthenticationProvider',
			'MediaWiki\Auth\TemporaryPasswordPrimaryAuthenticationProvider'
		];
		$providers = $GLOBALS['wgAuthManagerAutoConfig'];
		if ( isset( $providers['primaryauth'] ) ) {
			$primaries = $providers['primaryauth'];
			foreach ( $primaries as $key => $provider ) {
				if ( in_array( $provider['class'], $passwordProviders ) ) {
					unset( $GLOBALS['wgAuthManagerAutoConfig']['primaryauth'][$key] );
				}
			}
		}
	}

	/**
	 * Implements TitleReadWhitelist hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/TitleReadWhitelist
	 * Adds PluggableAuth login special pages to whitelist.
	 *
	 * @since 2.0
	 * @param Title $title being checked
	 * @param User $user Current user
	 * @param bool &$whitelisted whether this title is whitelisted
	 *
	 */
	public static function onTitleReadWhitelist(
		Title $title,
		User $user,
		bool &$whitelisted
	) {
		$pluggableAuthService = MediaWikiServices::getInstance()->get( 'PluggableAuthService' );
		$pluggableAuthService->allowLoginPage( $title, $whitelisted );
	}

	/**
	 * Implements AuthChangeFormFields hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/AuthChangeFormFields
	 * Moves login button to bottom of form.
	 *
	 * @since 2.0
	 * @param array $requests AuthenticationRequests the fields are created from
	 * @param array $fieldInfo union of AuthenticationRequest::getFieldInfo()
	 * @param array &$formDescriptor The special key weight can be set to
	 *        change the order of the fields.
	 * @param string $action one of the AuthManager::ACTION_* constants.
	 */
	public static function onAuthChangeFormFields(
		array $requests,
		array $fieldInfo,
		array &$formDescriptor,
		string $action
	) {
		$pluggableAuthService = MediaWikiServices::getInstance()->get( 'PluggableAuthService' );
		$pluggableAuthService->moveLoginButton( $formDescriptor );
	}

	/**
	 * Implements UserLogoutComplete hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/UserLogoutComplete
	 * Calls deauthenticate hook in authentication plugin.
	 *
	 * @since 2.0
	 * @param User $user User after logout (won't have name, ID, etc.)
	 * @param string $inject_html Any HTML to inject after the logout message.
	 * @param string $old_name The text of the username that just logged out.
	 */
	public static function deauthenticate(
		User $user,
		string $inject_html,
		string $old_name
	) {
		$pluggableAuthService = MediaWikiServices::getInstance()->get( 'PluggableAuthService' );
		$pluggableAuthService->deauthenticate( $old_name );
	}

	/**
	 * Grab the page request early
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforeInitialize
	 * Redirects ASAP to login
	 * @param Title &$title being used for request
	 * @param null $article unused
	 * @param OutputPage $out object
	 * @param User $user current user
	 * @param WebRequest $request why we're here
	 * @param MediaWiki $mw object
	 *
	 * Note that $title has to be passed by ref so we can replace it.
	 */
	public static function doBeforeInitialize(
		Title &$title,
		$article,
		OutputPage $out,
		User $user,
		WebRequest $request,
		MediaWiki $mw
	) {
		$pluggableAuthService = MediaWikiServices::getInstance()->get( 'PluggableAuthService' );
		$pluggableAuthService->autoLogin( $title, $out, $user, $request );
	}

	/**
	 * Implements PersonalUrls hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/PersonalUrls
	 * Removes logout link from skin if auto login is enabled and local login
	 * is not enabled.
	 *
	 * @since 1.0
	 *
	 * @param array &$personal_urls urls sto modify
	 * @param Title|null $title current title
	 * @param SkinTemplate|null $skin template for vars
	 */
	public static function modifyLoginURLs(
		array &$personal_urls,
		Title $title = null,
		SkinTemplate $skin = null
	) {
		$pluggableAuthService = MediaWikiServices::getInstance()->get( 'PluggableAuthService' );
		$pluggableAuthService->removeLogoutLink( $personal_urls );
	}

	/**
	 * Implements LocalUserCreated hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * Populate groups after the local user is created
	 * Called immediately after a local user has been created and saved to the database.
	 *
	 * @since 5.5
	 *
	 * @param User $user current user
	 * @param bool $autocreated whether the user was autocreated
	 */
	public static function onLocalUserCreated(
		User $user,
		bool $autocreated
	) {
		$pluggableAuthService = MediaWikiServices::getInstance()->get( 'PluggableAuthService' );
		$pluggableAuthService->populateGroups( $user, $autocreated );
	}
}
