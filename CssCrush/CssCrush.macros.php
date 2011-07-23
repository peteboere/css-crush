<?php 

################################################################################################
########  IE Legacy

CssCrush::addRuleMacro( 'csscrush_display_inlineblock' );
CssCrush::addRuleMacro( 'csscrush_minheight' );
CssCrush::addRuleMacro( 'csscrush_clip' );
CssCrush::addRuleMacro( 'csscrush_filter' );

########

/**
 * Fix inline-block in IE < 8
 * 
 * Before: 
 *     display: inline-block;
 * 
 * After:
 *     display: inline-block;
 *     display: inline;
 *     zoom: 1;
 */
function csscrush_display_inlineblock ( $rule ) {
	if ( $rule->propertyCount( 'display' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		$is_display = $declaration->property === 'display';
		if ( !$is_display or $is_display and $declaration->value !== 'inline-block' ) {
			continue;
		}
		$new_set[] = $rule->createDeclaration( '*display', 'inline' );
		if ( !$rule->propertyCount( '*zoom' ) ) {
			$new_set[] = $rule->createDeclaration( '*zoom', 1 );
		}
	}
	$rule->declarations = $new_set;
}

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
function csscrush_minheight ( $rule ) {
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

/**
 * Fix clip syntax for IE < 8
 * 
 * Before: 
 *     clip: rect(1px,1px,1px,1px);
 * 
 * After:
 *     clip: rect(1px,1px,1px,1px);
 *     *clip: rect(1px 1px 1px 1px);
 */
function csscrush_clip ( $rule ) {
	// Assume it's been dealt with if the property occurs more than once 
	if ( $rule->propertyCount( 'clip' ) !== 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		if ( $declaration->property !== 'clip' ) {
			continue;
		}
		$new_set[] = $rule->createDeclaration( 
						'*clip', str_replace( ',', ' ', $rule->getDeclarationValue( $declaration ) ) );
	}
	$rule->declarations = $new_set;
}

/**
 * IE filter without the cruft
 */
function csscrush_filter ( $rule ) {
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


################################################################################################
########  Display:box

CssCrush::addRuleMacro( 'csscrush_display_box' );

########

/**
 * Prefixed values on `display:box`
 */
function csscrush_display_box ( $rule ) {
	if ( $rule->propertyCount( 'display' ) < 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$is_display = $declaration->property === 'display';
		if ( !$is_display or ( $is_display and $declaration->value !== 'box' ) ) {
			$new_set[] = $declaration;
			continue;
		}
		$new_set[] = $rule->createDeclaration( 'display', '-webkit-box' );
		$new_set[] = $rule->createDeclaration( 'display', '-moz-box' );
		$new_set[] = $declaration;
	}
	$rule->declarations = $new_set;
}

