<?php
/**
  *
  * Bootstrap file with autoloader.
  *
  */
spl_autoload_register(function ($class) {

    if (stripos($class, 'csscrush') !== 0) {
        return;
    }

    $class = str_ireplace('csscrush', 'CssCrush', $class);
    $subpath = implode('/', array_map('ucfirst', explode('\\', $class)));

    require_once __DIR__ . "/lib/$subpath.php";
});

require_once 'lib/functions.php';
