<?php
/**
 * 
 *  Plugin API
 * 
 */
class CssCrush_Plugin
{
    static protected $plugins = array();

    static public function info ()
    {
        $plugin_dirs = CssCrush::$config->plugin_dirs;
        $plugin_data = array();

        foreach ($plugin_dirs as $plugin_dir) {

            foreach (glob("$plugin_dir/*.php") as $path) {
                $name = basename($path, '.php');
                $plugin_data += array($name => CssCrush_Plugin::parseDoc($path));
            }
        }

        return $plugin_data;
    }

    static public function parseDoc ($plugin_path)
    {
        $contents = file_get_contents($plugin_path);
        if (preg_match('~/\*\*(.*?)\*/~s', $contents, $m)) {

            $lines = preg_split(CssCrush_Regex::$patt->newline, $m[1]);
            foreach ($lines as &$line) {
                $line = trim(ltrim($line, "* \t"));
            }
            // Remove empty strings and reset indexes.
            $lines = array_values(array_filter($lines, 'strlen'));

            return $lines;
        }

        return false;
    }

    static public function register ($plugin_name, $callbacks)
    {
        self::$plugins[$plugin_name] = $callbacks;
    }

    static public function load ($plugin_name)
    {
        // Assume the the plugin file is not loaded if null.
        if (! isset(self::$plugins[$plugin_name])) {

            $found = false;

            // Loop plugin_dirs to find the plugin.
            foreach (CssCrush::$config->plugin_dirs as $plugin_dir) {

                $path = "$plugin_dir/$plugin_name.php";
                if (file_exists($path)) {
                    require_once $path;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                trigger_error(__METHOD__ .
                        ": <b>$plugin_name</b> plugin not found.\n", E_USER_NOTICE);
            }
        }

        return isset(self::$plugins[$plugin_name]) ? self::$plugins[$plugin_name] : null;
    }

    static public function enable ($plugin_name)
    {
        $plugin = self::load($plugin_name);

        if (is_callable($plugin['enable'])) {
            $plugin['enable']();
        }

        return true;
    }

    static public function disable ($plugin_name)
    {
        $plugin = isset(self::$plugins[$plugin_name]) ? self::$plugins[$plugin_name] : null;

        if (is_callable($plugin['disable'])) {
            $plugin['disable']();
        }
    }
}
