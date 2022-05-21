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

use Config;
use MediaWiki\User\UserIdentity;
use MultiConfig;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class Base implements IGroupProcessor, LoggerAwareInterface {

	/**
	 *
	 * @var UserIdentity
	 */
	protected $user = null;

	/**
	 *
	 * @var array
	 */
	protected $attributes = [];

	/**
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 * @var LoggerInterface
	 */
	protected $logger = null;

	public function __construct() {
		$this->logger = new NullLogger();
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function run( UserIdentity $user, array $attributes, Config $config ): void {
		$this->user = $user;
		$this->attributes = $attributes;
		$this->config = new MultiConfig( [
			$config,
			$this->getDefaultConfig()
		] );
		$this->doRun();
	}

	abstract protected function doRun(): void;

	/**
	 * @return Config
	 */
	abstract protected function getDefaultConfig(): Config;
}
