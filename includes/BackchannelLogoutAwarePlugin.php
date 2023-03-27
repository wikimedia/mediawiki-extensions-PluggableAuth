<?php

namespace MediaWiki\Extension\PluggableAuth;

use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Session\SessionManagerInterface;

interface BackchannelLogoutAwarePlugin {

	/**
	 * @param RequestInterface $request
	 * @return bool
	 */
	public function canHandle( RequestInterface $request ): bool;

	/**
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @param SessionManagerInterface $sessionManager
	 * @return void
	 */
	public function performBackchannelLogout(
		RequestInterface $request,
		ResponseInterface $response,
		SessionManagerInterface $sessionManager
	): void;
}
