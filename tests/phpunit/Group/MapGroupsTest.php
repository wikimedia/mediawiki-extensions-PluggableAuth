<?php

namespace MediaWiki\Extension\PluggableAuth\Tests\GroupProcessor;

use HashConfig;
use MediaWiki\Extension\PluggableAuth\Group\MapGroups;
use MediaWikiIntegrationTestCase;
use TestUserRegistry;

/**
 * @group Database
 */
class MapGroupsTest extends MediaWikiIntegrationTestCase {

	/**
	 *
	 * @param array $attributes
	 * @param array $configArray
	 * @param array $initialGroups
	 * @param array $expectedGroups
	 * @dataProvider provideRunData
	 * @covers MediaWiki\Extension\PluggableAuth\Group\MapGroups::run
	 */
	public function testRun( $attributes, $configArray, $initialGroups, $expectedGroups ) {
		$testUser = TestUserRegistry::getMutableTestUser( 'MapGroupsTestUser', $initialGroups );
		$user = $testUser->getUser();
		$config = new HashConfig( $configArray );
		$groupManager = $this->getServiceContainer()->getUserGroupManager();

		$processor = new MapGroups( $groupManager );
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
			// Tests from Extension:PluggableAuth - START
			'default-example' => [
				[ 'groups' => [ 'administrator', 'dontsync' ] ],
				[
					'map' => [ 'sysop' => [ 'groups' => [ 'administrator' ] ] ],
					'groupAttributeDelimiter' => null
				],
				[ 'abc' ],
				[ 'abc', 'sysop' ]
			],
			'delimiter-example' => [
				[ 'groups' => [ 'administrator,dontsync' ] ],
				[
					'map' => [ 'sysop' => [ 'groups' => [ 'administrator' ] ] ],
					'groupAttributeDelimiter' => ','
				],
				[ 'abc' ],
				[ 'abc', 'sysop' ]
			],
			'two-attributes' => [
				[
					'member' => [ 'group-1', 'group-2', 'group-3' ],
					'NameId' => [ 'firstname.lastname-1' ],
				],
				[
					'map' => [
						'editor' => [
							'member' => [ 'group-1' ],
							'NameId' => [ 'firstname.lastname-2', 'firstname.lastname-3' ],
						],
						'sysop' => [
							'member' => [ 'group-1' ],
							'NameId' => [ 'firstname.lastname-2', 'firstname.lastname-3' ],
						],
					],
					'groupAttributeDelimiter' => null
				],
				[ 'abc' ],
				[ 'abc', 'editor', 'sysop' ]
			],
			'delete' => [
				[
					// Not in SAML attributes anymore
					// 'has-abc' => [ 'yes' ]
					'not-mapped' => [ 'dontsync' ]
				],
				[
					'map' => [ 'abc' => [ 'has-abc' => [ 'yes' ] ] ],
					'groupAttributeDelimiter' => null
				],
				[ 'abc', 'sysop' ],
				[ 'sysop' ]
			],
			'Topic:V1k0yrv1f3ir7y6r-1' => [
				[ 'businessCategory' => [ 'B', 'N', 'Z' ] ],
				[
					'map' => [ 'staffer' => [ 'businessCategory' => [ 'B', 'N', 'Z' ] ] ],
					'groupAttributeDelimiter' => null
				],
				[ 'abc' ],
				[ 'abc', 'staffer' ]
			],
			'Topic:V1k0yrv1f3ir7y6r-2' => [
				[ 'businessCategory' => [ 'B,N,Z' ] ],
				[
					'map' => [ 'staffer' => [ 'businessCategory' => [ 'B', 'N', 'Z' ] ] ],
					'groupAttributeDelimiter' => ','
				],
				[ 'abc' ],
				[ 'abc', 'staffer' ]
			],
			'T304951-regex-positive' => [
				[
					'groups' => [ 'group-1', 'group-2' ]
				],
				[
					'map' => [ 'wiki_group_from_regex' => [
						'groups' => [ static function ( $pluginProvidedGroups ) {
							return count( preg_grep( '/^group-\d+$/', $pluginProvidedGroups ) );
						} ]
					] ]
				],
				[ 'sysop' ],
				[ 'sysop', 'wiki_group_from_regex' ]
			],
			'T304951-regex-negative' => [
				[
					'groups' => [ 'group-a', 'group-b' ]
				],
				[
					'map' => [ 'wiki_group_from_regex' => [
						'groups' => [ static function ( $pluginProvidedGroups ) {
							return count( preg_grep( '/^group-\d+$/', $pluginProvidedGroups ) );
						} ]
					] ]
				],
				[ 'sysop' ],
				[ 'sysop' ]
			],
			'T304951-regex-remove' => [
				[
					'groups' => [ 'group-a', 'group-b' ]
				],
				[
					'map' => [ 'wiki_group_from_regex' => [
						'groups' => [ static function ( $pluginProvidedGroups ) {
							return count( preg_grep( '/^group-\d+$/', $pluginProvidedGroups ) );
						} ]
					] ]
				],
				[ 'sysop', 'wiki_group_from_regex' ],
				[ 'sysop' ]
			],
			'T304950-will-add-with-addonly' => [
				[
					'groups' => [ 'group-1' ]
				],
				[
					'map' => [
						'addonly_wikigroup' => [
							'groups' => [ 'group-1' ]
						]
					],
					'addOnlyGroups' => [ 'addonly_wikigroup' ]
				],
				[ 'initial_wiki_group_1' ],
				[ 'initial_wiki_group_1', 'addonly_wikigroup' ]
			],
			'T304950-wont-remove-with-addonly' => [
				[
					'groups' => [ 'group-999' ]
				],
				[
					'map' => [
						'addonly_wikigroup' => [
							'groups' => [ 'group-1' ]
						]
					],
					'addOnlyGroups' => [ 'addonly_wikigroup' ]
				],
				[ 'addonly_wikigroup', 'initial_wiki_group_1' ],
				[ 'addonly_wikigroup', 'initial_wiki_group_1' ]
			],
			// Tests from Extension:PluggableAuth - END

			// Tests from Extension:LDAPGroups - START
			// Those had to be slightly modified as the "group list" must now be an associative array
			// https://www.mediawiki.org/w/index.php?title=Extension:LdapGroups&oldid=2595259#Group_mapping
			'set-from-ldap-and-remove-local-1' => [
				[
					'groupDNs' => [ 'nc=aws-production,ou=security group,o=top' ]
				],
				[
					'map' => [
						'AWSUsers' => [
							'groupDNs' => [ 'nc=aws-production,ou=security group,o=top' ]
						],
						'NavAndGuidance' => [
							'groupDNs' => [
								'cn=g001,OU=Groups,o=top',
								'cn=g002,OU=Groups,o=top',
								'cn=g003,OU=Groups,o=top'
							]
						]
					]
				],
				[ 'sysop', 'some_group', 'NavAndGuidance' ],
				[ 'sysop', 'some_group', 'AWSUsers' ]
			],
			'set-from-ldap-and-remove-local-2' => [
				[
					// Casing does not match!
					'groupDNs' => [ 'OU=SCIENTISTS,DC=EXAMPLE,DC=COM' ]
				],
				[
					'map' => [
						'mathematicians' => [
							'groupDNs' => [ 'ou=mathematicians,dc=example,dc=com' ]
						],
						'scientists' => [
							'groupDNs' => [ 'ou=scientists,dc=example,dc=com' ]
						]
					]
				],
				[ 'sysop', 'some_group', 'mathematicians' ],
				[ 'sysop', 'some_group', 'scientists' ]
			],
			'Topic:V3s73k1q4736ov68#1' => [
				[
					'groupDNs' => [ 'cn=wiki,cn=groups,dc=xx,dc=xxx' ]
				],
				[
					'map' => [
						'sysop' => [
							'groupDNs' => "cn=wiki,cn=groups,dc=xx,dc=xxx"
						]
					]
				],
				[],
				[ 'sysop' ]
			],
			'Topic:Vn9jblaqr69fim14#1' => [
				[
					'groupDNs' => [ 'CN=Wiki_ReadOnly,OU=Groups,DC=mydomain,DC=net' ]
				],
				[
					'map' => [
						'sysop' => [
							'groupDNs' => "CN=Wiki_Admin,OU=Groups,DC=mydomain,DC=net"
						],
						'wiki-read' => [
							'groupDNs' => "CN=Wiki_ReadOnly,OU=Groups,DC=mydomain,DC=net"
						],
						'wiki-write' => [
							'groupDNs' => "CN=Wiki_ReadWrite,OU=Groups,DC=mydomain,DC=net"
						]
					]
				],
				[ 'wiki-read', 'wiki-write' ],
				[ 'wiki-read' ]
			]
			// Tests from Extension:LDAPGroups - END
		];
	}
}
