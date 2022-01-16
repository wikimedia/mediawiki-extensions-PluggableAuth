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

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\MediaWikiServices;

class ContinueAuthenticationRequest extends AuthenticationRequest {

	/**
	 * Returns field information.
	 * @return array field information
	 */
	public function getFieldInfo(): array {
		return [];
	}

	/**
	 * Load from submission.
	 * @param array $data data (ignored)
	 * @return bool success
	 */
	public function loadFromSubmission( array $data ): bool {
		$authManager = MediaWikiServices::getInstance()->getAuthManager();
		$error = $authManager->getAuthenticationSessionData( PluggableAuthLogin::ERROR_SESSION_KEY );
		if ( $error === null ) {
			$this->username = $authManager->getAuthenticationSessionData( PluggableAuthLogin::USERNAME_SESSION_KEY );
			$authManager->removeAuthenticationSessionData( PluggableAuthLogin::USERNAME_SESSION_KEY );
		}
		return true;
	}
}
