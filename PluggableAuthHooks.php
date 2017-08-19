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
	 */
	public static function onTitleReadWhitelist(
		Title $title, User $user, &$whitelisted
	) {
		$loginSpecialPages = ExtensionRegistry::getInstance()->getAttribute(
			'PluggableAuthLoginSpecialPages' );
		foreach ( $loginSpecialPages as $page ) {
			if ( $title->isSpecial( $page ) ) {
				$whitelisted = true;
				return;
			}
		}
	}

	/**
	 * Implements AuthChangeFormFields hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/AuthChangeFormFields
	 * Moves login button to bottom of form.
	 *
	 * @since 2.0
	 * @param array $requests AuthenticationRequests the fields are created from
	 * @param array $fieldInfo union of AuthenticationRequest::getFieldInfo()
	 * @param HTMLForm &$formDescriptor The special key weight can be set to
	 *        change the order of the fields.
	 * @param int $action one of the AuthManager::ACTION_* constants.
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		if ( isset( $formDescriptor['pluggableauthlogin'] ) ) {
			$formDescriptor['pluggableauthlogin']['weight'] = 101;
		}
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
		User $user, $inject_html, $old_name
	) {
		$old_user = User::newFromName( $old_name );
		if ( $old_user === false ) {
			return;
		}
		wfDebug( 'Deauthenticating ' . $old_name );
		$pluggableauth = PluggableAuth::singleton();
		if ( $pluggableauth ) {
			$pluggableauth->deauthenticate( $old_user );
		}
		wfDebug( 'Deauthenticated ' . $old_name );
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
		Title &$title, $article, OutputPage $out, User $user,
		WebRequest $request, MediaWiki $mw
	) {
		if ( !$GLOBALS['wgPluggableAuth_EnableAutoLogin'] ) {
			return;
		}
		if ( !$out->getUser()->isAnon() ) {
			return;
		}
		if ( !User::isEveryoneAllowed( 'read' ) && $title->userCan( 'read' ) ) {
			return;
		}
		$loginSpecialPages = ExtensionRegistry::getInstance()->getAttribute(
			'PluggableAuthLoginSpecialPages'
		);
		foreach ( $loginSpecialPages as $page ) {
			if ( $title->isSpecial( $page ) ) {
				return;
			}
		}

		$oldTitle = $title;
		$title = Title::newFromText( "UserLogin", NS_SPECIAL );
		$out->redirect( $title->getFullURL( [
			'returnto' => urlencode( $oldTitle ),
			'returntoquery' => $request->getRawQueryString()
		] ) );
	}

	/**
	 * Implements PersonalUrls hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/PersonalUrls
	 * Removes logout link from skin if auto login is enabled.
	 *
	 * @since 1.0
	 *
	 * @param array &$personal_urls urls sto modify
	 * @param Title $title current title
	 * @param SkinTemplate $skin template for vars
	 */
	public static function modifyLoginURLs(
		array &$personal_urls, Title $title = null, SkinTemplate $skin = null
	) {
		if ( $GLOBALS['wgPluggableAuth_EnableAutoLogin'] ) {
			unset( $personal_urls['logout'] );
		}
	}
}
