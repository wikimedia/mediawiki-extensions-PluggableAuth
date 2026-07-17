<?php

namespace MediaWiki\Extension\PluggableAuth\Rest;

use Exception;
use MediaWiki\Extension\PluggableAuth\BackchannelLogoutAwarePlugin;
use MediaWiki\Extension\PluggableAuth\PluggableAuthFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Session\SessionManager;
use MediaWiki\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;

class LogoutHandler extends SimpleHandler {

	/**
	 * @var SessionManagerInterface
	 */
	private $sessionManager = null;

	/**
	 * @var PluggableAuthFactory
	 */
	private $pluggableAuthFactory = null;

	/**
	 * @var LoggerInterface
	 */
	private $logger = null;

	/**
	 * @param PluggableAuthFactory $pluggableAuthFactory
	 */
	public function __construct( PluggableAuthFactory $pluggableAuthFactory ) {
		if ( method_exists( MediaWikiServices::class, 'getSessionManager' ) ) {
			// MW 1.44+
			$this->sessionManager = MediaWikiServices::getInstance()->getSessionManager();
		} else {
			$this->sessionManager = SessionManager::singleton();
		}
		$this->pluggableAuthFactory = $pluggableAuthFactory;
		$this->logger = LoggerFactory::getInstance( 'PluggableAuth' );
	}

	/**
	 * @return \MediaWiki\Rest\ResponseInterface
	 */
	public function run() {
		$this->logger->debug( 'Starting backchannel-Logout' );

		$request = $this->getRequest();
		$response = $this->getResponseFactory()->create();

		$pluggableAuthPlugins = $this->pluggableAuthFactory->getInstances();
		foreach ( $pluggableAuthPlugins as $pluginName => $plugin ) {
			if ( !$plugin instanceof BackchannelLogoutAwarePlugin ) {
				continue;
			}
			if ( $plugin->canHandle( $request ) ) {
				try {
					$this->logger->debug( "Performing backchannel-Logout with $pluginName" );
					$plugin->performBackchannelLogout(
						$request,
						$response,
						$this->sessionManager
					);
				} catch ( Exception $e ) {
					$this->logger->error(
						'Exception while performing backchannel-Logout: ' . $e->getMessage()
					);
				}
				break;
			} else {
				$this->logger->debug( "Plugin $pluginName cannot handle request" );
			}

		}
		$response->setHeader( 'Cache-Control', 'no-store' );

		$this->logger->debug( 'Sending response' );
		return $response;
	}

	/**
	 * @return bool
	 */
	public function needsReadAccess() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function needsWriteAccess() {
		return false;
	}

	/**
	 * @return array|string[]
	 */
	public function getSupportedRequestTypes(): array {
		return [
			RequestInterface::FORM_URLENCODED_CONTENT_TYPE
		];
	}
}
