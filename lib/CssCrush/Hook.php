<?php
/**
 *
 *  Access to the execution flow.
 *
 */
namespace CssCrush;

class Hook
{
    // Table of hooks and the functions attached to them.
    public static $register = array();

    public static function add($hook, $fn_name)
    {
        // Bail early is the named hook and callback combination is already loaded.
        if (! isset(self::$register[$hook][$fn_name])) {

            // Store in associative array so no duplicates.
            if (function_exists($fn_name)) {
                self::$register[$hook][$fn_name] = true;
            }
        }
    }

    public static function remove($hook, $fn_name)
    {
        unset(self::$register[$hook][$fn_name]);
    }

    public static function run($hook, $arg_obj = null)
    {
        if (isset(self::$register[$hook])) {
            foreach (self::$register[$hook] as $fn_name => $flag) {
                $fn_name($arg_obj);
            }
        }
    }

    public static function reset()
    {
        self::$register = array();
    }
}
