<?php
/**
 * IE filter without the cruft
 */

CssCrush::addRuleMacro( 'csscrush_filter' );

function csscrush_filter ( CssCrush_Rule $rule ) {
	if ( $rule->propertyCount( 'filter' ) < 1 ) {
		return;
	}
	$filter_prefix = 'progid:DXImageTransform.Microsoft.';
	$new_set = array();
	foreach ( $rule as $declaration ) {
		if ( $declaration->property !== 'filter' ) {
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
			// Filters need hazLayout
			$new_set[] = $rule->createDeclaration( 'zoom', 1 );
		}
		$new_set[] = $declaration;
		$new_set[] = $rule->createDeclaration( '-ms-filter', "\"$declaration->value\"" );
	}
	$rule->declarations = $new_set;
}