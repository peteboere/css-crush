<?php
/**
  *
  * Functions for procedural style API.
  *
  */
function csscrush_file ($file, $options = null) {
    return CssCrush::file($file, $options);
}

function csscrush_tag ($file, $options = null, $attributes = array()) {
    return CssCrush::tag($file, $options, $attributes);
}

function csscrush_inline ($file, $options = null, $attributes = array()) {
    return CssCrush::inline($file, $options, $attributes);
}

function csscrush_string ($string, $options = null) {
    return CssCrush::string($string, $options);
}

function csscrush_globalvars ($vars) {
    return CssCrush::globalVars($vars);
}

function csscrush_clearcache ($dir = '') {
    return CssCrush::clearcache($dir);
}

function csscrush_stat ($name = null) {
    return CssCrush::stat($name);
}

function csscrush_version () {
    return CssCrush::$config->version;
}

/**
 * Set default options and config settings.
 *
 * @param string $object_name  Name of object you want to modify: 'config' or 'options'.
 * @param mixed $modifier  Assoc array of keys and values to set, or callable which is passed the object.
 */
function csscrush_set ($object_name, $modifier) {

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
