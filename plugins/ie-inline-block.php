<?php
/**
 * Simulate inline-block in IE < 8
 * 
 * @before 
 *     display: inline-block;
 * 
 * @after
 *     display: inline-block;
 *     *display: inline;
 *     *zoom: 1;
 */

csscrush_hook::add( 'rule_postalias', 'csscrush__ie_inline_block' );

function csscrush__ie_inline_block ( csscrush_rule $rule ) {

	if ( $rule->propertyCount( 'display' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		$is_display = $declaration->property === 'display';
		if ( 
			$declaration->skip || 
			! $is_display || 
			$is_display && $declaration->value !== 'inline-block' ) {
			continue;
		}
		$new_set[] = new csscrush_declaration( '*display', 'inline' );
		$new_set[] = new csscrush_declaration( '*zoom', 1 );
	}
	$rule->declarations = $new_set;
}
