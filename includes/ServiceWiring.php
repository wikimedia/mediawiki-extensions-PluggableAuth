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
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\PluggableAuth\Group\GroupProcessorFactory;
use MediaWiki\Extension\PluggableAuth\Group\GroupProcessorRunner;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'PluggableAuthFactory' =>
		static function ( MediaWikiServices $services ): PluggableAuthFactory {
			return new PluggableAuthFactory(
				new ServiceOptions( PluggableAuthFactory::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
				ExtensionRegistry::getInstance(),
				$services->getAuthManager(),
				LoggerFactory::getInstance( 'PluggableAuth' ),
				$services->getObjectFactory()
			);
		},
	'PluggableAuthService' =>
		static function ( MediaWikiServices $services ): PluggableAuthService {
			return new PluggableAuthService(
				new ServiceOptions( PluggableAuthService::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
				ExtensionRegistry::getInstance(),
				$services->getUserFactory(),
				$services->get( 'PluggableAuthFactory' ),
				$services->get( 'PluggableAuth.GroupProcessorRunner' ),
				$services->getPermissionManager(),
				$services->getAuthManager(),
				LoggerFactory::getInstance( 'PluggableAuth' )
			);
		},
	'PluggableAuth.GroupProcessorFactory' =>
		static function ( MediaWikiServices $services ): GroupProcessorFactory {
			$factory = new GroupProcessorFactory(
				ExtensionRegistry::getInstance()->getAttribute( 'PluggableAuthGroupSyncs' ),
				$services->getObjectFactory()
			);
			$factory->setLogger( LoggerFactory::getInstance( 'PluggableAuth' ) );
			return $factory;
		},
	'PluggableAuth.GroupProcessorRunner' =>
		static function ( MediaWikiServices $services ): GroupProcessorRunner {
			$factory = new GroupProcessorRunner(
				$services->getService( 'PluggableAuth.GroupProcessorFactory' )
			);
			$factory->setLogger( LoggerFactory::getInstance( 'PluggableAuth' ) );
			return $factory;
		},
];
