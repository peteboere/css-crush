<?php
/**
 * Polyfill for opacity in IE < 9
 *
 * @before
 *     opacity: 0.45;
 *
 * @after
 *     opacity: 0.45;
 *     -ms-filter: "alpha(opacity=45)";
 *     *filter: alpha(opacity=45);
 *     zoom: 1;
 */
namespace CssCrush;

Plugin::register('ie-opacity', array(
    'enable' => function () {
        Hook::add('rule_postalias', 'CssCrush\ie_opacity');
    },
    'disable' => function () {
        Hook::remove('rule_postalias', 'CssCrush\ie_opacity');
    },
));


function ie_opacity(Rule $rule) {

    if ($rule->propertyCount('opacity') < 1) {
        return;
    }
    $new_set = array();
    foreach ($rule as $declaration) {
        $new_set[] = $declaration;
        if (
            $declaration->skip ||
            $declaration->property != 'opacity'
        ) {
            continue;
        }

        $opacity = (float) $declaration->value;
        $opacity = round($opacity * 100);

        if (! $rule->propertyCount('zoom')) {
            // Filters need hasLayout
            $new_set[] = new Declaration('zoom', 1);
        }
        $value = "alpha(opacity=$opacity)";
        $new_set[] = new Declaration('-ms-filter', "\"$value\"");
        $new_set[] = new Declaration('*filter', $value);
    }
    $rule->setDeclarations($new_set);
}
