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

namespace MediaWiki\Extension\PluggableAuth\Group;

use Monolog\Logger;
use MWException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GroupProcessorFactory implements LoggerAwareInterface {

	/**
	 * @var array
	 */
	private $registry = [];

	/**
	 * @var \Wikimedia\ObjectFactory\ObjectFactory|\Wikimedia\ObjectFactory
	 */
	private $objetFactory = null;

	/**
	 * @var Logger
	 */
	private $logger = null;

	/**
	 * @param array $registry
	 * @param \Wikimedia\ObjectFactory\ObjectFactory|\Wikimedia\ObjectFactory $objectFactory
	 */
	public function __construct( array $registry, $objectFactory ) {
		$this->registry = $registry;
		$this->objetFactory = $objectFactory;
		$this->logger = new NullLogger();
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param string $registryKey
	 * @return IGroupProcessor
	 * @throws MWException
	 */
	public function getInstance( string $registryKey ): IGroupProcessor {
		if ( !isset( $this->registry[$registryKey] ) ) {
			throw new MWException( "No spec found for '$registryKey'!" );
		}
		$object = $this->objetFactory->createObject(
			$this->registry[$registryKey],
			[
				'assertClass' => IGroupProcessor::class
			]
		);

		if ( $object instanceof LoggerAwareInterface ) {
			$object->setLogger( $this->logger );
		}

		return $object;
	}
}
