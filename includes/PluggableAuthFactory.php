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
use Psr\Log\LoggerInterface;

class PluggableAuthFactory {

	private $config;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var PluggableAuth
	 */
	private $instance = null;

	/**
	 * @param Config $config
	 * @param LoggerInterface $logger
	 */
	public function __construct( Config $config, LoggerInterface $logger ) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * @return PluggableAuth|false a PluggableAuth object
	 */
	public function getInstance() {
		$class = $this->config->get( 'PluggableAuth_Class' );
		$this->logger->debug( 'Getting PluggableAuth singleton' );
		$this->logger->debug( 'Class name: ' . $class );
		if ( $this->instance !== null ) {
			$this->logger->debug( 'Singleton already exists' );
			return $this->instance;
		} elseif ( class_exists( $class ) && is_subclass_of( $class, PluggableAuth::class ) ) {
			$this->instance = new $class;
			return $this->instance;
		}
		$this->logger->debug( 'Could not get authentication plugin instance.' );
		return false;
	}
}
