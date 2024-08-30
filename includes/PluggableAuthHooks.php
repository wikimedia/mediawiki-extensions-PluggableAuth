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
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\Permissions\Hook\TitleReadWhitelistHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MWException;
use OutputPage;
use Title;
use User;
use WebRequest;

class PluggableAuthHooks implements
	TitleReadWhitelistHook,
	AuthChangeFormFieldsHook,
	UserLogoutCompleteHook,
	BeforeInitializeHook,
	SkinTemplateNavigation__UniversalHook,
	LocalUserCreatedHook,
	SpecialPage_initListHook,
	LoginFormValidErrorMessagesHook
{

	/**
	 * @var PluggableAuthService
	 */
	private $pluggableAuthService;

	/**
	 * @param PluggableAuthService $pluggableAuthService
	 */
	public function __construct( PluggableAuthService $pluggableAuthService ) {
		$this->pluggableAuthService = $pluggableAuthService;
	}

	/**
	 * Implements TitleReadWhitelist hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/TitleReadWhitelist
	 * Adds PluggableAuth login special pages to whitelist.
	 *
	 * @param Title $title being checked
	 * @param User $user Current user
	 * @param bool &$whitelisted whether this title is whitelisted
	 * @since 2.0
	 */
	public function onTitleReadWhitelist( $title, $user, &$whitelisted ) {
		$this->pluggableAuthService->allowLoginPage( $title, $whitelisted );
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
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$this->pluggableAuthService->moveLoginButton( $formDescriptor );
	}

	/**
	 * Implements UserLogoutComplete hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/UserLogoutComplete
	 * Calls deauthenticate hook in authentication plugin.
	 *
	 * @since 2.0
	 * @param User $user User after logout (won't have name, ID, etc.)
	 * @param string &$inject_html Any HTML to inject after the logout message.
	 * @param string $oldName The text of the username that just logged out.
	 */
	public function onUserLogoutComplete( $user, &$inject_html, $oldName ) {
		$this->pluggableAuthService->deauthenticate( $oldName );
	}

	/**
	 * Grab the page request early
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforeInitialize
	 * Redirects ASAP to login
	 * @param Title $title being used for request
	 * @param null $unused
	 * @param OutputPage $output object
	 * @param User $user current user
	 * @param WebRequest $request why we're here
	 * @param MediaWiki $mediaWiki object
	 *
	 * Note that $title has to be passed by ref so we can replace it.
	 * @throws MWException
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		$this->pluggableAuthService->autoLogin( $title, $output, $user, $request );
	}

	// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation

	/**
	 * Implements SkinTemplateNavigation::Universal hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 * Removes logout link from skin if auto login is enabled and local login
	 * is not enabled.
	 *
	 * @inheritDoc
	 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$this->pluggableAuthService->modifyLogoutLink( $links );
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
	public function onLocalUserCreated( $user, $autocreated ) {
		$this->pluggableAuthService->populateGroups( $user, $autocreated );
	}

	/**
	 * Implements SpecialPage_initList hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SpecialPage_initList
	 * Removes CreateAccount special page if local login is not enabled.
	 *
	 * @since 6.0
	 *
	 * @param array &$list
	 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 */
	public function onSpecialPage_initList( &$list ) {
		$this->pluggableAuthService->updateSpecialPages( $list );
	}

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
	 * @param array &$messages
	 * @return void
	 *
	 * @since 7.3.0
	 *
	 */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		$messages[] = 'pluggableauth-fatal-error';
	}
}
