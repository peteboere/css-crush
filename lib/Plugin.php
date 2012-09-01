<?php
/**
 * 
 *  Plugin API
 * 
 */

class csscrush_plugin {


	// The required prefix to all plugin function names
	static public $prefix = 'csscrush__';

	// The current loaded plugins
	static protected $associated_hooks = array();


	// Externally associate a hook with the plugin
	static public function registerHook ( $plugin_name, $hook ) {

		self::$associated_hooks[ $plugin_name ] = $hook;
	}


	static public function enable ( $plugin_name ) {

		$plugin_function = self::$prefix . $plugin_name;

		// Require the plugin file if it hasn't been already
		if ( ! function_exists( $plugin_function ) ) {

		 	$path = csscrush::$config->location . "/plugins/$plugin_name.php";

			if ( ! file_exists( $path ) ) {

				trigger_error( __METHOD__ .
					": <b>$plugin_name</b> plugin not found.\n", E_USER_NOTICE );
				return false;
			}
			require_once $path;
		}

		// If the plugin is associated with a hook, we make sure it is hooked
		if ( isset( self::$associated_hooks[ $plugin_name ] ) ) {

			csscrush_hook::add(
				self::$associated_hooks[ $plugin_name ],
				$plugin_function );
		}
		return true;
	}


	static public function disable ( $plugin_name ) {

		// If the plugin is associated with a hook, we 'un-hook' it
		if ( isset( self::$associated_hooks[ $plugin_name ] ) ) {

			csscrush_hook::remove(
				self::$associated_hooks[ $plugin_name ],
				self::$prefix . str_replace( '-', '_', $plugin_name ) );
		}
	}
}
