<?php
/**
 * rem-fallback
 *
 * Auto-generates a declaration fallback when using rem units for font-sizes.
 * IE 7/8 don't support rem units. See http://caniuse.com/#feat=rem
 *
 * @before
 *      font: .6875rem/1rem sans-serif;
 *
 * @after
 *      font: 11px/16px sans-serif;
 *      font: .6875rem/1rem sans-serif;
 */

CssCrush_Plugin::register( 'rem-fallback', array(
    'enable' => 'csscrush__enable_rem_fallback',
    'disable' => 'csscrush__disable_rem_fallback',
));

function csscrush__enable_rem_fallback () {
    CssCrush_Hook::add( 'rule_prealias', 'csscrush__rem_fallback' );
}

function csscrush__disable_rem_fallback () {
    CssCrush_Hook::remove( 'rule_prealias', 'csscrush__rem_fallback' );
}

function csscrush__rem_fallback (CssCrush_Rule $rule) {

    static $fontsize_properties, $rem_patt;
    if (! $fontsize_properties) {
        $fontsize_properties = array(
            'font' => true,
            'font-size' => true,
        );
        $rem_patt = CssCrush_Regex::create('<LB>(<number>)rem<RB>', 'iS');
    }

    if (! array_intersect_key($rule->canonicalProperties, $fontsize_properties)) {
        return;
    }

    $new_set = array();
    $rule_updated = false;
    foreach ($rule->declarations as $declaration) {
        if (
            ! $declaration->skip &&
            isset($fontsize_properties[$declaration->canonicalProperty]) &&
            preg_match_all($rem_patt, $declaration->value, $m)
        ) {
            // Value has rem, create new declaration with rem value converted to pixel.
            $find = $m[0];
            $replace = array();
            foreach ($m[1] as $num) {
                $replace[] = round(floatval($num) * 16, 5) . 'px';
            }
            $new_set[] = new CssCrush_Declaration(
                $declaration->property, str_replace($find, $replace, $declaration->value));
            $rule_updated = true;
        }
        $new_set[] = $declaration;
    }

    if ($rule_updated) {
        $rule->setDeclarations($new_set);
    }
}
