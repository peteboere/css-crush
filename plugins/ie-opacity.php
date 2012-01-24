<?php
/**
 * Opacity for IE < 9
 * 
 * @before 
 *     opacity: 0.45;
 * 
 * @after
 *     opacity: 0.45;
 *     -ms-filter: "alpha(opacity=45)";
 *     *filter: alpha(opacity=45);
 *     zoom: 1;
 */

CssCrush_Hook::add( 'rule_postalias', 'csscrush_opacity' );

function csscrush_opacity ( CssCrush_Rule $rule ) {
	if ( $rule->propertyCount( 'opacity' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		if (
			$declaration->skip or
			$declaration->property != 'opacity'
		) {
			continue;
		}

		$opacity = (float) $declaration->value;
		$opacity = round( $opacity * 100 );

		if ( !$rule->propertyCount( 'zoom' ) ) {
			// Filters need hasLayout
			$new_set[] = $rule->createDeclaration( 'zoom', 1 );
		}
		$value = "alpha(opacity=$opacity)";
		$new_set[] = $rule->createDeclaration( '-ms-filter', "\"$value\"" );
		$new_set[] = $rule->createDeclaration( '*filter', $value );
	}
	$rule->declarations = $new_set;
}