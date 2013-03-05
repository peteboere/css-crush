<?php
/**
 *
 * 
 */

CssCrush_Plugin::register( 'shapes', array(
    'enable' => 'csscrush__enable_shapes',
    'disable' => 'csscrush__disable_shapes',
));

function csscrush__enable_shapes () {
    CssCrush_Hook::add( 'process_extract', 'csscrush__shapes' );
    CssCrush_Function::register( 'shape', 'csscrush_fn__shape' );
}

function csscrush__disable_shapes () {
    CssCrush_Hook::remove( 'process_extract', 'csscrush__shapes' );
    CssCrush_Function::deRegister( 'shape' );
}

function csscrush__shapes ( $process ) {

    static $callback, $patt;
    if ( ! $callback ) {
        $patt = CssCrush_Regex::create( '@shape +(<ident>) *\{ *(.*?) *\};?', 'iS' );
        $callback = create_function( '$m', '
            $name = $m[1];
            $block = $m[2];
            if ( ! empty( $name ) && ! empty( $block ) ) {
                CssCrush::$process->misc->shape_defs[ $name ] =
                    CssCrush_Util::parseBlock( $block, true );
            }
        ');
    }

    // Extract shape definitions and stash them.
    $process->stream->pregReplaceCallback( $patt, $callback );

    // csscrush::log( $process->misc->shape_defs );
}

function csscrush_fn__shape () {
    // ...
}
