<?php
/**
 * Spiffing (http://spiffingcss.com), by @idiot.
 * Transforms correctly-spelt Queen's English into valid CSS.
 * 
 * @before 
 *     background-colour: grey !please;
 *     transparency: 0.5;
 * 
 * @after
 *     background-color: gray !important;
 *     opacity: 0.5;
 * 
 */

csscrush_hook::add( 'rule_preprocess', 'csscrush_spiffing' );

function csscrush_spiffing ( $rule ) {

	$find = array( 'colour', 'grey', '!please', 'transparency', 'centre', 'plump', 'photograph', 'capitalise' );
	$replace = array( 'color', 'gray', '!important', 'opacity', 'center', 'bold', 'image', 'capitalize' );
	
	$rule->declaration_raw = str_ireplace( $find, $replace, $rule->declaration_raw );
}
