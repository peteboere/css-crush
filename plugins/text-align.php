<?php
/**
 * Polyfill for direction sensitive text-align values, start and end
 *
 * @see docs/plugins/text-align.md
 */
namespace CssCrush;

Plugin::register('text-align', array(
    'enable' => function ($process) {
        $process->hooks->add('rule_prealias', 'CssCrush\text_align');
    }
));


function text_align(Rule $rule) {

    static $text_align_properties = array('text-align' => true, 'text-align-last' => true);
    static $text_align_special_values = array('start' => true, 'end' => true);

    if (! array_intersect_key($rule->declarations->properties, $text_align_properties)) {
        return;
    }

    $dir = Crush::$process->settings->get('dir', 'ltr');

    $stack = array();
    foreach ($rule->declarations as $declaration) {
        $value = strtolower($declaration->value);
        if (
            ! $declaration->skip &&
            isset($text_align_properties[$declaration->property]) &&
            isset($text_align_special_values[$value])
        ) {
            $fallback_declaration = clone $declaration;
            if ($value == 'start') {
                $fallback_declaration->value = $dir == 'ltr' ? 'left' : 'right';
            }
            elseif ($value == 'end') {
                $fallback_declaration->value = $dir == 'ltr' ? 'right' : 'left';
            }
            $stack[] = $fallback_declaration;
        }

        $stack[] = $declaration;
    }

    $rule->declarations->reset($stack);
}
