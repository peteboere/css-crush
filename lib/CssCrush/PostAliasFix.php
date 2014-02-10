<?php
/**
 *
 * Fixes for aliasing to legacy syntaxes.
 *
 */
namespace CssCrush;

class PostAliasFix
{
    public static $functions = array(
        '.gradients' => 'CssCrush\postalias_fix_gradients',
    );

    public static function add($alias_type, $key, $callback)
    {
        if ($alias_type === 'function') {
            self::$functions[$key] = $callback;
        }
    }

    public static function remove($alias_type, $key)
    {
        if ($alias_type === 'function') {
            unset(self::$functions[$key]);
        }
    }
}

/**
 * Post alias fix callback for all gradients.
 */
function postalias_fix_gradients($declaration_copies) {
    postalias_fix_linear_gradients($declaration_copies);
    postalias_fix_radial_gradients($declaration_copies);
}

/**
 * Convert the new angle syntax (keyword and degree) on -x-linear-gradient() functions
 * to legacy equivalents.
 */
function postalias_fix_linear_gradients($declaration_copies) {

    static $angles_new, $angles_old;
    if (! $angles_new) {
        $angles = array(
            'to top' => 'bottom',
            'to right' => 'left',
            'to bottom' => 'top',
            'to left' => 'right',
            // 'magic' corners.
            'to top left' => 'bottom right',
            'to left top' => 'bottom right',
            'to top right' => 'bottom left',
            'to right top' => 'bottom left',
            'to bottom left' => 'top right',
            'to left bottom' => 'top right',
            'to bottom right' => 'top left',
            'to right bottom' => 'top left',
        );
        $angles_new = array_keys($angles);
        $angles_old = array_values($angles);
    }

    static $deg_patt, $fn_patt;
    if (! $deg_patt) {
        $deg_patt = Regex::make('~(?<=[\( ])({{ number }})deg~i');
        $fn_patt = Regex::make('~{{ LB }}{{ vendor }}(?:repeating-)?linear-gradient{{ parens }}~iS');
    }

    // Legacy angles move anti-clockwise and start from East, not North.
    $deg_convert_callback = function ($m) {
        $angle = floatval($m[1]);
        $angle = ($angle + 90) - ($angle * 2);
        return ($angle < 0 ? $angle + 360 : $angle) . 'deg';
    };

    // Create new paren tokens based on the first prefixed declaration.
    // Replace the new syntax with the legacy syntax.
    $original_parens = array();
    $replacement_parens = array();

    foreach (Regex::matchAll($fn_patt, $declaration_copies[0]->value) as $m) {

        $original_parens[] = $m['parens'][0];

        // Keyword angle values.
        $updated_paren_value = str_ireplace($angles_new, $angles_old, $m['parens'][0]);

        // Degree angle values.
        $replacement_parens[] = preg_replace_callback($deg_patt, $deg_convert_callback, $updated_paren_value);
    }

    foreach ($declaration_copies as $prefixed_copy) {
        $prefixed_copy->value = str_replace(
            $original_parens,
            $replacement_parens,
            $prefixed_copy->value
        );
    }
}

/**
 * Remove the 'at' keyword from -x-radial-gradient() for legacy implementations.
 */
function postalias_fix_radial_gradients($declaration_copies) {

    // Create new paren tokens based on the first prefixed declaration.
    // Replace the new syntax with the legacy syntax.
    static $fn_patt;
    if (! $fn_patt) {
        $fn_patt = Regex::make('~{{ LB }}{{ vendor }}(?:repeating-)?radial-gradient{{ parens }}~iS');
    }

    $original_parens = array();
    $replacement_parens = array();

    foreach (Regex::matchAll($fn_patt, $declaration_copies[0]->value) as $m) {
        $original_parens[] = $m['parens'][0];
        $replacement_parens[] = preg_replace('~\bat +(top|left|bottom|right|center)\b~i', '$1', $m['parens'][0]);
    }

    foreach ($declaration_copies as $prefixed_copy) {
        $prefixed_copy->value = str_replace(
            $original_parens,
            $replacement_parens,
            $prefixed_copy->value
        );
    }
}
