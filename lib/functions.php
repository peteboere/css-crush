<?php
/**
  *
  * Public API.
  *
  */
use CssCrush\Crush;
class_alias('CssCrush\Crush', 'CssCrush\CssCrush');


/**
 * Process CSS file and return a new compiled file.
 *
 * @see docs/api/functions.md
 */
function csscrush_file($file, $options = array()) {

    try {
        Crush::$process = new CssCrush\Process($options, array('type' => 'file', 'data' => $file));
    }
    catch (\Exception $e) {
        CssCrush\warning($e->getMessage());

        return '';
    }

    return new CssCrush\File(Crush::$process);
}


/**
 * Process CSS file and return an HTML link tag with populated href.
 *
 * @see docs/api/functions.md
 */
function csscrush_tag($file, $options = array(), $tag_attributes = array()) {

    $file = csscrush_file($file, $options);
    if ($file && $file->url) {
        $tag_attributes['href'] = $file->url;
        $tag_attributes += array(
            'rel' => 'stylesheet',
            'media' => 'all',
        );
        $attrs = CssCrush\Util::htmlAttributes($tag_attributes, array('rel', 'href', 'media'));

        return "<link$attrs />\n";
    }
}


/**
 * Process CSS file and return CSS as text wrapped in html style tags.
 *
 * @see docs/api/functions.md
 */
function csscrush_inline($file, $options = array(), $tag_attributes = array()) {

    if (! is_array($options)) {
        $options = array();
    }
    if (! isset($options['boilerplate'])) {
        $options['boilerplate'] = false;
    }

    $file = csscrush_file($file, $options);
    if ($file && $file->path) {
        $tagOpen = '';
        $tagClose = '';
        if (is_array($tag_attributes)) {
            $attrs = CssCrush\Util::htmlAttributes($tag_attributes);
            $tagOpen = "<style$attrs>";
            $tagClose = '</style>';
        }
        return $tagOpen . file_get_contents($file->path) . $tagClose . "\n";
    }
}


/**
 * Compile a raw string of CSS string and return it.
 *
 * @see docs/api/functions.md
 */
function csscrush_string($string, $options = array()) {

    if (! isset($options['boilerplate'])) {
        $options['boilerplate'] = false;
    }

    Crush::$process = new CssCrush\Process($options, array('type' => 'filter', 'data' => $string));

    return Crush::$process->compile()->__toString();
}


/**
 * Set default options and config settings.
 *
 * @see docs/api/functions.md
 */
function csscrush_set($object_name, $modifier) {

    if (in_array($object_name, array('options', 'config'))) {

        $pointer = $object_name === 'options' ? Crush::$config->options : Crush::$config;

        if (is_callable($modifier)) {
            $modifier($pointer);
        }
        elseif (is_array($modifier)) {
            foreach ($modifier as $key => $value) {
                $pointer->{$key} = $value;
            }
        }
    }
}


/**
 * Get default options and config settings.
 *
 * @see docs/api/functions.md
 */
function csscrush_get($object_name, $property = null) {

    if (in_array($object_name, array('options', 'config'))) {

        $pointer = $object_name === 'options' ? Crush::$config->options : Crush::$config;

        if (! isset($property)) {
            return $pointer;
        }
        else {
            return isset($pointer->{$property}) ? $pointer->{$property} : null;
        }
    }
    return null;
}


/**
 * Add custom CSS functions.
 *
 * @see docs/api/functions.md
 */
function csscrush_add_function($function_name = null, $callback = null) {

    static $stack = array();

    if (! func_num_args()) {
        return $stack;
    }

    if (! $function_name) {
        $stack = array();
        return;
    }

    $function_name = strtolower($function_name);
    if (! $callback) {
        if (isset($stack[$function_name])) {
            unset($stack[$function_name]);
        }
    }
    else {
        $stack[$function_name] = array(
            'callback' => $callback,
            'parse_args' => true,
        );
    }
}


/**
 * Get version information.
 *
 * @see docs/api/functions.md
 */
function csscrush_version($use_git = false) {

    if ($use_git && $version = \CssCrush\Version::gitDescribe()) {
        return $version;
    }
    return Crush::$config->version;
}


/**
 * Get stats from most recent compile.
 *
 * @see docs/api/functions.md
 */
function csscrush_stat() {

    $process = Crush::$process;
    $stats = $process->stat;

    // Get logged errors as late as possible.
    $stats['errors'] = $process->errors;
    $stats['warnings'] = $process->warnings;
    $stats += array('compile_time' => 0);

    return $stats;
}
