<?php
/**
 * Shim for CSS3 'initial' keyword value
 * (http://www.w3.org/TR/css3-cascade/#initial0)
 * 
 * @before 
 *     opacity: initial;
 *     white-space: initial;
 *     min-height: initial;
 * 
 * @after
 *     opacity: 1;
 *     white-space: normal;
 *     max-height: auto;
 * 
 */

csscrush_hook::add( 'rule_prealias', 'csscrush__initial' );

function csscrush__initial ( csscrush_rule $rule ) {

	static $initialValues = null;
	if ( ! $initialValues ) {
		if ( ! ( $initialValues = @parse_ini_file( CssCrush::$config->location . '/misc/initial-values.ini' ) ) ) {
			trigger_error( __METHOD__ . ": Initial keywords file could not be parsed.\n", E_USER_NOTICE );
			return;
		}
	}

	foreach ( $rule as &$declaration ) {
		if ( !$declaration->skip && 'initial' === $declaration->value ) {
			if ( isset( $initialValues[ $declaration->property ] ) ) {
				$declaration->value = $initialValues[ $declaration->property ];
			}
			else {
				// Fallback to 'inherit'
				$declaration->value = 'inherit';
			}
		}
	}
}