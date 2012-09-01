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

csscrush_hook::add( 'rule_postalias', 'csscrush__ie_opacity' );

function csscrush__ie_opacity ( csscrush_rule $rule ) {

	if ( $rule->propertyCount( 'opacity' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		if (
			$declaration->skip ||
			$declaration->property != 'opacity'
		) {
			continue;
		}

		$opacity = (float) $declaration->value;
		$opacity = round( $opacity * 100 );

		if ( ! $rule->propertyCount( 'zoom' ) ) {
			// Filters need hasLayout
			$new_set[] = new csscrush_declaration( 'zoom', 1 );
		}
		$value = "alpha(opacity=$opacity)";
		$new_set[] = new csscrush_declaration( '-ms-filter', "\"$value\"" );
		$new_set[] = new csscrush_declaration( '*filter', $value );
	}
	$rule->declarations = $new_set;
}
