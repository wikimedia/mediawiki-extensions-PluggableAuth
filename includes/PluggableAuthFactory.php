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

use Exception;
use ExtensionRegistry;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use Message;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RawMessage;

class PluggableAuthFactory {

	public const CONSTRUCTOR_OPTIONS = [
		'PluggableAuth_Config'
	];

	/**
	 * @var AuthManager
	 */
	private $authManager;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var \Wikimedia\ObjectFactory|\Wikimedia\ObjectFactory\ObjectFactory
	 */
	private $objectFactory;

	/**
	 * @var array
	 */
	private $pluggableAuthConfig = [];

	/**
	 * @var PluggableAuthPlugin[]
	 */
	private $instances = [];

	/**
	 * @param ServiceOptions $options
	 * @param ExtensionRegistry $extensionRegistry
	 * @param AuthManager $authManager
	 * @param LoggerInterface $logger
	 * @param \Wikimedia\ObjectFactory|\Wikimedia\ObjectFactory\ObjectFactory $objectFactory
	 */
	public function __construct(
		ServiceOptions $options,
		ExtensionRegistry $extensionRegistry,
		AuthManager $authManager,
		LoggerInterface $logger,
		$objectFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->initConfig( $options->get( 'PluggableAuth_Config' ), $extensionRegistry );
		$this->authManager = $authManager;
		$this->logger = $logger;
		$this->objectFactory = $objectFactory;
	}

	/**
	 * Populate $this->pluggableAuthConfig from $wgPluggableAuth_Config, removing invalid entries.
	 * @param array $config
	 * @param ExtensionRegistry $extensionRegistry
	 */
	private function initConfig(
		array $config,
		ExtensionRegistry $extensionRegistry
	): void {
		$index = 0;
		foreach ( $config as $configId => $entry ) {
			if ( !isset( $entry['plugin'] ) ) {
				continue;
			}
			$plugin = $entry['plugin'];

			$spec = $extensionRegistry->getAttribute( 'PluggableAuth' . $plugin );
			if ( !isset( $spec['class'] ) ) {
				continue;
			}

			if ( isset( $entry['buttonLabelMessage'] ) ) {
				$label = new Message( $entry['buttonLabelMessage'] );
			} else {
				$label = new RawMessage( strval( $configId ) );
			}

			$name = 'pluggableauthlogin' . $index++;
			$this->pluggableAuthConfig[$name] = [
				'configId' => $configId,
				'plugin' => $plugin,
				'spec' => $spec,
				'data' => $entry['data'] ?? [],
				'groupsyncs' => $entry['groupsyncs'] ?? [],
				'label' => $label
			];

			if ( isset( $entry['weight'] ) ) {
				$this->pluggableAuthConfig[$name]['weight'] = $entry['weight'];
			}
		}
	}

	/**
	 * Get the validated configuration array
	 * @return array
	 */
	public function getConfig(): array {
		return $this->pluggableAuthConfig;
	}

	/**
	 * Get the configuration for the plugin currently being used for authentication or null if not in the
	 * authentication flow
	 * @return array|null
	 */
	public function getCurrentConfig(): ?array {
		$name = $this->authManager->getRequest()->getSessionData(
			PluggableAuthLogin::AUTHENTICATIONPLUGINNAME_SESSION_KEY
		);
		if ( $name !== null && isset( $this->pluggableAuthConfig[$name] ) ) {
			return $this->pluggableAuthConfig[$name];
		}
		return null;
	}

	/**
	 * @return PluggableAuthPlugin[]
	 */
	public function getInstances(): array {
		$this->initInstances();
		return $this->instances;
	}

	/**
	 * @var bool
	 */
	private $instancesInited = false;

	private function initInstances() {
		if ( $this->instancesInited ) {
			return;
		}

		$pluginConfigNames = array_keys( $this->pluggableAuthConfig );
		foreach ( $pluginConfigNames as $name ) {
			$this->getInstanceByName( $name );
		}
		$this->instancesInited = true;
	}

	/**
	 * @return PluggableAuthPlugin|false an object that implements PluggableAuthPlugin
	 */
	public function getInstance() {
		$this->logger->debug( 'Getting PluggableAuth instance' );
		$name = $this->authManager->getRequest()->getSessionData(
			PluggableAuthLogin::AUTHENTICATIONPLUGINNAME_SESSION_KEY
		);

		return $this->getInstanceByName( $name ) ?? false;
	}

	/**
	 * @param string|null $name
	 * @return PluggableAuthPlugin|null an object that implements PluggableAuthPlugin
	 */
	private function getInstanceByName( ?string $name ): ?PluggableAuthPlugin {
		if ( $name !== null && isset( $this->pluggableAuthConfig[$name] ) ) {
			$config = $this->pluggableAuthConfig[$name];
			$spec = $config['spec'];
			$this->logger->debug( 'Plugin name: ' . $config['plugin'] );
			if ( isset( $this->instances[$name] ) ) {
				$this->logger->debug( 'Instance already exists' );
			} else {
				try {
					/** @var PluggableAuthPlugin */
					$plugin = $this->objectFactory->createObject(
						$spec,
						[
							'assertClass' => PluggableAuthPlugin::class
						]
					);

					if ( $plugin instanceof LoggerAwareInterface ) {
						$pluginLogger = LoggerFactory::getInstance( $config['plugin'] );
						$plugin->setLogger( $pluginLogger );
					}
					$plugin->init( $config['configId'], [
						'data' => $config['data'] ?? [],
						'groupsyncs' => $config['groupsyncs'] ?? []
					] );

					$this->instances[$name] = $plugin;
				} catch ( Exception $e ) {
					$this->logger->debug( 'Invalid authentication plugin class: ' . $e->getMessage() . PHP_EOL );
					return null;
				}
			}
			return $this->instances[$name];
		}
		$this->logger->debug( 'Could not get authentication plugin instance.' );
		return null;
	}
}
