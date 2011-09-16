<?php
/**
 *
 * CSS Crush
 *
 * MIT License (http://www.opensource.org/licenses/mit-license.php)
 * Copyright 2010-2011 Pete Boere
 *
 * Example use:
 *
 * <?php
 *
 * require_once 'CssCrush.php';
 * $global_css = CssCrush::file( '/css/global.css' );
 *
 * ?>
 *
 * <link rel="stylesheet" href="<?php echo $global_css; ?>" />
 *
 */

require_once 'lib/Core.php';
CssCrush::init( dirname( __FILE__ ) );

require_once 'lib/Rule.php';
require_once 'lib/Function.php';
require_once 'lib/Importer.php';
require_once 'lib/Color.php';




