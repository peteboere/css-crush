<?php
/**
 *
 *  Options handling.
 *
 */
class CssCrush_Options
{
    public $data = array();

    public function __construct ($properties)
    {
        if ($properties) {
            foreach ($properties as $key => $value) {
                $this->__set($key, $value);
            }
        }
    }

    public function __set ($name, $value)
    {
        switch ($name) {

            // For legacy debug option, check minify has not been set then
            // flip the value and change property to minify.
            case 'debug':
                if (! array_key_exists('minify', $this->data)) {
                    $name = 'minify';
                    $value = ! $value;
                }
                break;

            // If trace value is truthy set to stubs.
            case 'trace':
                if (! is_array($value)) {
                    $value = $value ? array('stubs') : array();
                }
                break;

            // Resolve a formatter callback name and check it's callable.
            case 'formatter':
                if (isset(CssCrush::$config->formatters[$value])) {
                    $value = CssCrush::$config->formatters[$value];
                }
                if (! is_callable($value)) {
                    $value = null;
                }
                break;

            // Sanitize path options.
            case 'context':
            case 'doc_root':
                if (is_string($value)) {
                    $value = CssCrush_Util::normalizePath($value);
                }
                break;

            // Normalize options that can be passed as strings but internally
            // are used as arrays.
            case 'enable':
            case 'disable':
                $value = (array) $value;
                break;
        }

        $this->data[$name] = $value;
    }

    public function __get ($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    public function __isset ($name)
    {
        return isset($this->data[$name]);
    }

    public function merge (CssCrush_Options $options_instance)
    {
        foreach ($options_instance->data as $key => $value) {
            if (! array_key_exists($key, $this->data)) {
                $this->__set($key, $value);
            }
        }
    }

    public function get ()
    {
        return $this->data;
    }
}
