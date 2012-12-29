<?php
/**
 * 
 *  Plugin API
 * 
 */
class CssCrush_Plugin
{
    static protected $plugins = array();

    static public function show ()
    {
        return self::$plugins;
    }

    static public function register ( $plugin_name, $callbacks )
    {
        self::$plugins[ $plugin_name ] = $callbacks;
    }

    static public function load ( $plugin_name )
    {
        // Assume the the plugin file is not loaded if null.
        if ( ! isset( self::$plugins[ $plugin_name ] ) ) {

            $path = CssCrush::$config->location . "/plugins/$plugin_name.php";

            if ( ! file_exists( $path ) ) {

                trigger_error( __METHOD__ .
                    ": <b>$plugin_name</b> plugin not found.\n", E_USER_NOTICE );
            }
            else {
                require_once $path;
            }
        }
        return isset( self::$plugins[ $plugin_name ] ) ? self::$plugins[ $plugin_name ] : null;;
    }

    static public function enable ( $plugin_name )
    {
        $plugin = self::load( $plugin_name );

        if ( is_callable( $plugin[ 'enable' ] ) ) {
            $plugin[ 'enable' ]();
        }

        return true;
    }

    static public function disable ( $plugin_name )
    {
        $plugin = isset( self::$plugins[ $plugin_name ] ) ? self::$plugins[ $plugin_name ] : null;

        if ( is_callable( $plugin[ 'disable' ] ) ) {
            $plugin[ 'disable' ]();
        }
    }
}
