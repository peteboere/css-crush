<?php
/**
 * 
 *  Access to the execution flow.
 * 
 */
class CssCrush_Hook
{
    // Table of hooks and the functions attached to them.
    static public $register = array();

    static public function add ($hook, $fn_name)
    {
        // Bail early is the named hook and callback combination is already loaded.
        if (! isset(self::$register[$hook][$fn_name])) {

            // Register the hook and callback.
            // Store in associative array so no duplicates.
            if (function_exists($fn_name)) {
                self::$register[$hook][$fn_name] = true;
            }
        }
    }

    static public function remove ($hook, $fn_name)
    {
        unset(self::$register[$hook][$fn_name]);
    }

    static public function run ($hook, $arg_obj = null)
    {
        // Run all callbacks attached to the hook.
        if (isset(self::$register[$hook])) {
            foreach (array_keys(self::$register[$hook]) as $fn_name) {
                call_user_func($fn_name, $arg_obj);
            }
        }
    }
}
