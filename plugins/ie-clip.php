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
namespace CssCrush;

Plugin::register( 'ie-clip', array(
    'enable' => function () {
        Hook::add('rule_postalias', 'CssCrush\ie_clip');
    },
    'disable' => function () {
        Hook::remove('rule_postalias', 'CssCrush\ie_clip');
    },
));


function ie_clip (Rule $rule) {

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
        $new_set[] = new Declaration('*clip', str_replace( ',', ' ', $declaration->getFullValue()));
    }
    $rule->setDeclarations($new_set);
}
