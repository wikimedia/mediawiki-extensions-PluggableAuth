<?php

/*
 * Copyright (c) 2015-2016 The MITRE Corporation
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

abstract class PluggableAuth {

	const RETURNURL_SESSION_KEY = 'PluggableAuthLoginReturnToUrl';
	const USERNAME_SESSION_KEY = 'PluggableAuthLoginUsername';
	const REALNAME_SESSION_KEY = 'PluggableAuthLoginRealname';
	const EMAIL_SESSION_KEY = 'PluggableAuthLoginEmail';

	/**
	 * Implements SessionForRequest hook.
	 *
	 * @since 2.0
	 *
	 * @param $session
	 */
	public static function autoLogin( $session ) {
		$user = $session->getUser();
		if ( $user->isAnon() && isset( $GLOBALS['wgPluggableAuth_AutoLogin'] ) &&
			$GLOBALS['wgPluggableAuth_AutoLogin'] ) {
//			self::login( $user, $_REQUEST['title'], $session->getRequest(), $session );
		}
	}

	/**
	 * Implements PersonalUrls hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/PersonalUrls
	 *
	 * @since 1.0
	 *
	 * @param array &$personal_urls
	 * @param Title $title
	 * @param SkinTemplate $skin
	 */
	public static function modifyLoginURLs( array &$personal_urls,
		Title $title = null, SkinTemplate $skin = null ) {
		$urls = array(
			'createaccount',
			'anonlogin'
		);
		foreach ( $urls as $u ) {
			if ( array_key_exists( $u, $personal_urls ) ) {
				unset( $personal_urls[$u] );
			}
		}
		if ( isset( $GLOBALS['wgPluggableAuth_AutoLogin'] ) &&
			$GLOBALS['wgPluggableAuth_AutoLogin'] ) {
			unset( $personal_urls['login'] );
			unset( $personal_urls['logout'] );
		}
		return true;
	}

	/**
	 * Implements SpecialPage_initList hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SpecialPage_initList
	 *
	 * @since 1.0
	 *
	 * @param array &$specialPagesList
	 */
	public static function modifyLoginSpecialPages(
		array &$specialPagesList = null ) {
		$specialpages = array(
			'CreateAccount'
		);
		foreach ( $specialpages as $p ) {
			if ( array_key_exists( $p, $specialPagesList ) ) {
				unset( $specialPagesList[$p] );
			}
		}
		if ( isset( $GLOBALS['wgPluggableAuth_AutoLogin'] ) &&
			$GLOBALS['wgPluggableAuth_AutoLogin'] ) {
			unset( $specialPagesList['Userlogin'] );
			unset( $specialPagesList['Userlogout'] );
		}
		return true;
	}

	/**
	 * @since 1.0
	 *
	 * @param &$id
	 * @param &$username
	 * @param &$realname
	 * @param &$email
	 */
	abstract public function authenticate( &$id, &$username, &$realname,
		&$email );

	/**
	 * @since 1.0
	 *
	 * @param User &$user
	 */
	abstract public function deauthenticate( User &$user );

	/**
	 * @since 1.0
	 *
	 * @param $id
	 */
	abstract public function saveExtraAttributes( $id );

	/**
	 * Implements UserLogout hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/UserLogout
	 *
	 * @since 1.0
	 *
	 * @param User $user
	 */
	public static function logout( User &$user ) {
		$user->doLogout(); // in case deauthenticate does not return
		$instance = self::getInstance();
		if ( is_subclass_of( $instance, 'PluggableAuth' ) ) {
			$instance->deauthenticate( $user );
		}
		return false; // so doLogout does not execute again
	}

	/**
	 * @since 1.0
	 */
	public static function getInstance() {
		if ( isset( $GLOBALS['wgPluggableAuth_Class'] ) &&
			class_exists( $GLOBALS['wgPluggableAuth_Class'] ) &&
			is_subclass_of( $GLOBALS['wgPluggableAuth_Class'],
				'PluggableAuth' ) ) {
			return new $GLOBALS['wgPluggableAuth_Class'];
		}
		wfDebug( 'Could not get authentication plugin instance.' . PHP_EOL );
		return false;

	}
}
