<?php
/**
 * Polyfill for the rem (root em) length unit
 *
 * @see docs/plugins/rem.md
 */
namespace CssCrush;

Plugin::register('rem', array(
    'enable' => function ($process) {
        $process->hooks->add('rule_prealias', 'CssCrush\rem');
    }
));


function rem(Rule $rule) {

    static $rem_patt, $px_patt, $font_props, $modes;
    if (! $modes) {
        $rem_patt = Regex::make('~{{LB}}({{number}})rem{{RB}}~iS');
        $px_patt = Regex::make('~{{LB}}({{number}})px{{RB}}~iS');
        $font_props = array(
            'font' => true,
            'font-size' => true,
            'line-height' => true,
        );
        $modes = array('rem-fallback', 'px-fallback', 'convert');
    }

    // Determine which properties are touched; all, or just font related.
    $just_font_props = ! Crush::$process->settings->get('rem-all', false);

    if ($just_font_props && ! array_intersect_key($rule->declarations->canonicalProperties, $font_props)) {
        return;
    }

    // Determine what conversion mode we're using.
    $mode = Crush::$process->settings->get('rem-mode', $modes[0]);

    // Determine the default base em-size, to my knowledge always 16px.
    $base = Crush::$process->settings->get('rem-base', 16);

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
        $rule->declarations->reset($new_set);
    }
}
