<?php
/**
 *
 *  Plugin API
 *
 */
namespace CssCrush;

class Plugin
{
    public static $plugins = array();

    public static function info()
    {
        $plugin_dirs = Crush::$config->pluginDirs;
        $plugin_data = array();

        foreach ($plugin_dirs as $plugin_dir) {

            foreach (glob("$plugin_dir/*.php") as $path) {
                $name = basename($path, '.php');
                $plugin_data += array($name => Plugin::parseDoc($path));
            }
        }

        return $plugin_data;
    }

    public static function parseDoc($plugin_path)
    {
        $contents = file_get_contents($plugin_path);
        if (preg_match('~/\*\*(.*?)\*/~s', $contents, $m)) {

            $lines = preg_split(Regex::$patt->newline, $m[1]);
            foreach ($lines as &$line) {
                $line = trim(ltrim($line, "* \t"));
            }
            // Remove empty strings and reset indexes.
            $lines = array_values(array_filter($lines, 'strlen'));

            return $lines;
        }

        return false;
    }

    public static function register($plugin_name, $callbacks)
    {
        self::$plugins[$plugin_name] = $callbacks;
    }

    public static function load($plugin_name)
    {
        // Assume the the plugin file is not loaded if null.
        if (! isset(self::$plugins[$plugin_name])) {

            $found = false;

            // Loop plugin_dirs to find the plugin.
            foreach (Crush::$config->pluginDirs as $plugin_dir) {

                $path = "$plugin_dir/$plugin_name.php";
                if (file_exists($path)) {
                    require_once $path;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                notice("Plugin '$plugin_name' not found.");
            }
            elseif (isset(self::$plugins[$plugin_name]['load'])) {
                $plugin_load = self::$plugins[$plugin_name]['load'];
                $plugin_load(Crush::$process);
            }
        }

        return isset(self::$plugins[$plugin_name]) ? self::$plugins[$plugin_name] : null;
    }

    public static function enable($plugin_name)
    {
        $plugin = self::load($plugin_name);

        if (is_callable($plugin['enable'])) {
            $plugin['enable'](Crush::$process);
        }

        return true;
    }

    public static function disable($plugin_name)
    {
        $plugin = isset(self::$plugins[$plugin_name]) ? self::$plugins[$plugin_name] : null;

        if (isset($plugin['disable']) && is_callable($plugin['disable'])) {
            $plugin['disable'](Crush::$process);
        }
    }
}
