<?php
/**
 * Polyfill for the rem (root em) length unit
 *
 * No version of IE to date (IE <= 10) resizes text set with pixels.
 * IE > 8 supports rem units which will resize. See http://caniuse.com/#feat=rem
 *
 * Three conversion modes:
 *
 *     rem-fallback (rem to px, with converted value as fallback)
 *     ============
 *     font-size: 1rem;
 *
 *     font-size: 16px;
 *     font-size: 1rem;
 *
 *     px-fallback (px to rem, with original pixel value as fallback)
 *     ===========
 *     font-size: 16px;
 *
 *     font-size: 16px;
 *     font-size: 1rem;
 *
 *     convert (in-place px to rem conversion)
 *     =======
 *     font-size: 16px;
 *
 *     font-size: 1rem;
 *
 * `rem-fallback` is the default mode. To change the conversion mode set a
 * variable named `rem__mode` with the mode name you want as its value.
 * 
 * To convert all values, not just values of the font related properties,
 * set a variable named `rem__all` with a value of `yes`.
 */

CssCrush_Plugin::register('rem', array(
    'enable' => 'csscrush__enable_rem',
    'disable' => 'csscrush__disable_rem',
));

function csscrush__enable_rem () {
    CssCrush_Hook::add('rule_prealias', 'csscrush__rem');
}

function csscrush__disable_rem () {
    CssCrush_Hook::remove('rule_prealias', 'csscrush__rem');
}

function csscrush__rem (CssCrush_Rule $rule) {

    static $rem_patt, $px_patt, $font_props, $modes;
    if (! $modes) {
        $rem_patt = CssCrush_Regex::create('<LB>(<number>)rem<RB>', 'iS');
        $px_patt = CssCrush_Regex::create('<LB>(<number>)px<RB>', 'iS');
        $font_props = array(
            'font' => true,
            'font-size' => true,
            'line-height' => true,
        );
        $modes = array('rem-fallback', 'px-fallback', 'convert');
    }

    $vars =& CssCrush::$process->variables;

    // Determine which properties are touched; all, or just font related.
    $just_font_props = ! isset($vars['rem__all']);

    if ($just_font_props && ! array_intersect_key($rule->canonicalProperties, $font_props)) {
        return;
    }

    // Determine what conversion mode we're using.
    $mode = $modes[0];
    if (isset($vars['rem__mode'])) {
        $_mode = $vars['rem__mode'];
        if (in_array($_mode, $modes)) {
            $mode = $_mode;
        }
    }

    // Determine the default base em-size, to my knowledge always 16px.
    $base = isset($vars['rem__base']) ? $vars['rem__base'] : 16;

    // Select the length match pattern depending on mode.
    $length_patt = $mode === 'rem-fallback' ? $rem_patt : $px_patt;

    $new_set = array();
    $rule_updated = false;
    foreach ($rule->declarations as $declaration) {
        if (
            $declaration->skip ||
            ($just_font_props && ! isset($font_props[$declaration->canonicalProperty])) ||
            ! preg_match_all($length_patt, $declaration->value, $m)
        ) {
            $new_set[] = $declaration;
            continue;
        }

        // Value has matching length components.
        $find = $m[0];
        $replace = array();
        $numbers = $m[1];

        switch ($mode) {
            // Converting a rem value to px.
            case 'rem-fallback':
                foreach ($numbers as $num) {
                    $replace[] = round(floatval($num) * $base, 5) . 'px';
                }
                break;

            // Converting a px value to rem.
            case 'convert':
            case 'px-fallback':
                foreach ($numbers as $num) {
                    $replace[] = round(floatval($num) / $base, 5) . 'rem';
                }
                break;
        }

        $converted_value = str_replace($find, $replace, $declaration->value);

        if ($mode === 'convert') {
            $declaration->value = $converted_value;
            $new_set[] = $declaration;
        }
        else {
            $clone = clone $declaration;
            $clone->value = $converted_value;
            $rule_updated = true;

            if ($mode === 'px-fallback') {
                $new_set[] = $declaration;
                $new_set[] = $clone;
            }
            else {
                $new_set[] = $clone;
                $new_set[] = $declaration;
            }
        }
    }

    if ($rule_updated) {
        $rule->setDeclarations($new_set);
    }
}
