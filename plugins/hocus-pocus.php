<?php
/**
 * Hocus Pocus
 * Non-standard composite pseudo classes
 * 
 * @before
 *     a:hocus { color: red; }
 *     a:pocus { color: red; }
 * 
 * @after
 *    a:hover, a:focus { color: red; }
 *    a:hover, a:focus, a:active { color: red; }
 * 
 */

CssCrush_Hook::add( 'rule_preprocess', 'csscrush_hocuspocus' );

function csscrush_hocuspocus ( $rule ) {
	$adjustments = array(
		'!:hocus([^a-z0-9_-])!' => ':any(:hover,:focus)$1',
		'!:pocus([^a-z0-9_-])!' => ':any(:hover,:focus,:active)$1',
	);
	$rule->selector_raw = preg_replace( array_keys( $adjustments ), array_values( $adjustments ), $rule->selector_raw );
}
