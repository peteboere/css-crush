<?php
/**
 *
 * Fixes for aliasing to legacy syntaxes.
 *
 */
class CssCrush_PostAliasFix
{
    // Currently only post fixing aliased functions.
    static public $functions = array(
        ':gradients' => 'csscrush__post_alias_fix_gradients',
    );

    static public function add ($alias_type, $key, $callback)
    {
        if ($alias_type === 'function') {
            // $key is the aliased css function name.
            self::$functions[$key] = $callback;
        }
    }

    static public function remove ($alias_type, $key)
    {
        if ($type === 'function') {
            // $key is the aliased css function name.
            unset(self::$functions[$key]);
        }
    }
}

/**
 * Post alias fix callback for all gradients.
 */
function csscrush__post_alias_fix_gradients ($declaration_copies) {

    csscrush__post_alias_fix_lineargradients($declaration_copies);
    csscrush__post_alias_fix_radialgradients($declaration_copies);
}

/**
 * Convert the new angle syntax (keyword and degree) on -x-linear-gradient() functions
 * to legacy equivalents.
 */
function csscrush__post_alias_fix_lineargradients ($declaration_copies) {

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

    static $deg_patt, $deg_convert_callback, $fn_patt;
    if (! $deg_patt) {
        $deg_patt = CssCrush_Regex::create('(?<=[\( ])(<number>)deg', 'i');
        // Legacy angles move anti-clockwise and start from East, not North.
        $deg_convert_callback = create_function('$m', '
            $angle = floatval($m[1]);
            $angle = ($angle + 90) - ($angle * 2);
            return ($angle < 0 ? $angle + 360 : $angle) . \'deg\';
        ');
        $fn_patt = CssCrush_Regex::create('<LB><vendor>(?:(?:repeating-)?linear-gradient)(<p-token>)', 'iS');
    }

    // Create new paren tokens based on the first prefixed declaration.
    // Replace the new syntax with the legacy syntax.
    $original_parens = array();
    $replacement_parens = array();
    foreach (CssCrush_Regex::matchAll($fn_patt, $declaration_copies[0]->value) as $m) {

        $original_parens[] = $m[1][0];
        $original_paren_value = CssCrush::$process->fetchToken($m[1][0]);

        // Convert keyword angle values.
        $updated_paren_value = str_ireplace(
            $angles_new,
            $angles_old,
            $original_paren_value
        );

        // Convert degree angle values.
        $updated_paren_value = preg_replace_callback(
            $deg_patt,
            $deg_convert_callback,
            $updated_paren_value
        );

        $replacement_parens[] = CssCrush::$process->addToken($updated_paren_value, 'p');
    }

    // Swap in the new tokens on all the prefixed declarations.
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
function csscrush__post_alias_fix_radialgradients ($declaration_copies) {

    // Create new paren tokens based on the first prefixed declaration.
    // Replace the new syntax with the legacy syntax.
    static $fn_patt;
    if (! $fn_patt) {
        $fn_patt = CssCrush_Regex::create('<LB><vendor>(?:(?:repeating-)?radial-gradient)(<p-token>)', 'iS');
    }
    $original_parens = array();
    $replacement_parens = array();
    foreach (CssCrush_Regex::matchAll($fn_patt, $declaration_copies[0]->value) as $m) {

        $original_parens[] = $m[1][0];
        $replacement_parens[] = CssCrush::$process->addToken(
            preg_replace(
                '~\bat +(top|left|bottom|right|center)\b~i',
                '$1',
                CssCrush::$process->fetchToken($m[1][0])
            ), 'p');
    }

    // Swap in the new tokens on all the prefixed declarations.
    foreach ($declaration_copies as $prefixed_copy) {
        $prefixed_copy->value = str_replace(
            $original_parens,
            $replacement_parens,
            $prefixed_copy->value
        );
    }
}
