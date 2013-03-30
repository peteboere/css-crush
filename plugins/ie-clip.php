<?php
/**
 * Polyfill for the clip property in IE < 8
 *
 * @before
 *     clip: rect(1px,1px,1px,1px);
 * 
 * @after
 *     clip: rect(1px,1px,1px,1px);
 *     *clip: rect(1px 1px 1px 1px);
 */

CssCrush_Plugin::register( 'ie-clip', array(
    'enable' => 'csscrush__enable_ie_clip',
    'disable' => 'csscrush__disable_ie_clip',
));

function csscrush__enable_ie_clip () {
    CssCrush_Hook::add('rule_postalias', 'csscrush__ie_clip');
}

function csscrush__disable_ie_clip () {
    CssCrush_Hook::remove('rule_postalias', 'csscrush__ie_clip');
}

function csscrush__ie_clip (CssCrush_Rule $rule) {

    // Assume it's been dealt with if the property occurs more than once.
    if ($rule->propertyCount('clip') !== 1) {
        return;
    }
    $new_set = array();
    foreach ($rule as $declaration) {
        $new_set[] = $declaration;
        if ( 
            $declaration->skip ||
            $declaration->property !== 'clip'
        ) {
            continue;
        }
        $new_set[] = new CssCrush_Declaration('*clip', str_replace( ',', ' ', $declaration->getFullValue()));
    }
    $rule->setDeclarations($new_set);
}
