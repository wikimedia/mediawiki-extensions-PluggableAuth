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

class MapGroups extends GroupProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getDefaultConfig(): Config {
		return new MultiConfig( [
			new CaseInsensitiveHashConfig( [
				'map' => [],
				'addOnlyGroups' => []
			] ),
			parent::getDefaultConfig()
		] );
	}

	/**
	 *
	 * @var array
	 */
	protected $groupMap = [];

	/**
	 * Reads out the attribute that holds the user groups and applies them to the local user object
	 */
	public function doRun(): void {
		$this->initGroupMap();

		$groupListDelimiter = $this->config->get( 'groupAttributeDelimiter' );
		$addOnlyGroups = $this->config->get( 'addOnlyGroups' );

		foreach ( $this->groupMap as $group => $rules ) {
			$group = trim( $group );
			$groupAdded = false;

			foreach ( $rules as $attrName => $needles ) {
				if ( $groupAdded == true ) {
					break;
				} elseif ( !isset( $this->attributes[$attrName] ) ) {
					$this->removeUserFromGroup( $group );
					continue;
				}
				$pluginProvidedGroups = $this->attributes[$attrName];
				if ( $groupListDelimiter !== null ) {
					$pluginProvidedGroups = explode( $groupListDelimiter, $pluginProvidedGroups[0] );
				}
				$pluginProvidedGroups = $this->normalizePluginProvidedGroups( $pluginProvidedGroups );
				if ( !is_array( $needles ) ) {
					$needles = [ $needles ];
				}
				foreach ( $needles as $needle ) {
					if ( is_callable( $needle ) ) {
						$foundMatch = $needle( $pluginProvidedGroups );
					} else {
						$needle = $this->normalizeNeedle( $needle );
						$foundMatch = in_array( $needle, $pluginProvidedGroups );
					}
					if ( $foundMatch ) {
						$this->addUserToGroup( $group );
						$groupAdded = true;
						break;
					} else {
						if ( in_array( $group, $addOnlyGroups ) ) {
							continue;
						}
						$this->removeUserFromGroup( $group );
					}
				}
			}
		}
	}

	/**
	 * @param string $needle
	 * @return string
	 */
	private function normalizeNeedle( $needle ) {
		return trim( strtolower( $needle ) );
	}

	/**
	 * @param array $pluginProvidedGroups
	 * @return array
	 */
	private function normalizePluginProvidedGroups( $pluginProvidedGroups ) {
		$normalizedPluginProvidedGroups = [];
		foreach ( $pluginProvidedGroups as $pluginProvidedGroup ) {
			$normalizedPluginProvidedGroups[] = trim( strtolower( $pluginProvidedGroup ) );
		}
		return $normalizedPluginProvidedGroups;
	}

	private function initGroupMap() {
		$this->groupMap = [];
		if ( $this->config->has( 'map' ) ) {
			$this->groupMap = $this->config->get( 'map' );
		}

		# group map: [mediawiki group][plugin attribute][plugin attribute value]
		if ( !is_array( $this->groupMap ) ) {
			$this->logger->debug( '`map` is not an array' );
			$this->groupMap = [];
		}
	}
}
