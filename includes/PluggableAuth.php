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
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class PluggableAuth implements PluggableAuthPlugin, LoggerAwareInterface {

	/**
	 * @var string
	 */
	private $configId = '';

	/**
	 * @var Config
	 */
	private $data;

	/**
	 * @var Config
	 */
	private $groupsyncs;

	/**
	 * @var LoggerInterface
	 */
	private $logger = null;

	/**
	 * @inheritDoc
	 */
	public function init( string $configId, array $config ) {
		$this->configId = $configId;
		$this->data = new CaseInsensitiveHashConfig( $config['data'] ?? [] );
		$this->groupsyncs = $config['groupsyncs'] ?? [];
		if ( $this->logger === null ) {
			$this->logger = new NullLogger();
		}
	}

	/**
	 * @return string
	 * @since 7.0
	 */
	public function getConfigId(): string {
		return $this->configId;
	}

	/**
	 * @return Config
	 * @since 7.0
	 */
	public function getData(): Config {
		return $this->data;
	}

	/**
	 * @return Config
	 * @since 7.0
	 */
	public function getGroupSyncs(): array {
		return $this->groupsyncs;
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return LoggerInterface
	 * @since 7.0
	 */
	public function getLogger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * @param UserIdentity $user
	 * @return array
	 * @since 7.0
	 */
	public function getAttributes( UserIdentity $user ): array {
		return [];
	}

	/**
	 * Return an array of fields to be added to the login form (see documentation at
	 * AuthenticationRequest::getFieldInfo for the format). Subclasses should override this function
	 * if they want to add fields to the login form. If multiple fields have the same array index,
	 * they will be merged into a single field (discarding all but one of the matching fields,
	 * see AuthenticationRequest::mergeFieldInfo()). If multiple instances of the same
	 * authentication plugin want to have their own instances of those fields, the static function
	 * could use a static counter to give them unique array indices.
	 * @return array
	 */
	public static function getExtraLoginFields(): array {
		return [];
	}

	/**
	 * @return bool
	 * @since 7.0
	 */
	public function shouldOverrideDefaultLogout(): bool {
		return false;
	}
}
