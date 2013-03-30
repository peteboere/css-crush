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

CssCrush_Plugin::register('ie-inline-block', array(
    'enable' => 'csscrush__enable_ie_inline_block',
    'disable' => 'csscrush__disable_ie_inline_block',
));

function csscrush__enable_ie_inline_block () {
    CssCrush_Hook::add('rule_postalias', 'csscrush__ie_inline_block');
}

function csscrush__disable_ie_inline_block () {
    CssCrush_Hook::remove('rule_postalias', 'csscrush__ie_inline_block');
}

function csscrush__ie_inline_block (CssCrush_Rule $rule) {

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
        $stack[] = new CssCrush_Declaration('*display', 'inline');
        $stack[] = new CssCrush_Declaration('*zoom', 1);
    }
    $rule->setDeclarations($stack);
}
