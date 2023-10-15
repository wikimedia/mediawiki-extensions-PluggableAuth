<?php

namespace MediaWiki\Extension\PluggableAuth\Tests\GroupProcessor;

use HashConfig;
use MediaWiki\Extension\PluggableAuth\Group\SyncAllGroups;
use MediaWikiIntegrationTestCase;
use TestUserRegistry;

/**
 * @group Database
 */
class SyncAllGroupsTest extends MediaWikiIntegrationTestCase {

	/**
	 *
	 * @param array $attributes
	 * @param array $configArray
	 * @param array $initialGroups
	 * @param array $expectedGroups
	 * @param array $existingGroups
	 * @dataProvider provideRunData
	 * @covers MediaWiki\Extension\PluggableAuth\Group\SyncAllGroups::run
	 */
	public function testRun( $attributes, $configArray, $initialGroups, $expectedGroups, $existingGroups = [] ) {
		foreach ( $existingGroups as $existingGroup ) {
			$this->setGroupPermissions( $existingGroup, 'read', true );
		}
		$testUser = TestUserRegistry::getMutableTestUser( 'SyncAllGroupsTestUser', $initialGroups );
		$user = $testUser->getUser();
		$config = new HashConfig( $configArray );
		$groupManager = $this->getServiceContainer()->getUserGroupManager();

		$processor = new SyncAllGroups( $groupManager );
		$processor->run( $user, $attributes, $config );
		$actualGroups = $groupManager->getUserGroups( $user );

		// Within the application the sorting is not relevant, but for the test it is.
		sort( $expectedGroups );
		sort( $actualGroups );

		$this->assertArrayEquals(
			$expectedGroups,
			$actualGroups,
			"Groups have not been set properly!"
		);
	}

	/**
	 *
	 * @return array
	 */
	public function provideRunData() {
		return [
			'default-example' => [
				[ 'groups' => [ 'administrator', 'alsosync' ] ],
				[
					'groupAttributeName' => 'groups',
					'locallyManaged' => [ 'abc' ],
					'groupAttributeDelimiter' => null,
					'groupNameModificationCallback' => null
				],
				[ 'abc', 'def' ],
				[ 'abc', 'administrator', 'alsosync' ]
			],
			'delimiter-example' => [
				[ 'groups' => [ 'administrator, alsosync' ] ],
				[
					'groupAttributeName' => 'groups',
					'locallyManaged' => [ 'abc' ],
					'groupAttributeDelimiter' => ',',
					'groupNameModificationCallback' => null
				],
				[ 'abc', 'def' ],
				[ 'abc', 'administrator', 'alsosync' ]
			],
			'delimiter-and-callback-example' => [
				[ 'groups' => [ 'CN=Group_1,OU=ABC,DC=someDomainController | '
					. 'CN=Group_2,OU=ABC,DC=someDomainController | '
					. 'CN=Group_3,OU=ABC,DC=someDomainController' ] ],
				[
					'groupAttributeName' => 'groups',
					'locallyManaged' => [],
					'groupAttributeDelimiter' => ' | ',
					'groupNameModificationCallback' => static function ( $origGroupName ){
						return preg_replace( '#^CN=(.*?),OU=.*$#', '$1', $origGroupName );
					}
				],
				[ 'Group_1', 'Group_1000' ],
				[ 'Group_1', 'Group_2', 'Group_3' ]
			],
			'T297493' => [
				[ 'not_the_configured_attribute' ],
				[
					'groupAttributeName' => 'groups'
				],
				[ 'Group_1', 'sysop' ],
				[ 'sysop' ]
			],
			"Prefixing #1" => [
				[ 'groups' => [ 'GroupB' ] ],
				[
					'filterPrefix' => 'cas-'
				],
				[ 'sysop', 'cas-GroupA' ],
				[ 'sysop', 'cas-GroupB' ],
			],
			"Prefixing #2" => [
				[ 'groups' => [ 'GroupB', 'GroupC', 'GroupD' ] ],
				[
					'filterPrefix' => 'cas-'
				],
				[ 'sysop', 'cas-GroupA', 'cas-GroupB', 'some-locally-assigned-group' ],
				[ 'sysop', 'cas-GroupB', 'cas-GroupC', 'cas-GroupD', 'some-locally-assigned-group' ],
			],
			// https://www.mediawiki.org/w/index.php?title=Topic:Vhct95ynxd8fwf7i&topic_showPostId=vhgso1i82466h5fy#flow-post-vhgso1i82466h5fy
			"Topic:Vhct95ynxd8fwf7i - OIDC compat #1" => [
				[
					"typ" => "Bearer",
					// all the other stuff,
					 "realm_access" => [
						"roles" => [
							"admin",
							"jedi_master"
						]
					 ],
					"resource_access" => [
						"wiki" => [
							"roles" => [
							"editor",
							"admin"
							]
						],
						"other.client" => [
							"roles" => [
								"manage-account",
								"manage-account-links",
								"view-profile"
							]
						]
					]
				],
				[
					'groupAttributeName' => [
						[
							'path' => [ 'realm_access', 'roles' ]
						]
					],
					'filterPrefix' => 'oidc_global'
				],
				[
					'oidc_globaladmin', 'oidc_globalreader',
					'oidc_editor', 'oidc_reader',
				],
				[
					// Should change
					'oidc_globaladmin', 'oidc_globaljedi_master',
					// Should not change
					'oidc_editor', 'oidc_reader',
				]
			],
			"Topic:Vhct95ynxd8fwf7i - OIDC compat #2" => [
				[
					"typ" => "Bearer",
					// all the other stuff,
						"realm_access" => [
						"roles" => [
							"admin",
							"jedi_master"
						]
						],
					"resource_access" => [
						"wiki" => [
							"roles" => [
							"editor",
							"admin"
							]
						],
						"other.client" => [
							"roles" => [
								"manage-account",
								"manage-account-links",
								"view-profile"
							]
						]
					]
				],
				[
					'groupAttributeName' => [
						[
							'path' => [ 'resource_access', 'wiki', 'roles' ],
						]
					],
					'filterPrefix' => 'oidc_',
				],
				[
					'oidc_globaladmin', 'oidc_globalreader',
					'oidc_editor', 'oidc_reader',
				],
				[
					// 'oidc_globaladmin' and 'oidc_globalreader'
					// will be removed due to the overlapping prefix.
					// This is intentional
					'oidc_editor', 'oidc_admin',
				]
			],
			'sync-only-existing-example' => [
				[ 'groups' => [ 'local-admin', 'other-admin' ] ],
				[
					'onlySyncExisting' => true
				],
				[ 'non-existing', 'local-editor' ],
				[ 'non-existing', 'local-admin' ],
				[ 'local-editor', 'local-admin' ]
			],
		];
	}
}
