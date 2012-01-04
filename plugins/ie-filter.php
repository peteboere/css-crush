<?php
/**
 * IE filter without the cruft
 * 
 * Using ms vendor prefix outputs expanded and quoted syntax for IE > 7
 * Outputs '*' escaped filter property for IE < 8
 * Adds hasLayout via zoom property (required by filter effects)
 * 
 * 
 * @before
 *     -ms-filter: alpha(opacity=50), blur(strength=10);
 * 
 * @after
 *     -ms-filter: "alpha(opacity=50), progid:DXImageTransform.Microsoft.Blur(strength=10)";
 *     *filter: alpha(opacity=50), progid:DXImageTransform.Microsoft.Blur(strength=10);
 *     zoom: 1;
 */

CssCrush_Hook::add( 'rule_postalias', 'csscrush_filter' );

function csscrush_filter ( CssCrush_Rule $rule ) {
	if ( $rule->propertyCount( '-ms-filter' ) < 1 ) {
		return;
	}
	$filter_prefix = 'progid:DXImageTransform.Microsoft.';
	$new_set = array();
	foreach ( $rule as $declaration ) {
		if (
			$declaration->skip or
			$declaration->property !== '-ms-filter' 
		) {
			$new_set[] = $declaration;
			continue;
		}
		$list = array_map( 'trim', explode( ',', $declaration->value ) );
		foreach ( $list as &$item ) {
			if ( 
				strpos( $item, $filter_prefix ) !== 0 and 
				strpos( $item, 'alpha' ) !== 0 // Shortcut syntax permissable on alpha
			) {
				$item = $filter_prefix . ucfirst( $item );
			}
		}
		$declaration->value = implode( ',', $list );
		if ( !$rule->propertyCount( 'zoom' ) ) {
			// Filters need hasLayout
			$new_set[] = $rule->createDeclaration( 'zoom', 1 );
		}
		// Quoted version for -ms-filter IE >= 8
		$new_set[] = $rule->createDeclaration( '-ms-filter', "\"$declaration->value\"" );
		// Star escaped property for IE < 8
		$new_set[] = $rule->createDeclaration( '*filter', $declaration->value );
	}
	$rule->declarations = $new_set;
}