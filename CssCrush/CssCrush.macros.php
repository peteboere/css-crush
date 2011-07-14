<?php 

################################################################################################
########  IE Legacy

CssCrush::addRuleMacro( 'csscrush_display_inlineblock' );
CssCrush::addRuleMacro( 'csscrush_minheight' );
CssCrush::addRuleMacro( 'csscrush_filter' );

########

function csscrush_display_inlineblock ( $rule ) {
	if ( !$rule->hasProperty( 'display' ) ) {
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
		if ( !$rule->hasProperty( '*zoom' ) ) {
			$new_set[] = $rule->createDeclaration( '*zoom', 1 );
		}
	}
	$rule->declarations = $new_set;
}

function csscrush_minheight ( $rule ) {
	if ( !$rule->hasProperty( 'min-height' ) ) {
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

function csscrush_filter ( $rule ) {
	if ( !$rule->hasProperty( 'filter' ) ) {
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
		if ( !$rule->hasProperty( 'zoom' ) ) {
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

function csscrush_display_box ( $rule ) {
	if ( !$rule->hasProperty( 'display' ) ) {
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

