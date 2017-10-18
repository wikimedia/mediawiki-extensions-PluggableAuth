<?php

abstract class PluggableAuth {

	/**
	 * @since 1.0
	 *
	 * @param int &$id
	 * @param string &$username
	 * @param string &$realname
	 * @param string &$email
	 * @param string &$errorMessage
	 */
	abstract public function authenticate( &$id, &$username, &$realname,
		&$email, &$errorMessage );

	/**
	 * @since 1.0
	 *
	 * @param User &$user
	 */
	abstract public function deauthenticate( User &$user );

	/**
	 * @since 1.0
	 *
	 * @param int $id
	 */
	abstract public function saveExtraAttributes( $id );

	private static $instance = null;

	/**
	 * @since 2.0
	 */
	public static function singleton() {
		if ( !is_null( self::$instance ) ) {
			return self::$instance;
		} elseif ( isset( $GLOBALS['wgPluggableAuth_Class'] ) &&
			class_exists( $GLOBALS['wgPluggableAuth_Class'] ) &&
			is_subclass_of( $GLOBALS['wgPluggableAuth_Class'],
				'PluggableAuth' ) ) {
			self::$instance = new $GLOBALS['wgPluggableAuth_Class'];
			return self::$instance;
		}
		wfDebug( 'Could not get authentication plugin instance.' );
		return false;
	}
}
