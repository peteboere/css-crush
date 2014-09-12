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

    public function add($hook, $functionName)
    {
        if (! isset($this->register[$hook][$functionName])) {
            if (function_exists($functionName)) {
                $this->register[$hook][$functionName] = true;
            }
        }
    }

    public function remove($hook, $functionName)
    {
        unset($this->register[$hook][$functionName]);
    }

    public function run($hook, $argObj = null)
    {
        if (isset($this->register[$hook])) {
            foreach (array_keys($this->register[$hook]) as $functionName) {
                $functionName($argObj);
            }
        }
    }

    public function get()
    {
        return $this->register;
    }
}
