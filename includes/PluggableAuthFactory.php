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

use Config;
use Exception;
use ExtensionRegistry;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class PluggableAuthFactory {

	public const CONSTRUCTOR_OPTIONS = [
		'PluggableAuth_ExtraLoginFields',
		'PluggableAuth_ButtonLabelMessage',
		'PluggableAuth_ButtonLabel',
	];

	/**
	 * @var array
	 */
	private $pluggableAuthConfig;

	/**
	 * @var AuthManager
	 */
	private $authManager;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var ExtensionRegistry
	 */
	private $extensionRegistry;

	/**
	 * @var PluggableAuth[]
	 */
	private $instances = [];

	/**
	 * @var array
	 */
	private $validatedPlugins = [];

	/**
	 * @var \Wikimedia\ObjectFactory|\Wikimedia\ObjectFactory\ObjectFactory
	 */
	private $objectFactory;

	/**
	 * @param ServiceOptions $options
	 * @param Config $mainConfig
	 * @param AuthManager $authManager
	 * @param LoggerInterface $logger
	 * @param ExtensionRegistry $extensionRegistry
	 * @param \Wikimedia\ObjectFactory|\Wikimedia\ObjectFactory\ObjectFactory $objectFactory
	 */
	public function __construct(
		ServiceOptions $options,
		Config $mainConfig,
		AuthManager $authManager,
		LoggerInterface $logger,
		ExtensionRegistry $extensionRegistry,
		$objectFactory
	) {
		$this->authManager = $authManager;
		$this->logger = $logger;
		$this->extensionRegistry = $extensionRegistry;
		$this->pluggableAuthConfig = $this->initConfig( $options, $mainConfig );
		$this->objectFactory = $objectFactory;
	}

	/**
	 * @return array
	 */
	public function getConfig(): array {
		return $this->pluggableAuthConfig;
	}

	/**
	 * @return PluggableAuth|false a PluggableAuth object
	 */
	public function getInstance() {
		$this->logger->debug( 'Getting PluggableAuth instance' );
		$name = $this->authManager->getRequest()->getSessionData(
			PluggableAuthLogin::AUTHENTICATIONPLUGINNAME_SESSION_KEY
		);
		if ( $name !== null && isset( $this->pluggableAuthConfig[$name] ) ) {
			$spec = $this->pluggableAuthConfig[$name];
			$this->logger->debug( 'Plugin name: ' . $spec['plugin'] );
			if ( isset( $this->instances[$name] ) ) {
				$this->logger->debug( 'Instance already exists' );
			} else {
				try {
					/** @var PluggableAuth */
					$plugin = $this->objectFactory->createObject(
						$spec,
						[
							'assertClass' => PluggableAuth::class
						]
					);

					$pluginLogger = LoggerFactory::getInstance( $spec['plugin'] );
					$plugin->setLogger( $pluginLogger );
					$plugin->init( $spec['configId'], $spec['data'] ?? null );

					$this->instances[$name] = $plugin;
				} catch ( Exception $e ) {
					$this->logger->debug( 'Invalid authentication plugin class: ' . $e->getMessage() . PHP_EOL );
					return false;
				}
			}
			return $this->instances[$name];
		}
		$this->logger->debug( 'Could not get authentication plugin instance.' );
		return false;
	}

	/**
	 * Populate and then validate the configuration array. The configuration array is
	 * populated either from the $wgPluggableAuth_Config configuration variable or, if that is
	 * not set, from the legacy configuration variables for backward compatibility
	 * ($wgPluggableAuth_Class, $wgPluggableAuth_ButtonLabelMessage, $wgPluggableAuth_ButtonLabel,
	 * and $wgPluggableAuth_ExtraLoginFields).
	 * @param ServiceOptions $options
	 * @param Config $mainConfig
	 * @return array
	 */
	private function initConfig(
		ServiceOptions $options,
		Config $mainConfig
	): array {
		if ( $mainConfig->has( 'PluggableAuth_Config' ) ) {
			$this->logger->debug( 'Using $wgPluggableAuth_Config' );
			return $this->validateConfig( $mainConfig->get( 'PluggableAuth_Config' ) );
		} elseif ( $mainConfig->has( 'PluggableAuth_Plugin' ) ) {
			$this->logger->debug( 'Using PluggableAuth_Plugin' );
			return $this->validateConfig(
				[
					[
						'plugin' => $mainConfig->get( 'PluggableAuth_Plugin' ),
						'data' => null,
						'buttonLabelMessage' => $options->get( 'PluggableAuth_ButtonLabelMessage' ),
						'buttonLabel' => $options->get( 'PluggableAuth_ButtonLabel' ),
						'extraLoginFields' => $options->get( 'PluggableAuth_ExtraLoginFields' )
					]
				]
			);
		}
		return [];
	}

	/**
	 * Validates the configuration array, removing invalid entries. Validation conditions are:
	 * - class (required): the name of a PluggableAuth subclass that will be used to instantiate
	 *   the authentication plugin
	 * - data (optional): will be passed to the constructor of the authentication plugin, if set;
	 *   if no 'data' field is provided, no arguments will be passed to the constructor
	 * - buttonLabelMessage (optional): a Message that will be used for the login button label
	 * - buttonLabel (optional): a text string that will be used for the login button label if
	 *   buttonLabelMessage is not set
	 * - extraLoginFields (optional): an array of fields to be added to the login form (see
	 *   documentation at AuthenticationRequest::getFieldInfo for the format). If not set, the
	 *   value will come from the static getExtraLoginFields() function on the authentication plugin.
	 *   That function defaults to an empty array (no extra login fields) in the PluggableAuth
	 *   abstract superclass.
	 *
	 * @param array $config
	 * @return array
	 */
	private function validateConfig( array $config ): array {
		$validatedConfig = [];
		$index = 0;
		foreach ( $config as $configId => $entry ) {
			if ( isset( $entry['plugin'] ) ) {
				$plugin = $entry['plugin'];
				if ( $this->validatePlugin( $plugin ) ) {
					$pluginDescription = $this->validatedPlugins[$plugin];
					$class = $pluginDescription['class'];
					$name = 'pluggableauthlogin' . $index++;
					$validatedConfig[$name] = [
						'configId' => $configId,
						'plugin' => $plugin,
						'class' => $class,
						'services' => $pluginDescription['services'] ?? null,
						'data' => $entry['data'] ?? null,
						'buttonLabelMessage' => $entry['buttonLabelMessage'] ?? null,
						'buttonLabel' => $entry['buttonLabel'] ?? null,
						'extraLoginFields' => $entry['extraLoginFields'] ?? $class::getExtraLoginFields()
					];
				}
			}
		}
		return $validatedConfig;
	}

	private function validatePlugin( $name ): bool {
		if ( isset( $this->validatedPlugins[$name] ) ) {
			return ( $this->pluggableAuthConfig[$name] !== false );
		}
		$plugin = $this->extensionRegistry->getAttribute( 'PluggableAuth' . $name );
		if ( $plugin && isset( $plugin['class'] ) ) {
			$class = $plugin['class'];
			if ( class_exists( $class ) && is_subclass_of( $class, PluggableAuth::class ) ) {
				$this->validatedPlugins[$name] = [
					'class' => $class,
					'services' => $plugin['services'] ?? null
				];
				return true;
			} else {
				$this->validatedPlugins[$name] = false;
			}
		}
		return false;
	}
}
