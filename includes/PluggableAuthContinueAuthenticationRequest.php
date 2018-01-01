<?php

use \MediaWiki\Auth\AuthenticationRequest;
use \MediaWiki\Auth\AuthManager;

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
		$authManager = AuthManager::singleton();
		$error = $authManager->getAuthenticationSessionData(
			PluggableAuthLogin::ERROR_SESSION_KEY );
		if ( is_null( $error ) ) {
			$this->username = $authManager->getAuthenticationSessionData(
				PluggableAuthLogin::USERNAME_SESSION_KEY );
			$authManager->removeAuthenticationSessionData(
				PluggableAuthLogin::USERNAME_SESSION_KEY );
		}
		return true;
	}
}
