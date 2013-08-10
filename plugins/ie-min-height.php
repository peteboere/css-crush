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
namespace CssCrush;

Plugin::register('ie-min-height', array(
    'enable' => function () {
        Hook::add('rule_postalias', 'CssCrush\ie_min_height');
    },
    'disable' => function () {
        Hook::remove('rule_postalias', 'CssCrush\ie_min_height');
    },
));


function ie_min_height (Rule $rule) {

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
        $new_set[] = new Declaration('_height', $declaration->value);
    }
    $rule->setDeclarations($new_set);
}
