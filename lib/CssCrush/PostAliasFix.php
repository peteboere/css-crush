<?php
/**
 *
 * Fixes for aliasing to legacy syntaxes.
 *
 */
class CssCrush_PostAliasFix
{
    // Currently only post fixing aliased functions.
    static public $functions = array();

    static public function init ()
    {
        // Register fix callbacks.
        CssCrush_PostAliasFix::add( 'function', 'linear-gradient',
            'csscrush__post_alias_fix_lineargradients' );
        CssCrush_PostAliasFix::add( 'function', 'linear-repeating-gradient',
            'csscrush__post_alias_fix_lineargradients' );
        CssCrush_PostAliasFix::add( 'function', 'radial-gradient',
            'csscrush__post_alias_fix_radialgradients' );
        CssCrush_PostAliasFix::add( 'function', 'radial-repeating-gradient',
            'csscrush__post_alias_fix_radialgradients' );
    }

    static public function add ( $alias_type, $key, $callback )
    {
        if ( $alias_type === 'function' ) {
            // $key is the aliased css function name.
            self::$functions[ $key ] = $callback;
        }
    }

    static public function remove ( $alias_type, $key )
    {
        if ( $type === 'function' ) {
            // $key is the aliased css function name.
            unset( self::$functions[ $key ] );
        }
    }
}

function csscrush__post_alias_fix_lineargradients ( $declaration_copies, $fn_name ) {

    // Swap the new 'to' gradient syntax to the old 'from' syntax for the prefixed versions.
    // 1. Create new paren tokens based on the first prefixed declaration.
    // 2. Replace the new syntax with the legacy syntax.
    // 3. Swap in the new tokens on all the prefixed declarations.

    static $angles_new, $angles_old;
    if ( ! $angles_new ) {
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
        $angles_new = array_keys( $angles );
        $angles_old = array_values( $angles );
    }

    // 1, 2.
    $patt = '~(?<![\w-])-[a-z]+-' . $fn_name . '(\?p\d+\?)~i';
    $original_parens = array();
    $replacement_parens = array();
    foreach ( CssCrush_Regex::matchAll( $patt, $declaration_copies[0]->value ) as $m ) {
        $original_parens[] = $m[1][0];
        $replacement_parens[] = CssCrush::$process->addToken(
            str_ireplace(
                $angles_new,
                $angles_old,
                CssCrush::$process->fetchToken( $m[1][0] )
            ), 'p' );
    }

    // 3.
    foreach ( $declaration_copies as $prefixed_copy ) {
        $prefixed_copy->value = str_replace( $original_parens, $replacement_parens, $prefixed_copy->value );
    }
}

function csscrush__post_alias_fix_radialgradients ( $declaration_copies, $fn_name ) {

    // Remove the new 'at' keyword from gradient syntax for legacy implementations.
    // 1. Create new paren tokens based on the first prefixed declaration.
    // 2. Replace the new syntax with the legacy syntax.
    // 3. Swap in the new tokens on all the prefixed declarations.

    // 1, 2.
    $patt = '~(?<![\w-])-[a-z]+-' . $fn_name . '(\?p\d+\?)~i';
    $original_parens = array();
    $replacement_parens = array();
    foreach ( CssCrush_Regex::matchAll( $patt, $declaration_copies[0]->value ) as $m ) {
        $original_parens[] = $m[1][0];
        $replacement_parens[] = CssCrush::$process->addToken(
            preg_replace(
                '~\bat +(top|left|bottom|right|center)\b~i',
                '$1',
                CssCrush::$process->fetchToken( $m[1][0] )
            ), 'p' );
    }

    // 3.
    foreach ( $declaration_copies as $prefixed_copy ) {
        $prefixed_copy->value = str_replace( $original_parens, $replacement_parens, $prefixed_copy->value );
    }
}

CssCrush_PostAliasFix::init();
