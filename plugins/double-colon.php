<?php
/**
 * Double Colon
 * Compile pseudo element double colon syntax to single colon syntax for backwards compatibility
 * 
 * @before
 *     p::after { content: '!'; }
 * 
 * @after
 *     p:after { content: '!'; }
 * 
 */

CssCrush_Hook::add( 'rule_preprocess', 'csscrush_doublecolon' );

function csscrush_doublecolon ( $rule ) {
	$rule->selector_raw = preg_replace( '!::(after|before|first-letter|first-line)!', ':$1', $rule->selector_raw );
}
