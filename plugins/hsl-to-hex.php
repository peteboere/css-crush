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

csscrush_hook::add( 'rule_postalias', 'csscrush_hsl' );

function csscrush_hsl ( csscrush_rule $rule ) {

	foreach ( $rule as &$declaration ) {
		if ( 
			! $declaration->skip &&
			( ! empty( $declaration->functions ) && in_array( 'hsl', $declaration->functions ) )
		) {
			while ( preg_match( '!hsl(___p\d+___)!', $declaration->value, $m ) ) {
				$full_match = $m[0];
				$token = $m[1];
				$hsl = trim( csscrush::$storage->tokens->parens[ $token ], '()' );
				$hsl = array_map( 'trim', explode( ',', $hsl ) );
				$rgb = csscrush_color::cssHslToRgb( $hsl );
				$hex = csscrush_color::rgbToHex( $rgb );
				$declaration->value = str_replace( $full_match, $hex, $declaration->value );
			}
		}
	}
}