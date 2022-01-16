<?php

namespace MediaWiki\Extension\PluggableAuth;

use ExtensionRegistry;
use MediaWiki;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use OutputPage;
use SkinTemplate;
use SpecialPage;
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
		User $user,
		string $inject_html,
		string $old_name
	) {
		$old_user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $old_name );
		if ( $old_user === null ) {
			return;
		}
		$logger = LoggerFactory::getInstance( 'PluggableAuth' );
		$logger->debug( 'Deauthenticating ' . $old_name );
		$pluggableauth = PluggableAuth::singleton();
		if ( $pluggableauth ) {
			$pluggableauth->deauthenticate( $old_user );
		}
		$logger->debug( 'Deauthenticated ' . $old_name );
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
	 * @throws MWException
	 */
	public static function doBeforeInitialize(
		Title &$title,
		$article,
		OutputPage $out,
		User $user,
		WebRequest $request,
		MediaWiki $mw
	) {
		if ( !$GLOBALS['wgPluggableAuth_EnableAutoLogin'] ) {
			return;
		}
		if ( !$out->getUser()->isAnon() ) {
			return;
		}

		$pm = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$pm->isEveryoneAllowed( 'read' ) &&
			$pm->userCan( 'read', $user, $title )
		) {
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
		$title = SpecialPage::getTitleFor( 'Userlogin' );
		$url = $title->getFullURL( [
			'returnto' => $oldTitle,
			'returntoquery' => $request->getRawQueryString()
		] );
		if ( $url ) {
			header( 'Location: ' . $url );
		} else {
			throw new MWException( "Could not determine URL for Special:Userlogin" );
		}
		exit;
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
		if ( $GLOBALS['wgPluggableAuth_EnableAutoLogin'] &&
			!$GLOBALS['wgPluggableAuth_EnableLocalLogin'] ) {
			unset( $personal_urls['logout'] );
		}
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
		if ( $autocreated ) {
			MediaWikiServices::getInstance()->getHookContainer()->run( 'PluggableAuthPopulateGroups', [ $user ] );
		}
	}
}
