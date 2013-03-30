<?php
/**
 * Transforms correctly-spelt Queen's English into valid CSS
 *
 * Spiffing (http://spiffingcss.com), by @idiot.
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

CssCrush_Plugin::register('spiffing', array(
    'enable' => 'csscrush__enable_spiffing',
    'disable' => 'csscrush__disable_spiffing',
));

function csscrush__enable_spiffing () {
    CssCrush_Hook::add('rule_preprocess', 'csscrush__spiffing');
}

function csscrush__disable_spiffing () {
    CssCrush_Hook::add('rule_preprocess', 'csscrush__spiffing');
}

function csscrush__spiffing ($rule) {

    $find = array('colour', 'grey', '!please', 'transparency', 'centre', 'plump', 'photograph', 'capitalise');
    $replace = array('color', 'gray', '!important', 'opacity', 'center', 'bold', 'image', 'capitalize');

    $rule->declaration_raw = str_ireplace($find, $replace, $rule->declaration_raw);
}
