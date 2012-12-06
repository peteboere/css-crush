<?php
/**
 * HSL shim
 * Converts HSL values into hex code that works in all browsers
 * 
 * @before
 *     color: hsl( 100, 50%, 50% )
 * 
 * @after
 *    color: #6abf40
 */

csscrush_plugin::register( 'hsl-to-hex', array(
    'enable' => 'csscrush__enable_hsl_to_hex',
    'disable' => 'csscrush__disable_hsl_to_hex',
));

function csscrush__enable_hsl_to_hex () {
    csscrush_hook::add( 'rule_postalias', 'csscrush__hsl_to_hex' );
}

function csscrush__disable_hsl_to_hex () {
    csscrush_hook::remove( 'rule_postalias', 'csscrush__hsl_to_hex' );
}

function csscrush__hsl_to_hex ( csscrush_rule $rule ) {

    foreach ( $rule as &$declaration ) {

        if ( ! $declaration->skip && isset( $declaration->functions[ 'hsl' ] ) ) {
            while ( preg_match( '!hsl(\?p\d+\?)!', $declaration->value, $m ) ) {
                $token = $m[1];
                $color = new csscrush_color( 'hsl' . csscrush::$process->fetchToken( $token ) );
                csscrush::$process->releaseToken( $token );
                $declaration->value = str_replace( $m[0], $color->getHex(), $declaration->value );
            }
        }
    }
}
