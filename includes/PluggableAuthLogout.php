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

use ErrorPageError;
use MediaWiki\Session\SessionManager;
use SpecialPage;
use UnlistedSpecialPage;

class PluggableAuthLogout extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'PluggableAuthLogout' );
	}

	/**
	 * @param string|null $subPage parameters (ignored)
	 */
	public function execute( $subPage ) {
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->setHeaders();
			$this->showSuccess();
			return;
		}

		// Make sure it's possible to log out
		$session = SessionManager::getGlobalSession();
		if ( !$session->canSetUser() ) {
			throw new ErrorPageError(
				'cannotlogoutnow-title',
				'cannotlogoutnow-text',
				[
					$session->getProvider()->describe( $this->getLanguage() )
				]
			);
		}

		$oldUserName = $user->getName();

		$user->logout();
		$this->showSuccess();

		// Hook.
		$injected_html = '';
		$this->getHookRunner()->onUserLogoutComplete( $user, $injected_html, $oldUserName );
		$this->getOutput()->addHTML( $injected_html );
	}

	private function showSuccess() {
		$loginURL = SpecialPage::getTitleFor( 'Userlogin' )->getFullURL(
			$this->getRequest()->getValues( 'returnto', 'returntoquery' ) );
		$out = $this->getOutput();
		$out->addWikiMsg( 'logouttext', $loginURL );
		$out->returnToMain();
	}
}
