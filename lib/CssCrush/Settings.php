<?php
/**
 *
 * Plugin and environment settings.
 *
 */
namespace CssCrush;

class Settings
{
    protected $store = array();

    public function __construct($pairs = array())
    {
        foreach ($pairs as $name => $value) {
            $this->set($name, $value);
        }
    }

    public function set($name, $value)
    {
        $this->store[strtolower($name)] = strtolower($value);
    }

    public function get($name, $fallback = null)
    {
        $value = isset($this->store[$name]) ? $this->store[$name] : null;

        // Backwards compat for variable based settings.
        if (! $value && in_array($name, array('rem-all', 'rem-mode', 'rem-base', 'px2rem-base', 'px2em-base'))) {

            $var_setting = function ($var_name) {
                return isset(Crush::$process->vars[$var_name]) ?
                        Crush::$process->vars[$var_name] : null;
            };
            switch ($name) {
                case 'rem-all':
                    $value = $var_setting('rem__all');
                    break;
                case 'rem-mode':
                    $value = $var_setting('rem__mode');
                    break;
                case 'rem-base':
                    $value = $var_setting('rem__base');
                    break;
                case 'px2rem-base':
                    $value = $var_setting('px2rem__base');
                    break;
                case 'px2em-base':
                    $value = $var_setting('px2em__base');
                    break;
            }
        }

        return isset($value) ? $value : $fallback;
    }
}
