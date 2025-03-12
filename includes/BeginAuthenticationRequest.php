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

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use MediaWiki\Message\Message;

class BeginAuthenticationRequest extends ButtonAuthenticationRequest {
	/**
	 * @var array
	 */
	private $extraLoginFields;

	/**
	 * @param string $name
	 * @param Message $label
	 * @param array $extraLoginFields
	 */
	public function __construct(
		string $name,
		Message $label,
		array $extraLoginFields
	) {
		parent::__construct( $name, $label, new Message( 'pluggableauth-loginbutton-help' ), true );
		$this->extraLoginFields = $extraLoginFields;
	}

	/**
	 * @return string
	 */
	public function getAuthenticationPluginName(): string {
		return $this->name;
	}

	/**
	 * @return array
	 */
	public function getExtraLoginFields(): array {
		return $this->extraLoginFields;
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
