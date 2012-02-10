<?php
/**
 * 
 *  Access to the execution flow for plugins
 * 
 */

class csscrush_hook {
	
	static public $record = array();
	
	static public function add ( $hook, $fn_name ) {
		// Store in associative array so no duplicates
		if ( function_exists( $fn_name ) ) {
			self::$record[ $hook ][ $fn_name ] = true;
		}
	}

	static public function run ( $hook, $arg_obj ) {
		// Run all callbacks attached to the hook
		if ( !isset( self::$record[ $hook ] ) ) {
			return;
		}
		foreach ( array_keys( self::$record[ $hook ] ) as $fn_name ) {
			call_user_func( $fn_name, $arg_obj );
		}
	}
}