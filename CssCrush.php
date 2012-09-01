<?php
/**
 *
 * CSS Crush
 * Extensible CSS preprocessor
 *
 * @version    1.7
 * @link       https://github.com/peteboere/css-crush
 * @license    http://www.opensource.org/licenses/mit-license.php (MIT)
 * @copyright  (c) 2010-2012 Pete Boere
 */

require_once 'lib/Util.php';
require_once 'lib/IO.php';
require_once 'lib/Core.php';
require_once 'lib/Rule.php';
require_once 'lib/Mixin.php';
require_once 'lib/Function.php';
require_once 'lib/Importer.php';
require_once 'lib/Color.php';
require_once 'lib/Regex.php';
require_once 'lib/Hook.php';
require_once 'lib/Plugin.php';

csscrush::init( __FILE__ );
