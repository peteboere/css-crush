<?php
/**
 *
 * CSS Crush
 * Extensible CSS preprocessor.
 *
 * @version    1.9
 * @link       https://github.com/peteboere/css-crush
 * @license    http://www.opensource.org/licenses/mit-license.php (MIT)
 * @copyright  (c) 2010-2013 Pete Boere
 */

function csscrush_autoload ( $class ) {

    // Only autoload classes with the library prefix.
    if ( stripos( $class, 'csscrush' ) !== 0 ) {
        return;
    }
    $class = ltrim( substr( $class, 8 ), '_' );

    // Tolerate some cases of lowercasing from external use.
    $subpath = implode( '/', array_map( 'ucfirst', explode( '_', $class ) ) );

    require_once dirname( __FILE__ ) . "/lib/$subpath.php";
}

spl_autoload_register( 'csscrush_autoload' );


// Core.php will also be PSR-0 autoloaded with API changes in v2.x
require_once 'lib/Core.php';

CssCrush::init( __FILE__ );
