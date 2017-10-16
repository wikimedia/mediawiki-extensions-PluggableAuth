<?php

use \MediaWiki\Auth\ButtonAuthenticationRequest;

class PluggableAuthBeginAuthenticationRequest extends ButtonAuthenticationRequest {

	public function __construct() {
		parent::__construct(
			'pluggableauthlogin',
			wfMessage('pluggableauth-loginbutton-label'),
			wfMessage('pluggableauth-loginbutton-help'),
			true);
	}

}
