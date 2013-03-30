<?php
/**
 * Polyfill for min-height in IE 6
 * 
 * @before
 *     min-height: 100px;
 * 
 * @after
 *     min-height: 100px;
 *     _height: 100px;
 */

CssCrush_Plugin::register('ie-min-height', array(
    'enable' => 'csscrush__enable_ie_min_height',
    'disable' => 'csscrush__disable_ie_min_height',
));

function csscrush__enable_ie_min_height () {
    CssCrush_Hook::add('rule_postalias', 'csscrush__ie_min_height');
}

function csscrush__disable_ie_min_height () {
    CssCrush_Hook::remove('rule_postalias', 'csscrush__ie_min_height');
}

function csscrush__ie_min_height (CssCrush_Rule $rule) {

    if ($rule->propertyCount('min-height') < 1) {
        return;
    }
    $new_set = array();
    foreach ($rule as $declaration) {
        $new_set[] = $declaration;
        if (
            $declaration->skip ||
            $declaration->property !== 'min-height') {
            continue;
        }
        $new_set[] = new CssCrush_Declaration('_height', $declaration->value);
    }
    $rule->setDeclarations($new_set);
}
