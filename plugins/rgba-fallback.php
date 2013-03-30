<?php
/**
 * Polyfill for rgba() color values
 *
 * Only works with background shorthand IE < 8
 * (http://css-tricks.com/2151-rgba-browser-support/)
 *
 * @before
 *     background: rgba(0,0,0,.5);
 *
 * @after
 *     background: rgb(0,0,0);
 *     background: rgba(0,0,0,.5);
 */

CssCrush_Plugin::register('rgba-fallback', array(
    'enable' => 'csscrush__enable_rgba_fallback',
    'disable' => 'csscrush__disable_rgba_fallback',
));

function csscrush__enable_rgba_fallback () {
    CssCrush_Hook::add('rule_postalias', 'csscrush__rgba_fallback');
}

function csscrush__disable_rgba_fallback () {
    CssCrush_Hook::remove('rule_postalias', 'csscrush__rgba_fallback');
}

function csscrush__rgba_fallback (CssCrush_Rule $rule) {

    $props = array_keys($rule->properties);

    // Determine which properties apply
    $rgba_props = array();
    foreach ($props as $prop) {
        if ($prop === 'background' || strpos($prop, 'color') !== false) {
            $rgba_props[] = $prop;
        }
    }
    if (empty($rgba_props)) {
        return;
    }

    static $rgb_patt;
    if (! $rgb_patt) {
        $rgb_patt = CssCrush_Regex::create('^rgba<p-token>$', 'i');
    }

    $new_set = array();
    foreach ($rule as $declaration) {

        $is_viable = in_array($declaration->property, $rgba_props);
        if (
            $declaration->skip ||
            ! $is_viable ||
            $is_viable && ! preg_match($rgb_patt, $declaration->value)
        ) {
            $new_set[] = $declaration;
            continue;
        }

        // Create rgb value from rgba.
        $raw_value = $declaration->getFullValue();
        $raw_value = substr($raw_value, 5, strlen($raw_value) - 1);
        list($r, $g, $b, $a) = explode(',', $raw_value);

        // Add rgb value to the stack, followed by rgba.
        $new_set[] = new CssCrush_Declaration($declaration->property, "rgb($r,$g,$b)");
        $new_set[] = $declaration;
    }
    $rule->setDeclarations($new_set);
}
