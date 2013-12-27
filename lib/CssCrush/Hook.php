<?php
/**
 *
 * Runtime hook management.
 *
 */
namespace CssCrush;

class Hook
{
    public static $register = array();

    public static function add($hook, $fn_name)
    {
        if (! isset(self::$register[$hook][$fn_name])) {
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
}
