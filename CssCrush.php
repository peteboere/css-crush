<?php
/**
 *
 * CSS Crush
 * Extensible CSS preprocessor
 * 
 * @version    1.4.1
 * @license    http://www.opensource.org/licenses/mit-license.php (MIT)
 * @copyright  Copyright 2010-2012 Pete Boere
 * 
 * 
 * <?php
 *
 * // Basic usage
 * require_once 'CssCrush.php';
 * $global_css = CssCrush::file( '/css/global.css' );
 *
 * ?>
 *
 * <link rel="stylesheet" href="<?php echo $global_css; ?>" />
 *
 */

require_once 'lib/Util.php';
require_once 'lib/Core.php';
CssCrush::init( dirname( __FILE__ ) );

require_once 'lib/Rule.php';

require_once 'lib/Function.php';
CssCrush_Function::init();

require_once 'lib/Importer.php';
require_once 'lib/Color.php';
require_once 'lib/Hook.php';




