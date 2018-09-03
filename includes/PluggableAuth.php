<?php

abstract class PluggableAuth {

	/**
	 * @since 1.0
	 *
	 * @param int &$id The user's user ID
	 * @param string &$username The user's user name
	 * @param string &$realname The user's real name
	 * @param string &$email The user's email address
	 * @param string &$errorMessage Returns a descritive message if
	 *                              there's an error
	 */
	abstract public function authenticate( &$id, &$username, &$realname,
		&$email, &$errorMessage );

	/**
	 * @since 1.0
	 *
	 * @param User &$user The user
	 */
	abstract public function deauthenticate( User &$user );

	/**
	 * @since 1.0
	 *
	 * @param int $id The user's user ID
	 */
	abstract public function saveExtraAttributes( $id );

	private static $instance = null;

	/**
	 * @since 2.0
	 * @return PluggableAuth a PluggableAuth object
	 */
	public static function singleton() {
		wfDebugLog( 'PluggableAuth', 'Getting PluggableAuth singleton' );
		wfDebugLog( 'PluggableAuth', 'Class name: ' . $GLOBALS['wgPluggableAuth_Class'] );
		if ( !is_null( self::$instance ) ) {
			wfDebugLog( 'PluggableAuth', 'Singleton already exists' );
			return self::$instance;
		} elseif ( isset( $GLOBALS['wgPluggableAuth_Class'] ) &&
			class_exists( $GLOBALS['wgPluggableAuth_Class'] ) &&
			is_subclass_of( $GLOBALS['wgPluggableAuth_Class'],
				'PluggableAuth' ) ) {
			self::$instance = new $GLOBALS['wgPluggableAuth_Class'];
			return self::$instance;
		}
		wfDebugLog( 'PluggableAuth', 'Could not get authentication plugin instance.' );
		return false;
	}
}
