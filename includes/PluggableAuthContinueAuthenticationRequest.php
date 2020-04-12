<?php

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

class PluggableAuthContinueAuthenticationRequest extends AuthenticationRequest {

	/**
	 * Returns field information.
	 * @return array field information
	 */
	public function getFieldInfo() {
		return [];
	}

	/**
	 * Load from submission.
	 * @param array $data data (ignored)
	 * @return bool success
	 */
	public function loadFromSubmission( array $data ) {
		if ( method_exists( MediaWikiServices::class, 'getAuthManager' ) ) {
			// MediaWiki 1.35+
			$authManager = MediaWikiServices::getInstance()->getAuthManager();
		} else {
			$authManager = AuthManager::singleton();
		}
		$error = $authManager->getAuthenticationSessionData(
			PluggableAuthLogin::ERROR_SESSION_KEY );
		if ( $error === null ) {
			$this->username = $authManager->getAuthenticationSessionData(
				PluggableAuthLogin::USERNAME_SESSION_KEY );
			$authManager->removeAuthenticationSessionData(
				PluggableAuthLogin::USERNAME_SESSION_KEY );
		}
		return true;
	}
}
