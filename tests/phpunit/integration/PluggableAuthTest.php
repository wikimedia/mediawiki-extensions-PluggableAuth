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

use ExtensionRegistry;
use FauxRequest;
use MediaWiki\Extension\PluggableAuth\PluggableAuthLogin;
use MediaWikiIntegrationTestCase;
use SpecialPage;
use SpecialUserLogin;

/**
 * @covers \MediaWiki\Extension\PluggableAuth\PluggableAuthLogin::execute()
 */
class PluggableAuthTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgTitle' => SpecialPage::getTitleFor( 'Userlogin' ),
			'wgAuthManagerConfig' => [
				'preauth' => [],
				'primaryauth' => [
					'PluggableAuthPrimaryAuthenticationProvider' => [
						'class' => 'MediaWiki\\Extension\\PluggableAuth\\PrimaryAuthenticationProvider',
						'services' => [
							'MainConfig',
							'UserFactory',
							'PluggableAuthFactory'
						]
					]
				],
				'secondaryauth' => []
			],
			'wgPluggableAuth_Config' => [
				"unauthorized" => [
					'plugin' => 'DummyAuth',
					'buttonLabel' => 'Unauth'
				],
				"authorized" => [
					'plugin' => 'DummyAuth',
					'data' => [
						'username' => 'Dummy',
						'realname' => 'Dummy User',
						'email' => 'dummy@example.com'
					],
					'buttonLabel' => 'Dummy',
				]
			]
		] );
	}

	public function provideAuthenticate() {
		yield [
			'127.0.0.1',
			'pluggableauthlogin0'
		];
		yield [
			'Dummy',
			'pluggableauthlogin1'
		];
	}

	/**
	 * @covers \MediaWiki\Extension\PluggableAuth\PluggableAuthLogin::execute()
	 * @dataProvider provideAuthenticate
	 */
	public function testAuthenticate( string $expected, string $buttonName ): void {
		$callback = ExtensionRegistry::getInstance()->setAttributeForTest(
			'PluggableAuthDummyAuth',
			[
				'class' => '\MediaWiki\Extension\PluggableAuth\Test\DummyAuth',
				'services' => [
					'PluggableAuth.GroupProcessorFactory'
				]
			]
		);

		$this->loginStep( 'login', $buttonName );

		$serviceContainer = $this->getServiceContainer();
		$login = new PluggableAuthLogin(
			$serviceContainer->getService( 'PluggableAuthFactory' ),
			$serviceContainer->getAuthManager(),
			$serviceContainer->getService( 'PluggableAuth.GroupProcessorRunner' )
		);
		$login->setHookContainer( $serviceContainer->getHookContainer() );
		$login->execute( null );

		$this->loginStep( 'login-continue', $buttonName );

		$this->assertEquals( $expected, $login->getContext()->getUser()->getName() );
	}

	private function loginStep( string $step, string $buttonName ) {
		$request = new FauxRequest(
			[
				'authAction' => $step,
				$buttonName => true
			],
			true
		);
		$this->setRequest( $request );
		$login = new SpecialUserLogin( $this->getServiceContainer()->getAuthManager() );
		$token = $login->getRequest()->getSession()->getToken( '', 'login' );
		$request->setVal( 'wpLoginToken', $token );
		$login->execute( null );
	}
}
