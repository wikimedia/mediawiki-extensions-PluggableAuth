<?php

use \MediaWiki\Auth\ButtonAuthenticationRequest;
use \MediaWiki\Auth\AuthManager;

class PluggableAuthBeginAuthenticationRequest extends
	ButtonAuthenticationRequest {

	public function __construct() {
		if ( isset( $GLOBALS['wgPluggableAuth_ButtonLabelMessage'] ) ) {
			$label = wfMessage( $GLOBALS['wgPluggableAuth_ButtonLabelMessage'] );
		} elseif ( $GLOBALS['wgPluggableAuth_ButtonLabel'] ) {
			$label = new RawMessage( $GLOBALS['wgPluggableAuth_ButtonLabel'] );
		} else {
			$label = wfMessage( 'pluggableauth-loginbutton-label' );
		}
		parent::__construct(
			'pluggableauthlogin',
			$label,
			wfMessage( 'pluggableauth-loginbutton-help' ),
			true );
	}

	/**
	 * Returns field information.
	 * @return array field information
	 */
	public function getFieldInfo() {
		if ( $this->action !== AuthManager::ACTION_LOGIN ) {
			return [];
		}
		return array_merge( $GLOBALS['wgPluggableAuth_ExtraLoginFields'],
			parent::getFieldInfo() );
	}
}
