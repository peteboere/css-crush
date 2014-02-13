<?php
/**
 * Polyfill for opacity in IE < 9
 *
 * @see docs/plugins/ie-opacity.md
 */
namespace CssCrush;

Plugin::register('ie-opacity', array(
    'enable' => function ($process) {
        $process->hooks->add('rule_postalias', 'CssCrush\ie_opacity');
    }
));


function ie_opacity(Rule $rule) {

    if ($rule->declarations->propertyCount('opacity') < 1) {
        return;
    }

    $new_set = array();
    foreach ($rule->declarations as $declaration) {
        $new_set[] = $declaration;
        if (
            $declaration->skip ||
            $declaration->property != 'opacity'
        ) {
            continue;
        }

        $opacity = (float) $declaration->value;
        $opacity = round($opacity * 100);

        if (! $rule->declarations->propertyCount('zoom')) {
            // Filters need hasLayout
            $new_set[] = new Declaration('zoom', 1);
        }
        $value = "alpha(opacity=$opacity)";
        $new_set[] = new Declaration('-ms-filter', "\"$value\"");
        $new_set[] = new Declaration('*filter', $value);
    }
    $rule->declarations->reset($new_set);
}
