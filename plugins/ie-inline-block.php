<?php
/**
 * Polyfill for display:inline-block in IE < 8
 *
 * @see docs/plugins/ie-inline-block.md
 */
namespace CssCrush;

Plugin::register('ie-inline-block', array(
    'enable' => function ($process) {
        $process->hooks->add('rule_postalias', 'CssCrush\ie_inline_block');
    }
));


function ie_inline_block(Rule $rule) {

    if ($rule->declarations->propertyCount('display') < 1) {
        return;
    }

    $stack = array();
    foreach ($rule->declarations as $declaration) {
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
    $rule->declarations->reset($stack);
}
