<?php
/**
 * Fix min-height in IE 6
 * 
 * @before
 *     min-height: 100px;
 * 
 * @after
 *     min-height: 100px;
 *     _height: 100px;
 */

csscrush_hook::add( 'rule_postalias', 'csscrush_minheight' );

function csscrush_minheight ( csscrush_rule $rule ) {
	if ( $rule->propertyCount( 'min-height' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		if ( 
			$declaration->skip ||
			$declaration->property !== 'min-height' ) {
			continue;
		}
		$new_set[] = new csscrush_declaration( '_height', $declaration->value );
	}
	$rule->declarations = $new_set;
}