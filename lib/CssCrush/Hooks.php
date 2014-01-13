<?php
/**
 *
 * Runtime hook management.
 *
 */
namespace CssCrush;

class Hooks
{
    protected $register = array();

    public function add($hook, $fn_name)
    {
        if (! isset($this->register[$hook][$fn_name])) {
            if (function_exists($fn_name)) {
                $this->register[$hook][$fn_name] = true;
            }
        }
    }

    public function remove($hook, $fn_name)
    {
        unset($this->register[$hook][$fn_name]);
    }

    public function run($hook, $arg_obj = null)
    {
        if (isset($this->register[$hook])) {
            foreach ($this->register[$hook] as $fn_name => $flag) {
                $fn_name($arg_obj);
            }
        }
    }

    public function get()
    {
        return $this->register;
    }
}
