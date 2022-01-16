<?php

namespace MediaWiki\Extension\PluggableAuth;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use RawMessage;

class BeginAuthenticationRequest extends ButtonAuthenticationRequest {

	/**
	 * @var array
	 */
	private $extraLoginFields;

	/**
	 * @param array $extraLoginFields
	 * @param ?string $buttonLabelMessage
	 * @param ?string $buttonLabel
	 */
	public function __construct( array $extraLoginFields, ?string $buttonLabelMessage, ?string $buttonLabel ) {
		$this->extraLoginFields = $extraLoginFields;
		if ( $buttonLabelMessage ) {
			$label = wfMessage( $buttonLabelMessage );
		} elseif ( $buttonLabel ) {
			$label = new RawMessage( $buttonLabel );
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
	public function getFieldInfo(): array {
		if ( $this->action !== AuthManager::ACTION_LOGIN ) {
			return [];
		}
		return array_merge( $this->extraLoginFields, parent::getFieldInfo() );
	}
}
