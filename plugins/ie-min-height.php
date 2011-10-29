<?php
/**
 * Fix min-height in IE 6
 * 
 * Before: 
 *     min-height: 100px;
 * 
 * After:
 *     min-height: 100px;
 *     _height: 100px;
 */

CssCrush::addRuleMacro( 'csscrush_minheight' );

function csscrush_minheight ( CssCrush_Rule $rule ) {
	if ( $rule->propertyCount( 'min-height' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		if ( $declaration->property !== 'min-height' ) {
			continue;
		}
		$new_set[] = $rule->createDeclaration( '_height', $declaration->value );
	}
	$rule->declarations = $new_set;
}