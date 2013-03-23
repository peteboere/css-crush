<?php
/**
  *
  * Bootstrap file with autoloader.
  *
  */
function csscrush_autoload ($class) {

    // Only autoload classes with the library prefix.
    if (stripos($class, 'csscrush') !== 0) {
        return;
    }

    // Tolerate some cases of lowercasing.
    $class = str_ireplace('csscrush', 'CssCrush', $class);
    $subpath = implode('/', array_map('ucfirst', explode('_', $class)));

    require_once dirname(__FILE__) . "/lib/$subpath.php";
}

spl_autoload_register('csscrush_autoload');


// Core.php will also be autoloaded with API changes in v2.x.
require_once 'lib/CssCrush/Core.php';
require_once 'lib/functions.php';
