<?php
/**
  *
  * High level API.
  *
  */
use CssCrush\CssCrush;

function csscrush_file($file, $options = null) {
    return CssCrush::file($file, $options);
}

function csscrush_tag($file, $options = null, $attributes = array()) {
    return CssCrush::tag($file, $options, $attributes);
}

function csscrush_inline($file, $options = null, $attributes = array()) {
    return CssCrush::inline($file, $options, $attributes);
}

function csscrush_string($string, $options = null) {
    return CssCrush::string($string, $options);
}

function csscrush_stat() {
    return CssCrush::stat();
}

function csscrush_version($use_git = false) {
    if ($use_git && $version = \CssCrush\Version::gitDescribe()) {
        return $version;
    }
    return CssCrush::$config->version;
}

/**
 * Set default options and config settings.
 *
 * @param string $object_name  Name of object you want to modify: 'config' or 'options'.
 * @param mixed $modifier  Assoc array of keys and values to set, or callable which is passed the object.
 */
function csscrush_set($object_name, $modifier) {

    if (in_array($object_name, array('options', 'config'))) {

        $pointer = $object_name === 'options' ?
            CssCrush::$config->options : CssCrush::$config;

        if (is_callable($modifier)) {
            call_user_func($modifier, $pointer);
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
 * @param string $object_name  Name of object you want to modify: 'config' or 'options'.
 * @param mixed $property  The property name to retrieve.
 */
function csscrush_get($object_name, $property = null) {

    if (in_array($object_name, array('options', 'config'))) {

        $pointer = $object_name === 'options' ?
            CssCrush::$config->options : CssCrush::$config;

        if (! isset($property)) {

            return $pointer;
        }
        else {

            return isset($pointer->{$property}) ? $pointer->{$property} : null;
        }
    }

    return null;
}
