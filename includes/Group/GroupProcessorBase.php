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
use MediaWiki\User\UserGroupManager;

abstract class GroupProcessorBase extends Base {

	/**
	 * @var UserGroupManager
	 */
	protected $userGroupManager;

	/**
	 * @param UserGroupManager $userGroupManager
	 */
	public function __construct( $userGroupManager ) {
		parent::__construct();
		$this->userGroupManager = $userGroupManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultConfig(): Config {
		return new CaseInsensitiveHashConfig( [
			// If the attribute for groups is not an array but a CSV string,
			// this can be set to the appropriate delimiter (e.g. ',')
			'groupAttributeDelimiter' => null
		] );
	}

	/**
	 * @param string $groupToAdd
	 * @return void
	 */
	protected function addUserToGroup( string $groupToAdd ) {
		$this->logger->debug( "Adding '{$this->user->getName()}' to group '$groupToAdd'" );
		$this->userGroupManager->addUserToGroup( $this->user, $groupToAdd );
	}

	/**
	 * @param string $groupToRemove
	 * @return void
	 */
	protected function removeUserFromGroup( string $groupToRemove ) {
		$this->logger->debug( "Removing '{$this->user->getName()}' from group '$groupToRemove'" );
		$this->userGroupManager->removeUserFromGroup( $this->user, $groupToRemove );
	}
}
