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

interface PluggableAuthPlugin {

	/**
	 * Must only be called by `PluggableAuthFactory`
	 * @param string $configId
	 * @param array $config
	 * @return void
	 * @since 6.0
	 */
	public function init( string $configId, array $config );

	/**
	 * @param int|null &$id The user's user ID
	 * @param string|null &$username The user's username
	 * @param string|null &$realname The user's real name
	 * @param string|null &$email The user's email address
	 * @param string|null &$errorMessage Returns a descriptive message if there's an error
	 * @return bool true if the user has been authenticated and false otherwise
	 * @since 1.0
	 */
	public function authenticate(
		?int &$id,
		?string &$username,
		?string &$realname,
		?string &$email,
		?string &$errorMessage
	): bool;

	/**
	 * @param UserIdentity &$user The user
	 * @since 1.0
	 */
	public function deauthenticate( UserIdentity &$user ): void;

	/**
	 * @return string
	 * @since 7.0
	 */
	public function getConfigId(): string;

	/**
	 * @return Config
	 * @since 7.0
	 */
	public function getData(): Config;

	/**
	 * @return array
	 * @since 7.0
	 */
	public function getGroupSyncs(): array;

	/**
	 * @param UserIdentity $user
	 * @return array
	 * @since 7.0
	 */
	public function getAttributes( UserIdentity $user ): array;

	/**
	 * @param int $id The user's user ID
	 * @since 1.0
	 */
	public function saveExtraAttributes( int $id ): void;

	/**
	 * @return bool
	 * @since 7.0
	 */
	public function shouldOverrideDefaultLogout(): bool;
}
