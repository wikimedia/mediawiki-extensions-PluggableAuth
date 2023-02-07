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

use ExtensionRegistry;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;
use MWException;
use OutputPage;
use Psr\Log\LoggerInterface;
use SpecialPage;
use Title;
use User;
use WebRequest;

class PluggableAuthService {

	public const CONSTRUCTOR_OPTIONS = [
		'PluggableAuth_EnableAutoLogin',
		'PluggableAuth_EnableLocalLogin'
	];

	/**
	 * @var bool
	 */
	private $enableAutoLogin;

	/**
	 * @var bool
	 */
	private $enableLocalLogin;

	/**
	 * @var array
	 */
	private $loginSpecialPages;

	/**
	 * @var UserFactory
	 */
	private $userFactory;

	/**
	 * @var PluggableAuthFactory
	 */
	private $pluggableAuthFactory;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var HookRunner
	 */
	private $hookRunner;

	/**
	 * @var AuthManager
	 */
	private $authManager;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param ServiceOptions $options
	 * @param ExtensionRegistry $extensionRegistry
	 * @param UserFactory $userFactory
	 * @param PluggableAuthFactory $pluggableAuthFactory
	 * @param PermissionManager $permissionManager
	 * @param HookRunner $hookRunner
	 * @param AuthManager $authManager
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ServiceOptions $options,
		ExtensionRegistry $extensionRegistry,
		UserFactory $userFactory,
		PluggableAuthFactory $pluggableAuthFactory,
		PermissionManager $permissionManager,
		HookRunner $hookRunner,
		AuthManager $authManager,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->enableAutoLogin = $options->get( 'PluggableAuth_EnableAutoLogin' );
		$this->enableLocalLogin = $options->get( 'PluggableAuth_EnableLocalLogin' );
		$this->loginSpecialPages = $extensionRegistry->getAttribute( 'PluggableAuthLoginSpecialPages' );
		$this->userFactory = $userFactory;
		$this->pluggableAuthFactory = $pluggableAuthFactory;
		$this->permissionManager = $permissionManager;
		$this->hookRunner = $hookRunner;
		$this->authManager = $authManager;
		$this->logger = $logger;
	}

	/**
	 * Adds PluggableAuth login special pages to allowed list.
	 * @param Title $title being checked
	 * @param bool &$whitelisted whether this title is whitelisted
	 *
	 */
	public function allowLoginPage(
		Title $title,
		bool &$whitelisted
	): void {
		foreach ( $this->loginSpecialPages as $page ) {
			if ( $title->isSpecial( $page ) ) {
				$whitelisted = true;
				return;
			}
		}
	}

	/**
	 * Moves login button to bottom of form.
	 * @param array &$formDescriptor The special key weight can be set to
	 *        change the order of the fields.
	 */
	public function moveLoginButton(
		array &$formDescriptor
	): void {
		foreach ( $this->pluggableAuthFactory->getConfig() as $name => $config ) {
			if ( isset( $formDescriptor[$name] ) ) {
				$formDescriptor[$name]['weight'] = 101;
			}
		}
	}

	/**
	 * Log out.
	 * @param string $old_name The text of the username that just logged out.
	 */
	public function deauthenticate(
		string $old_name
	) {
		$old_user = $this->userFactory->newFromName( $old_name );
		// If $old_name is not a user, newFromName returns false for MW 1.35 but null for MW 1.36
		if ( $old_user === null || $old_user === false ) {
			return;
		}
		$this->logger->debug( 'Deauthenticating ' . $old_name );
		$pluggableauth = $this->pluggableAuthFactory->getInstance();
		if ( $pluggableauth ) {
			$pluggableauth->deauthenticate( $old_user );
			$this->authManager->getRequest()->getSession()->remove(
				PluggableAuthLogin::AUTHENTICATIONPLUGINNAME_SESSION_KEY
			);
		}
		$this->logger->debug( 'Deauthenticated ' . $old_name );
	}

	/**
	 * Redirects ASAP to login
	 * @param Title &$title being used for request
	 * @param OutputPage $out object
	 * @param User $user current user
	 * @param WebRequest $request why we're here
	 *
	 * Note that $title has to be passed by ref so we can replace it.
	 * @throws MWException
	 */
	public function autoLogin(
		Title &$title,
		OutputPage $out,
		User $user,
		WebRequest $request
	) {
		if ( !$this->enableAutoLogin ) {
			return;
		}
		if ( !$out->getUser()->isAnon() ) {
			return;
		}

		if ( !$this->permissionManager->isEveryoneAllowed( 'read' ) &&
			$this->permissionManager->userCan( 'read', $user, $title )
		) {
			return;
		}

		foreach ( $this->loginSpecialPages as $page ) {
			if ( $title->isSpecial( $page ) ) {
				return;
			}
		}

		$oldTitle = $title;
		$title = SpecialPage::getTitleFor( 'Userlogin' );
		$url = $title->getFullURL( [
			'returnto' => $oldTitle,
			'returntoquery' => $request->getRawQueryString()
		] );
		if ( $url ) {
			header( 'Location: ' . $url );
		} else {
			throw new MWException( "Could not determine URL for Special:Userlogin" );
		}
		exit;
	}

	/**
	 * Remove logout link from skin if auto login is enabled and local login is not enabled.
	 * @param array &$links URLs to modify
	 */
	public function removeLogoutLink( array &$links ) {
		if ( $this->enableAutoLogin && !$this->enableLocalLogin ) {
			unset( $links['user-menu']['logout'] );
		}
	}

	/**
	 * Populate groups after the local user is created
	 * Called immediately after a local user has been created and saved to the database.
	 * @param User $user current user
	 * @param bool $autocreated whether the user was autocreated
	 */
	public function populateGroups(
		User $user,
		bool $autocreated
	) {
		if ( $autocreated ) {
			$this->hookRunner->onPluggableAuthPopulateGroups( $user );
		}
	}

	/**
	 * Removes CreateAccount special page if local login is not enabled.
	 * @param array &$list
	 */
	public function updateSpecialPages( array &$list ) {
		if ( !$this->enableLocalLogin ) {
			unset( $list['CreateAccount'] );
		}
	}
}
