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

CssCrush_Plugin::register('ie-opacity', array(
    'enable' => 'csscrush__enable_ie_opacity',
    'disable' => 'csscrush__disable_ie_opacity',
));

function csscrush__enable_ie_opacity () {
    CssCrush_Hook::add('rule_postalias', 'csscrush__ie_opacity');
}

function csscrush__disable_ie_opacity () {
    CssCrush_Hook::remove('rule_postalias', 'csscrush__ie_opacity');
}

function csscrush__ie_opacity (CssCrush_Rule $rule) {

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
            $new_set[] = new CssCrush_Declaration('zoom', 1);
        }
        $value = "alpha(opacity=$opacity)";
        $new_set[] = new CssCrush_Declaration('-ms-filter', "\"$value\"");
        $new_set[] = new CssCrush_Declaration('*filter', $value);
    }
    $rule->setDeclarations($new_set);
}
