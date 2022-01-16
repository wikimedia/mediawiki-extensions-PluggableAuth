<?php

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
