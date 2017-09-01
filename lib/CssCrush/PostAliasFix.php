<?php
/**
 *
 * Fixes for aliasing to legacy syntaxes.
 *
 */
namespace CssCrush;

class PostAliasFix
{
    public static $functions = [];

    public static function add($alias_type, $key, $callback)
    {
        if ($alias_type === 'function') {
            self::$functions[$key] = $callback;
        }
    }

    public static function remove($alias_type, $key)
    {
        if ($alias_type === 'function') {
            unset(self::$functions[$key]);
        }
    }
}
