<?php
/**
 * Polyfill for display:inline-block in IE < 8
 *
 * @before
 *     display: inline-block;
 *
 * @after
 *     display: inline-block;
 *     *display: inline;
 *     *zoom: 1;
 */
namespace CssCrush;

Plugin::register('ie-inline-block', array(
    'enable' => function () {
        Hook::add('rule_postalias', 'CssCrush\ie_inline_block');
    },
    'disable' => function () {
        Hook::remove('rule_postalias', 'CssCrush\ie_inline_block');
    },
));


function ie_inline_block(Rule $rule) {

    if ($rule->propertyCount('display') < 1) {
        return;
    }

    $stack = array();
    foreach ($rule as $declaration) {
        $stack[] = $declaration;
        $is_display = $declaration->property === 'display';
        if (
            $declaration->skip ||
            ! $is_display ||
            $is_display && $declaration->value !== 'inline-block') {
            continue;
        }
        $stack[] = new Declaration('*display', 'inline');
        $stack[] = new Declaration('*zoom', 1);
    }
    $rule->setDeclarations($stack);
}
