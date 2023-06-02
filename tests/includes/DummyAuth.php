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

namespace MediaWiki\Extension\PluggableAuth\Test;

use MediaWiki\Extension\PluggableAuth\PluggableAuth;
use MediaWiki\User\UserIdentity;

class DummyAuth extends PluggableAuth {

	/**
	 * @var bool
	 */
	private $auth;

	/**
	 * @var int
	 */
	private $id;

	/**
	 * @var string
	 */
	private $username;

	/**
	 * @var string
	 */
	private $realname;

	/**
	 * @var string
	 */
	private $email;

	/**
	 * @param string $configId
	 * @param array|null $config
	 */
	public function init( string $configId, ?array $config ) {
		parent::init( $configId, $config );
		$data = $config['data'] ?? null;
		if ( $data ) {
			$this->auth = true;
			$this->id = $data['id'] ?? null;
			$this->username = $data['username'];
			$this->realname = $data['realname'];
			$this->email = $data['email'];
		} else {
			$this->auth = false;
		}
	}

	/**
	 * @param int|null &$id
	 * @param string|null &$username
	 * @param string|null &$realname
	 * @param string|null &$email
	 * @param string|null &$errorMessage
	 * @return bool true if user is authenticated, false otherwise
	 *
	 */
	public function authenticate(
		?int &$id,
		?string &$username,
		?string &$realname,
		?string &$email,
		?string &$errorMessage
	): bool {
		if ( $this->auth ) {
			$id = $this->id;
			$username = $this->username;
			$realname = $this->realname;
			$email = $this->email;
		}
		return $this->auth;
	}

	/**
	 * @param UserIdentity &$user
	 */
	public function deauthenticate( UserIdentity &$user ): void {
		// Just a test dummy. Do nothing.
	}

	/**
	 * @param int $id user id
	 */
	public function saveExtraAttributes( int $id ): void {
		// Just a test dummy. Do nothing.
	}
}
