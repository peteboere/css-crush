<?php
/**
 * RGBA fallback
 * Only works with background shorthand IE < 8 
 * (http://css-tricks.com/2151-rgba-browser-support/)
 * 
 * @before
 *     background: rgba(0,0,0,.5);
 * 
 * @after
 *     background: rgb(0,0,0);
 *     background: rgba(0,0,0,.5);
 */

csscrush_hook::add( 'rule_postalias', 'csscrush__rgba_fallback' );


function csscrush__rgba_fallback ( csscrush_rule $rule ) {

	$props = array_keys( $rule->properties );

	// Determine which properties apply
	$rgba_props = array();
	foreach ( $props as $prop ) {
		if ( $prop === 'background' || strpos( $prop, 'color' ) !== false ) {
			$rgba_props[] = $prop;
		}
	}
	if ( empty( $rgba_props ) ) {
		return;
	}

	$new_set = array();
	foreach ( $rule as $declaration ) {
		$is_viable = in_array( $declaration->property, $rgba_props );
		if ( 
			$declaration->skip ||
			! $is_viable || 
			$is_viable && !preg_match( '!^rgba___p\d+___$!', $declaration->value )
		) {
			$new_set[] = $declaration;
			continue;
		}
		// Create rgb value from rgba
		$raw_value = $declaration->getFullValue();
		$raw_value = substr( $raw_value, 5, strlen( $raw_value ) - 1 );
		list( $r, $g, $b, $a ) = explode( ',', $raw_value );
		
		// Add rgb value to the stack, followed by rgba 
		$new_set[] = new csscrush_declaration( $declaration->property, "rgb($r,$g,$b)" );
		$new_set[] = $declaration;
	}
	$rule->declarations = $new_set;
}
