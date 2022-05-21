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

namespace MediaWiki\Extension\PluggableAuth\Group;

use Config;
use MediaWiki\Extension\PluggableAuth\CaseInsensitiveHashConfig;
use MultiConfig;

class SyncAllGroups extends GroupProcessorBase {

	/**
	 * @var array
	 */
	private $currentGroups = [];

	/**
	 * @var array
	 */
	private $locallyManagedGroups = [];

	/**
	 * @inheritDoc
	 */
	protected function getDefaultConfig(): Config {
		return new MultiConfig( [
			new CaseInsensitiveHashConfig( [
				'groupAttributeName' => 'groups',
				'locallyManaged' => [ 'sysop' ],
				'groupNameModificationCallback' => null,
				'filterPrefix' => '',
				'onlySyncExisting' => false
			] ),
			parent::getDefaultConfig()
		] );
	}

	/**
	 * Reads out the attribute that holds the user groups and applies them to the local user object
	 */
	public function doRun(): void {
		$locallyManagedGroups = $this->config->get( 'locallyManaged' );
		$this->locallyManagedGroups = $this->normalizeGroupNames( $locallyManagedGroups );
		$this->currentGroups = $this->userGroupManager->getUserGroups( $this->user );

		$this->extractRawGroupNamesFromAttribute();
		$this->postprocessGroupNamesFromAttribute();
		$groupsToAdd = $this->getGroupsToAdd();
		$groupsToRemove = $this->getGroupsToRemove();

		foreach ( $groupsToAdd as $groupToAdd ) {
			$this->addUserToGroup( $groupToAdd );
		}
		foreach ( $groupsToRemove as $groupToRemove ) {
			$this->removeUserFromGroup( $groupToRemove );
		}
	}

	/**
	 * @var array
	 */
	private $rawGroupNames = [];

	private function extractRawGroupNamesFromAttribute() {
		$groupAttributes = $this->config->get( 'groupAttributeName' );
		if ( !is_array( $groupAttributes ) ) {
			$groupAttributes = [
				[
					'path' => [
						$groupAttributes
					]
				]
			];
		}
		$groupNames = [];
		foreach ( $groupAttributes as $groupAttribute ) {
			if ( isset( $groupAttribute['path'] ) ) {
				foreach (
					$this->getNestedPropertyAsArray( $this->attributes, $groupAttribute['path'] ) as $groupName
				) {
					$groupNames[] = ( $groupAttribute['prefix'] ?? '' ) . $groupName;
				}
			}
		}
		$groupNames = array_unique( $groupNames );

		$delimiter = $this->config->get( 'groupAttributeDelimiter' );
		foreach ( $groupNames as $groupName ) {
			if ( $delimiter !== null ) {
				$this->rawGroupNames = array_merge(
					$this->rawGroupNames,
					explode( $delimiter, $groupName )
				);
			} else {
				$this->rawGroupNames[] = $groupName;
			}
		}
	}

	/**
	 * Walks the nested array structure and returns the value of the last property
	 * See https://github.com/wikimedia/mediawiki-extensions-OpenIDConnect/blob/6.2/includes/OpenIDConnectUserGroupManager.php#L137-L156
	 * @param stdClass|array|null $obj
	 * @param array $properties
	 * @return array
	 */
	private function getNestedPropertyAsArray( $obj, array $properties ): array {
		if ( $obj === null ) {
			return [];
		}
		while ( !empty( $properties ) ) {
			$property = array_shift( $properties );
			if ( is_array( $obj ) ) {
				if ( !array_key_exists( $property, $obj ) ) {
					return [];
				}
				$obj = $obj[$property];
			} else {
				if ( !property_exists( $obj, $property ) ) {
					return [];
				}
				$obj = $obj->$property;
			}
		}
		return is_array( $obj ) ? $obj : [ $obj ];
	}

	/**
	 * @param array $groupNames
	 * @return array
	 */
	private function normalizeGroupNames( $groupNames ) {
		return array_map( 'trim', $groupNames );
	}

	/**
	 * @var array
	 */
	private $groupNames = [];

	private function postprocessGroupNamesFromAttribute() {
		$this->groupNames = $this->normalizeGroupNames( $this->rawGroupNames );

		$filterPrefix = trim( $this->config->get( 'filterPrefix' ) );
		$this->groupNames = array_map(
			static function ( $groupName ) use ( $filterPrefix ) {
				return $filterPrefix . $groupName;
			},
			$this->groupNames
		);

		$groupModificationCallback = $this->config->get( 'groupNameModificationCallback' );
		if ( is_callable( $groupModificationCallback ) ) {
			$this->groupNames = array_map( $groupModificationCallback, $this->groupNames );
		}
	}

	/**
	 * @return array
	 */
	private function getGroupsToAdd() {
		$groupsToAdd = array_diff( $this->groupNames, $this->currentGroups );
		$groupsToAdd = array_diff( $groupsToAdd, $this->locallyManagedGroups );
		return $groupsToAdd;
	}

	/**
	 * @return array
	 */
	private function getGroupsToRemove() {
		$groupsToRemove = array_diff( $this->currentGroups, $this->groupNames );
		$groupsToRemove = array_diff( $groupsToRemove, $this->locallyManagedGroups );
		// Filter out all that are not starting with the prefix
		$filterPrefix = trim( $this->config->get( 'filterPrefix' ) );
		if ( $filterPrefix === '' ) {
			return $groupsToRemove;
		}
		$groupsToRemove = array_filter( $groupsToRemove, static function ( $groupName ) use ( $filterPrefix ) {
			return strpos( $groupName, $filterPrefix ) === 0;
		} );
		return $groupsToRemove;
	}

	/**
	 * @inheritDoc
	 */
	protected function addUserToGroup( string $groupToAdd ) {
		if ( $this->skipGroup( $groupToAdd ) ) {
			return;
		}
		parent::addUserToGroup( $groupToAdd );
	}

	/**
	 * @inheritDoc
	 */
	protected function removeUserFromGroup( string $groupToRemove ) {
		if ( $this->skipGroup( $groupToRemove ) ) {
			return;
		}
		parent::removeUserFromGroup( $groupToRemove );
	}

	/**
	 * @param string $group
	 * @return bool
	 */
	private function skipGroup( string $group ): bool {
		if ( !$this->config->get( 'onlySyncExisting' ) ) {
			return false;
		}
		$this->initExistingGroups();
		if ( !in_array( $group, $this->existingGroups ) ) {
			$this->logger->debug( "Skipping group '$group' for '{$this->user->getName()}'" );
			return true;
		}
		return false;
	}

	/**
	 * @var array
	 */
	private $existingGroups = [];

	/**
	 * @var bool
	 */
	private $existingGroupsInitialized = false;

	private function initExistingGroups() {
		if ( $this->existingGroupsInitialized ) {
			return;
		}
		$this->existingGroups = $this->userGroupManager->listAllGroups();
		$this->existingGroupsInitialized = true;
	}
}
