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
namespace CssCrush;

Plugin::register('spiffing', array(
    'enable' => function () {
        Hook::add('rule_preprocess', 'CssCrush\spiffing');
    },
    'disable' => function () {
        Hook::remove('rule_preprocess', 'CssCrush\spiffing');
    },
));


function spiffing ($rule) {

    $find = array('colour', 'grey', '!please', 'transparency', 'centre', 'plump', 'photograph', 'capitalise');
    $replace = array('color', 'gray', '!important', 'opacity', 'center', 'bold', 'image', 'capitalize');

    $rule->declaration_raw = str_ireplace($find, $replace, $rule->declaration_raw);
}
