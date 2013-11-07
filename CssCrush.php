<?php
/**
  *
  * Bootstrap file with autoloader.
  *
  */
function csscrush_autoload($class) {

    // We're only autoloading this library.
    if (stripos($class, 'csscrush') !== 0) {
        return;
    }

    // Tolerate some cases of lowercasing.
    $class = str_ireplace('csscrush', 'CssCrush', $class);
    $subpath = implode('/', array_map('ucfirst', explode('\\', $class)));

    require_once __DIR__ . "/lib/$subpath.php";
}

spl_autoload_register('csscrush_autoload');

require_once 'lib/functions.php';
