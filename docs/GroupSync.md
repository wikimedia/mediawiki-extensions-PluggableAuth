# PluggableAuth Generic GroupSync Mechanism

Inspired by various individual implementations in different plugins (e.g. SimpleSAMLphp, OpenIDConnect, LDAPGroups), version 7 of Extension:PlugableAuth introduces a generic group sync mechanism.

The basic idea is the same in any case. Some remote authentication provider returns a list of user groups that a user should be assigned to. This happens only during login-time. There is no mechanism of pulling group assignments during a regular user session.

Different authentication providers may provide group membership in different formats. E.g.
* flat list of group names
* nested list of group names
* list of group IDs or DNs
* single string with group names separated by a delimiter

Therefore the sync mechanism must be flexible enough to handle all of these cases.

Currently there are two implementations for group sync:
- "Sync all"
- "Mapped"

They have been derived from the existing implementations in the plugins and are as compatible as possible.

## Configuration
Each plugin may define _one or more_ group sync mechanisms. Each mechanism is configured in the `groupsyncs` section of the plugin's configuration at the same level as the plugin-specific `data` item.

```php
$wgPluggableAuth_Config['<plugin-identifier>'] = [
	'plugin' => '<plugin-name>',
	'data' => [
		// ...
	],
	'groupsyncs' => [
		'<groupsync-identifier>' => [
			'type' => '<groupsync-type>',
			// ...
		]
	]
];
```
Both "Mapped" as well as "SyncAll" feature splitting up a delimiter separated string into a list of groups:
| Option                    | Description                              | Default |
| ------------------------- | ---------------------------------------- | ------- |
| `groupAttributeDelimiter` | Delimiter to create a list from a string | `null`  |

## Sync all
The "Sync all" mechanism will take the definitive list of groups from the plugin and compare it to the groups that are currently assigned to the user. The user will then be added to / removed from groups. "Locally managed" groups can be defined. Such groups will be excluded from the sync process.

| Option                          | Description                                                                                | Default       |
| ------------------------------- | ------------------------------------------------------------------------------------------ | ------------- |
| `locallyManaged`                | List of groups that should not be synced                                                   | `[ 'sysop' ]` |
| `groupNameModificationCallback` | Function callback that allows to change the groupName dynamically                          | `null`        |
| `groupAttributeName`            | Name of the attribute to get the groups from                                               | `'groups'`    |
| `filterPrefix`                  | Prefix to be applied to the group names from the attribute list; filter for adds/deletes.  | `''`          |
| `onlySyncExisting`              | Only sync groups that already exist in the wiki                                            | `false`       |

### Basic example
```php
$wgPluggableAuth_Config['example'] = [
	'plugin' => 'ExampleAuthPlugin',
	'data' => [
		// ...
	],
	'groupsyncs' => [
		'default' => [ 'type' => 'syncall' ]
	]
];
```

## Mapped
The "Mapped" mechanism will take a mapping of group names to attributes and their values. The list of groups to be synced is then pre-configured.

### Basic example
```php
$wgPluggableAuth_Config['example'] = [
	'plugin' => 'ExampleAuthPlugin',
	'data' => [
		// ...
	],
	'groupsyncs' => [
		'default' => [
			'type' => 'mapped'
			'map' => [
				'group1' => [ 'groupAttr' => 'value1' ],
				'group2' => [ 'groupAttr' => 'value2' ],
				'group3' => [ 'groupAttr' => 'value3' ]
			]
		]
	]
];
```

# Comsumers of this service
- Extension:SimpleSAMLphp
- Extension:OpenIDConnect

# Plugins that do not use this service
- Extension:LDAPAuthentication2 --> Within the LDAP-Stack, group synchronisation is done by Extension:LDAPGroups, which again is not dependend on Extension:PluggableAuth. This may change in the future.


# Migration guides

## Extension:SimpleSAMLphp

TBD

## Extension:OpenIDConnect

From https://www.mediawiki.org/w/index.php?title=Topic:Vhct95ynxd8fwf7i&topic_showPostId=vhgso1i82466h5fy#flow-post-vhgso1i82466h5fy

```php
$wgOpenIDConnect_Config['<your issuer>'] = [
	'clientID' => '...',
	// ...
	'global_roles' => [
		'property' => ['realm_access', 'roles'],
		'prefix' => 'global'
	],
	'wiki_roles' => [
		'property' => ['resource_access', 'wiki', 'roles']
	]
];
```

```json
	"typ": "Bearer",
	...
	"realm_access": {
		"roles": [
			"admin",
			"jedi_master"
		]
	},
	"resource_access": {
		"wiki": {
			"roles": [
				"editor",
				"admin"
			]
		},
		"other.client": {
			"roles": [
				"manage-account",
				"manage-account-links",
				"view-profile"
			]
		}
	}
```

Resulting wiki groups: `oidc_globaladmin`, `oidc_globaljedi_master`, `oidc_editor`, `oidc_admin`

In the new configuration, the very same thing can be achived by
```php
$wgPluggableAuth_Config['Log in with OIDC'] = [
	'plugin' => 'OpenIDConnect',
	'data' => [
		// ...
	],
	'groupsyncs' => [
		'mysync' => [
			'type' => 'syncall',
			'filterPrefix' => 'oidc_'
			'groupAttributeName' => [
				[
					'path' => ['realm_access', 'roles']
				],
				[
					'path' => ['resource_access', 'wiki', 'roles']
				]
			]
		]
	]
];
```

**ATTENTION:** Be aware that if you have more than one syncall group sync, the order is important if the 'filterPrefix' overlaps between the two groups. The second group sync may remove groups that have been added by the first group sync.

## Extension:WSOAuth

The implementation contains two things:
1. Exposing a hook `WSOAuthBeforeAutoPopulateGroups` that allowed access to the user object
2. Adding (**no removing**) of groups specified in the configuration variable `wgOAuthAutoPopulateGroups` or the `autopopulategroups` data element

```php
$wgPluggableAuth_Config["WSOAuth Log In"] = [
	'plugin' => 'WSOAuth',
	'data' => [
		// ...
		'autopopulategroups' => [ 'roles' => [ '<group1>', '<group2>', '<etc>' ] ]
	],
	'groupsyncs' => [
		'sync' => [
			'type' => 'syncall',
			'groupattributename' => 'roles'
		]
	]
];
````
TBD
