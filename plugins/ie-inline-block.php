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

CssCrush_Hook::add( 'rule_postalias', 'csscrush_display_inlineblock' );

function csscrush_display_inlineblock ( CssCrush_Rule $rule ) {
	if ( $rule->propertyCount( 'display' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		$is_display = $declaration->property === 'display';
		if ( 
			$declaration->skip or 
			!$is_display or 
			$is_display and $declaration->value !== 'inline-block' ) {
			continue;
		}
		$new_set[] = $rule->createDeclaration( '*display', 'inline' );
		$new_set[] = $rule->createDeclaration( '*zoom', 1 );
	}
	$rule->declarations = $new_set;
}