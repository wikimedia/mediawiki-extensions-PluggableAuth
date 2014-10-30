<?php

/*
 * Copyright (c) 2014 The MITRE Corporation
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

	/**
	 * Implements UserLoadFromSession hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/UserLoadFromSession
	 *
	 * @since 1.0
	 *
	 * @param User $user
	 * @param &$result
	 */
	public static function userLoadFromSession( User $user = null,
		&$result = null ) {

		// http://stackoverflow.com/questions/520237/how-do-i-expire-a-php-session-after-30-minutes

		if ( !isset( $GLOBALS['PluggableAuth_Timeout'] ) ) {
			$GLOBALS['PluggableAuth_Timeout'] = 1800;
		}

		if ( $GLOBALS['PluggableAuth_Timeout'] > 0 ) {

			if ( session_id() == '' ) {
				wfSetupSession();
			}

			$time = time();

			if ( isset( $_SESSION['LAST_ACTIVITY'] ) &&
				( $time - $_SESSION['LAST_ACTIVITY'] >
					$GLOBALS['PluggableAuth_Timeout'] ) ) {
				session_unset();
				session_destroy();
				wfDebug( "Session timed out." );
			}
			$_SESSION['LAST_ACTIVITY'] = $time;

			if ( !isset( $_SESSION['CREATED'] ) ) {
				$_SESSION['CREATED'] = $time;
			} elseif ( $time - $_SESSION['CREATED'] >
					$GLOBALS['PluggableAuth_Timeout'] ) {
				session_regenerate_id( true );
				$_SESSION['CREATED'] = $time;
				wfDebug( "Session regenerated." );
			}

		}

		if ( session_id() == '' ) {
			wfSetupSession();
		}

		$session_variable = wfWikiID() . "_userid";
		if ( array_key_exists( $session_variable, $_SESSION ) ) {
			$user->mId = $_SESSION[$session_variable];
			if ( $user->loadFromDatabase() ) {
				$user->saveToCache();
				$result = true;
				return false;
			}
		}

		if ( isset( $GLOBALS['PluggableAuth_AutoLogin'] ) &&
			$GLOBALS['PluggableAuth_AutoLogin'] ) {

			$session_variable = wfWikiID() . "_returnto";
			if ( ( !array_key_exists( $session_variable, $_SESSION ) ||
				$_SESSION[$session_variable] === null ) &&
				array_key_exists( 'title', $_REQUEST ) ) {
				$_SESSION[$session_variable] = $_REQUEST['title'];
			}

			$result = self::login( $user );

		}
		return false;
	}

	/**
	 * Implements UserLogout hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/UserLogout
	 *
	 * @since 1.0
	 *
	 * @param User $user
	 */
	public static function logout( User &$user ) {
		if ( session_id() == '' ) {
			wfSetupSession();
		}

		$session_variable = wfWikiID() . "_userid";
		if ( array_key_exists( $session_variable, $_SESSION ) ) {
			unset( $_SESSION[$session_variable] );
		}
		$instance = self::getInstance();
		if ( !$instance ) {
			return true;
		}
		$instance->deauthenticate( $user );
		session_regenerate_id( true );
		session_destroy();
		unset( $_SESSION );
		return true;
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
		if ( isset( $GLOBALS['PluggableAuth_AutoLogin'] ) &&
			$GLOBALS['PluggableAuth_AutoLogin'] ) {
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
		if ( isset( $GLOBALS['PluggableAuth_AutoLogin'] ) &&
			$GLOBALS['PluggableAuth_AutoLogin'] ) {
			unset( $specialPagesList['Userlogin'] );
			unset( $specialPagesList['Userlogout'] );
		}
		return true;
	}

	/**
	 * Called from PluggableAuthLogin
	 *
	 * @since 1.0
	 *
	 * @param User $user
	 */
	public static function login( $user ) {
		$instance = self::getInstance();
		if ( $instance ) {
			if ( $instance->authenticate( $id, $username, $realname, $email ) ) {
				if ( is_null( $id ) ) {
					$user->loadDefaults( $username );
					$user->mName = $username;
					$user->mRealName = $realname;
					$user->mEmail = $email;
					$user->mEmailAuthenticated = wfTimestamp();
					$user->mTouched = wfTimestamp();
					$user->addToDatabase();
					$instance->saveExtraAttributes( $id );
					wfDebug( "Authenticated/created new user: " . $username );
				} else {
					$user->mId = $id;
					$user->loadFromDatabase();
					self::updateUser( $user, $realname, $email );
					$user->saveToCache();
					wfDebug( "Authenticated existing user: " . $user->mName );
				}
			} else {
				wfDebug( "Authentication failure." );
				return false;
			}
		} else {
			return false;
		}

		$authorized = true;
		wfRunHooks( 'PluggableAuthUserAuthorization', array( $user,
			&$authorized ) );
		$returnto = null;
		$params = null;
		if ( $authorized ) {
			if ( session_id() == '' ) {
				wfSetupSession();
			}
			$session_variable = wfWikiID() . "_userid";
			$_SESSION[$session_variable] = $user->mId;
			$session_variable = wfWikiID() . "_returnto";
			if ( array_key_exists( $session_variable, $_SESSION ) ) {
				$returnto = $_SESSION[$session_variable];
				unset( $_SESSION[$session_variable] );
			}
			wfRunHooks( 'UserLoginComplete', array( &$user, &$injected_html ) );
		} else {
			$returnto = 'Special:PluggableAuthNotAuthorized';
			$params = array( 'name' => $user->mName );
		}
		session_regenerate_id( true );
		self::redirect( $returnto, $params );
		return $authorized;
	}

	/**
	 * @since 1.0
	 *
	 * @param $page
	 * @param $params
	 */
	public static function redirect( $page, $params = null ) {
		$title = Title::newFromText( $page );
		if ( is_null( $title ) ) {
			$title = Title::newMainPage();
		}
		$url = $title->getFullURL();
		if ( is_array( $params ) && count( $params ) > 0 ) {
			$first = true;
			foreach ( $params as $key => $value ) {
				if ( $first ) {
					$first = false;
					$url .= '?';
				} else {
					$url .= '&';
				}
				$url .= $key . '=' . $value;
			}
		}
		$GLOBALS['wgOut']->redirect( $url );
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

	private static function getInstance() {
		if ( isset( $GLOBALS['PluggableAuth_Class'] ) &&
			class_exists( $GLOBALS['PluggableAuth_Class'] ) &&
			is_subclass_of( $GLOBALS['PluggableAuth_Class'],
				'PluggableAuth' ) ) {
			return new $GLOBALS['PluggableAuth_Class'];
		}
		wfDebug( "Could not get authentication plugin instance." );
		return false;

	}

	private static function updateUser( $user, $realname, $email ) {
		if ( $user->mRealName != $realname || $user->mEmail != $email ) {
			$user->mRealName = $realname;
			$user->mEmail = $email;
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update( 'user',
				array( // SET
					'user_real_name' => $realname,
					'user_email' => $email
				), array( // WHERE
					'user_id' => $user->mId
				), __METHOD__
			);
		}
	}

}

