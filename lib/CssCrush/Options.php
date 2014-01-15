<?php
/**
 *
 *  Options handling.
 *
 */
namespace CssCrush;

class Options
{
    protected $computedOptions = array();
    protected $inputOptions = array();

    public static $initialOptions = array(
        'minify' => true,
        'formatter' => null,
        'versioning' => true,
        'boilerplate' => true,
        'vars' => array(),
        'cache' => true,
        'output_file' => null,
        'output_dir' => null,
        'asset_dir' => null,
        'doc_root' => null,
        'vendor_target' => 'all',
        'rewrite_import_urls' => true,
        'enable' => null,
        'disable' => null,
        'settings' => array(),
        'stat_dump' => false,
        'trace' => array(),
        'source_map' => false,
        'newlines' => 'use-platform',
    );

    public function __construct(array $options = array(), Options $defaults = null)
    {
        if ($defaults) {
            $options += $defaults->get();
        }

        foreach ($options + self::$initialOptions as $key => $value) {
            $this->__set($key, $value);
        }
    }

    public function __set($name, $value)
    {
        $this->inputOptions[$name] = $value;

        switch ($name) {

            // Legacy debug option.
            case 'debug':
                if (! array_key_exists('minify', $this->inputOptions)) {
                    $name = 'minify';
                    $value = ! $value;
                }
                break;

            case 'trace':
                if (! is_array($value)) {
                    $value = $value ? array('stubs') : array();
                }
                break;

            case 'formatter':
                if (is_string($value) && isset(Crush::$config->formatters[$value])) {
                    $value = Crush::$config->formatters[$value];
                }
                if (! is_callable($value)) {
                    $value = null;
                }
                break;

            // Path options.
            case 'boilerplate':
                if (is_string($value)) {
                    $value = Util::resolveUserPath($value);
                }
                break;

            case 'stat_dump':
                if (is_string($value)) {
                    $value = Util::resolveUserPath($value, function ($path) {
                        touch($path);
                        return $path;
                    });
                }
                break;

            case 'output_dir':
            case 'asset_dir':
                if (is_string($value)) {
                    $value = Util::resolveUserPath($value, function ($path) use ($name) {
                        if (! @mkdir($path)) {
                            notice("[[CssCrush]] - Could not create directory $path (setting `$name` option).");
                        }
                        else {
                            debug("Created directory $path (setting `$name` option).");
                        }
                        return $path;
                    });
                }
                break;

            // Path options that only accept system paths.
            case 'context':
            case 'doc_root':
                if (is_string($value)) {
                    $value = Util::normalizePath(realpath($value));
                }
                break;

            // Options used internally as arrays.
            case 'enable':
            case 'disable':
                $value = (array) $value;
                break;
        }

        $this->computedOptions[$name] = $value;
    }

    public function __get($name)
    {
        $input_value = $this->inputOptions[$name];

        switch ($name) {
            case 'newlines':
                switch ($input_value) {
                    case 'windows':
                    case 'win':
                        return "\r\n";
                    case 'unix':
                        return "\n";
                    case 'use-platform':
                    default:
                        return PHP_EOL;
                }
                break;

            case 'minify':
                if (isset($this->computedOptions['formatter'])) {
                    return false;
                }
                break;

            case 'formatter':
                if (empty($this->inputOptions['minify'])) {
                    return isset($this->computedOptions['formatter']) ?
                        $this->computedOptions['formatter'] : 'CssCrush\fmtr_block';
                }
        }

        return isset($this->computedOptions[$name]) ? $this->computedOptions[$name] : null;
    }

    public function __isset($name)
    {
        return isset($this->inputOptions[$name]);
    }

    public function get($computed = false)
    {
        return $computed ? $this->computedOptions : $this->inputOptions;
    }
}
