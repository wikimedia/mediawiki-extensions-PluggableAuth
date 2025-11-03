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

use Exception;
use MediaWiki;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\ImgAuthBeforeStreamHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Hook\TitleReadWhitelistHook;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Utils\UrlUtils;
use MWException;

class PluggableAuthHooks implements
	TitleReadWhitelistHook,
	AuthChangeFormFieldsHook,
	UserLogoutCompleteHook,
	BeforeInitializeHook,
	SkinTemplateNavigation__UniversalHook,
	LocalUserCreatedHook,
	SpecialPage_initListHook,
	LoginFormValidErrorMessagesHook,
	ImgAuthBeforeStreamHook,
	AuthPreserveQueryParamsHook,
	PostLoginRedirectHook
{

	/**
	 * @var PluggableAuthService
	 */
	private $pluggableAuthService;

	/**
	 * @var MediaWiki\Utils\UrlUtils
	 */
	private $urlUtils;

	/**
	 * @param PluggableAuthService $pluggableAuthService
	 * @param UrlUtils $urlUtils
	 */
	public function __construct( PluggableAuthService $pluggableAuthService, MediaWiki\Utils\UrlUtils $urlUtils ) {
		$this->pluggableAuthService = $pluggableAuthService;
		$this->urlUtils = $urlUtils;
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

	/**
	 * Try to authenticate if the user is trying to view an image
	 * Step 1: Check if the user is trying to view an image redirect to login if necessary
	 *
	 * @param Title &$title
	 * @param string &$path
	 * @param string &$name
	 * @param array &$result
	 * @return void
	 */
	public function onImgAuthBeforeStream( &$title, &$path, &$name, &$result ) {
		$context = RequestContext::getMain();
		$url = $context->getRequest()->getFullRequestURL();
		$user = $context->getUser();
		$this->pluggableAuthService->autoLoginOnImgAuth( $title, $user, $url );
	}

	/**
	 * Step 2: Make sure full return URL is preserved, so we can redirect after login
	 *
	 * @param array &$params
	 * @param array $options
	 * @return void
	 */
	public function onAuthPreserveQueryParams( array &$params, array $options ) {
		$returnToUrl = $this->maybeGetReturnToUrl();
		if ( !$returnToUrl ) {
			return;
		}
		$params['returntourl'] = $returnToUrl;
		$params['auth_for'] = 'img_auth';
	}

	/**
	 * Step 3: Redirect to the return URL after login
	 * @param string &$returnTo
	 * @param string &$returnToQuery
	 * @param string &$type
	 * @return void
	 */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {
		$returnToUrl = $this->maybeGetReturnToUrl();
		if ( !$returnToUrl ) {
			return;
		}
		header( 'Location: ' . $returnToUrl );
		exit;
	}

	/**
	 * Related to img_auth authentication, check if env flags are set and try to retrieve returntourl
	 * @return string|null
	 */
	private function maybeGetReturnToUrl(): ?string {
		try {
			$url = RequestContext::getMain()->getRequest()->getFullRequestURL();
		} catch ( Exception $e ) {
			return null;
		}

		$parsed = $this->urlUtils->parse( $url );
		$queryParams = wfCgiToArray( $parsed['query'] ?? '' );
		if ( !isset( $queryParams['auth_for'] ) || $queryParams['auth_for'] !== 'img_auth' ) {
			return null;
		}
		if ( !isset( $queryParams['returntourl'] ) ) {
			return null;
		}
		return $queryParams['returntourl'];
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
	 */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		$messages[] = 'pluggableauth-fatal-error';
	}
}
