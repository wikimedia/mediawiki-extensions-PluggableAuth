<?php

namespace MediaWiki\Extension\PluggableAuth\Test;

use ExtensionRegistry;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\PluggableAuth\Group\GroupProcessorRunner;
use MediaWiki\Extension\PluggableAuth\PluggableAuthFactory;
use MediaWiki\Extension\PluggableAuth\PluggableAuthPlugin;
use MediaWiki\Extension\PluggableAuth\PluggableAuthService;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

class PluggableAuthServiceTest extends MediaWikiIntegrationTestCase {

	/**
	 *
	 * @param array $links
	 * @param array $expectedLinks
	 * @param array $options
	 * @param bool $shouldOverrideDefaultLogout
	 * @param string $msg
	 * @return void
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @covers       MediaWiki\Extension\PluggableAuth\PluggableAuthService::modifyLogoutLink
	 * @dataProvider provideTestModifyLogoutLinkData
	 */
	public function testModifyLogoutLink( $links, $expectedLinks, $options, $shouldOverrideDefaultLogout, $msg ) {
		$serviceOptions = new ServiceOptions(
			PluggableAuthService::CONSTRUCTOR_OPTIONS,
			$options
		);
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$userFactory = $this->createMock( UserFactory::class );
		$pluggableAuthPlugin = $this->createMock( PluggableAuthPlugin::class );
		$pluggableAuthPlugin->method( 'shouldOverrideDefaultLogout' )->willReturn( $shouldOverrideDefaultLogout );
		$pluggableAuthFactory = $this->createMock( PluggableAuthFactory::class );
		$pluggableAuthFactory->method( 'getInstance' )->willReturn( $pluggableAuthPlugin );
		$groupProcessorRunner = $this->createMock( GroupProcessorRunner::class );
		$permissionManager = $this->createMock( PermissionManager::class );
		$authManager = $this->createMock( AuthManager::class );
		$logger = $this->createMock( LoggerInterface::class );
		$service = new PluggableAuthService(
			$serviceOptions,
			$extensionRegistry,
			$userFactory,
			$pluggableAuthFactory,
			$groupProcessorRunner,
			$permissionManager,
			$authManager,
			$logger
		);

		$service->modifyLogoutLink( $links );

		$this->assertEquals( $expectedLinks, $links, $msg );
	}

	public function provideTestModifyLogoutLinkData() {
		return [
			'no-replacement' => [
				[
					'user-menu' => [
						'logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'user-menu' => [
						'logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'PluggableAuth_EnableAutoLogin' => false,
					'PluggableAuth_EnableLocalLogin' => false,
					'PluggableAuth_EnableFastLogout' => false
				],
				false,
				'Should not replace link',
			],
			'no-logout-link' => [
				[],
				[],
				[
					'PluggableAuth_EnableAutoLogin' => false,
					'PluggableAuth_EnableLocalLogin' => false,
					'PluggableAuth_EnableFastLogout' => false
				],
				true,
				'Should not add a custom logout link or emit errors',
			],
			'regular-replacement' => [
				[
					'user-menu' => [
						'logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'user-menu' => [
						'pluggableauth-logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'PluggableAuth_EnableAutoLogin' => false,
					'PluggableAuth_EnableLocalLogin' => false,
					'PluggableAuth_EnableFastLogout' => false
				],
				true,
				'Should add custom Special:Userlogout url with proper `returnto`',
			],
			'regular-replacement-fast' => [
				[
					'user-menu' => [
						'logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'user-menu' => [
						'pluggableauth-logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:PluggableAuthLogout&returnto=Main_Page'
						]
					]
				],
				[
					'PluggableAuth_EnableAutoLogin' => false,
					'PluggableAuth_EnableLocalLogin' => false,
					'PluggableAuth_EnableFastLogout' => true
				],
				true,
				'Should add custom Special:PluggableAuthLogout url with proper `returnto`',
			],
			'remove-link-due-to-autologin' => [
				[
					'user-menu' => [
						'logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'user-menu' => []
				],
				[
					'PluggableAuth_EnableAutoLogin' => true,
					'PluggableAuth_EnableLocalLogin' => false,
					'PluggableAuth_EnableFastLogout' => false
				],
				true,
				'Should remove logout link because of `AutoLogin`',
			],
			'replacement-due-to-local-login-with-auto-login' => [
				[
					'user-menu' => [
						'logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'user-menu' => [
						'pluggableauth-logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'PluggableAuth_EnableAutoLogin' => true,
					'PluggableAuth_EnableLocalLogin' => true,
					'PluggableAuth_EnableFastLogout' => false
				],
				true,
				'Should replace logout link because of `LocalLogin`',
			],
			'replacement-due-to-local-login-with-auto-login-fast' => [
				[
					'user-menu' => [
						'logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:Userlogout&returnto=Main_Page'
						]
					]
				],
				[
					'user-menu' => [
						'pluggableauth-logout' => [
							'text' => 'Log out',
							'href' => '/index.php?title=Special:PluggableAuthLogout&returnto=Main_Page'
						]
					]
				],
				[
					'PluggableAuth_EnableAutoLogin' => true,
					'PluggableAuth_EnableLocalLogin' => true,
					'PluggableAuth_EnableFastLogout' => true
				],
				true,
				'Should replace logout link (fast) because of `LocalLogin`',
			]
		];
	}
}
