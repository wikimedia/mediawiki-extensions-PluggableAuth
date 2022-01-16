<?php

namespace MediaWiki\Extension\PluggableAuth;

use User;

abstract class PluggableAuth {

	/**
	 * @param int|null &$id The user's user ID
	 * @param string|null &$username The user's username
	 * @param string|null &$realname The user's real name
	 * @param string|null &$email The user's email address
	 * @param string|null &$errorMessage Returns a descriptive message if there's an error
	 * @return bool true if the user has been authenticated and false otherwise
	 * @since 1.0
	 *
	 */
	abstract public function authenticate(
		?int &$id,
		?string &$username,
		?string &$realname,
		?string &$email,
		?string &$errorMessage
	): bool;

	/**
	 * @since 1.0
	 *
	 * @param User &$user The user
	 */
	abstract public function deauthenticate( User &$user ): void;

	/**
	 * @since 1.0
	 *
	 * @param int $id The user's user ID
	 */
	abstract public function saveExtraAttributes( int $id ): void;

	private static $instance = null;

	/**
	 * @since 2.0
	 * @return PluggableAuth|false a PluggableAuth object
	 */
	public static function singleton() {
		wfDebugLog( 'PluggableAuth', 'Getting PluggableAuth singleton' );
		wfDebugLog(
			'PluggableAuth',
			'Class name: ' . ( $GLOBALS['wgPluggableAuth_Class'] ?? 'unset' )
		);
		if ( self::$instance !== null ) {
			wfDebugLog( 'PluggableAuth', 'Singleton already exists' );
			return self::$instance;
		} elseif ( isset( $GLOBALS['wgPluggableAuth_Class'] ) &&
			class_exists( $GLOBALS['wgPluggableAuth_Class'] ) &&
			is_subclass_of( $GLOBALS['wgPluggableAuth_Class'], self::class )
		) {
			self::$instance = new $GLOBALS['wgPluggableAuth_Class'];
			return self::$instance;
		}
		wfDebugLog( 'PluggableAuth', 'Could not get authentication plugin instance.' );
		return false;
	}
}
