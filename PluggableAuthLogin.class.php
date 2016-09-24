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

class PluggableAuthLogin extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'Userlogin' );
	}

	public function execute( $param ) {
		if ( session_id() == '' ) {
			wfSetupSession();
		}
		$session_variable = wfWikiID() . "_returnto";
		$user = $this->getContext()->getUser();
		if ( $user->isLoggedIn() ) {
			if ( !array_key_exists( $session_variable, $_SESSION ) ||
				$_SESSION[$session_variable] === null ) {
				$returnto = Title::newMainPage()->getPrefixedText();
			} else {
				$returnto = $_SESSION[$session_variable];
				unset( $_SESSION[$session_variable] );
			}
			Hooks::run( 'UserLoginComplete', array( &$user, &$injected_html ) );
			PluggableAuth::redirect( $returnto );
		} else {
			if ( !array_key_exists( $session_variable, $_SESSION ) ||
				$_SESSION[$session_variable] === null ) {
				$returnto = htmlentities(
					$this->getRequest()->getVal( 'returnto', '' ),
					ENT_QUOTES );
				$title = Title::newFromText( $returnto );
				if ( is_null( $title ) ) {
					$title = Title::newMainPage();
				}
				$_SESSION[$session_variable] = $title->getPrefixedText();
			}
			$title = Title::newFromText( "Special:UserLogin" );
			$_SERVER['REQUEST_URI'] = $title->getLocalURL();
			PluggableAuth::login( $user );
		}
	}
}

