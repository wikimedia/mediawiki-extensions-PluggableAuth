<?php

use \MediaWiki\Auth\ButtonAuthenticationRequest;
use \MediaWiki\Auth\AuthManager;

class PluggableAuthBeginAuthenticationRequest extends
	ButtonAuthenticationRequest {

	public function __construct() {
		parent::__construct(
			'pluggableauthlogin',
			wfMessage( 'pluggableauth-loginbutton-label' ),
			wfMessage( 'pluggableauth-loginbutton-help' ),
			true );
	}

	public function getFieldInfo() {
		if ( $this->action !== AuthManager::ACTION_LOGIN ) {
			return [];
		}
		return array_merge( $GLOBALS['wgPluggableAuth_ExtraLoginFields'],
			parent::getFieldInfo() );
	}
}
