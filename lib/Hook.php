<?php
/**
 * 
 *  Access to the execution flow
 * 
 */

class csscrush_hook {

	// Table of hooks and the functions attached to them
	static public $register = array();

	static public function add ( $hook, $fn_name ) {

		// Bail early is the named hook and callback combination is already loaded
		if ( isset( self::$register[ $hook ][ $fn_name ] ) ) {
			return;
		}

		// Register the hook and callback.
		// Store in associative array so no duplicates
		if ( function_exists( $fn_name ) ) {

			self::$register[ $hook ][ $fn_name ] = true;

			// If the callback is a plugin register the hook that it uses
			if ( strpos( $fn_name, csscrush_plugin::$prefix ) === 0 ) {

				$plugin_name = str_replace( '_', '-',
					substr( $fn_name, strlen( csscrush_plugin::$prefix ) ) );
				csscrush_plugin::registerHook( $plugin_name, $hook );
			}
		}
	}

	static public function remove ( $hook, $fn_name ) {
		unset( self::$register[ $hook ][ $fn_name ] );
	}

	static public function run ( $hook, $arg_obj ) {

		// Run all callbacks attached to the hook
		if ( ! isset( self::$register[ $hook ] ) ) {
			return;
		}
		foreach ( array_keys( self::$register[ $hook ] ) as $fn_name ) {
			call_user_func( $fn_name, $arg_obj );
		}
	}
}
