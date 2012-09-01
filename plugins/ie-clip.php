<?php
/**
 * Fix clip syntax for IE < 8
 * 
 * @before
 *     clip: rect(1px,1px,1px,1px);
 * 
 * @after
 *     clip: rect(1px,1px,1px,1px);
 *     *clip: rect(1px 1px 1px 1px);
 */

csscrush_hook::add( 'rule_postalias', 'csscrush__ie_clip' );

function csscrush__ie_clip ( csscrush_rule $rule ) {

	// Assume it's been dealt with if the property occurs more than once 
	if ( $rule->propertyCount( 'clip' ) !== 1 ) {
		return;
	}
	$new_set = array();
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
		if ( 
			$declaration->skip ||
			$declaration->property !== 'clip' 
		) {
			continue;
		}
		$new_set[] = new csscrush_declaration( '*clip', str_replace( ',', ' ', $declaration->getFullValue() ) );
	}
	$rule->declarations = $new_set;
}

