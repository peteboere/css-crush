<?php
/**
  *
  * Functions for procedural style API.
  *
  */
function csscrush_file ( $file, $options = null ) {
    return CssCrush::file( $file, $options );
}

function csscrush_tag ( $file, $options = null, $attributes = array() ) {
    return CssCrush::tag( $file, $options, $attributes );
}

function csscrush_inline ( $file, $options = null, $attributes = array() ) {
    return CssCrush::inline( $file, $options, $attributes );
}

function csscrush_string ( $string, $options = null ) {
    return CssCrush::string( $string, $options );
}

function csscrush_globalvars ( $vars ) {
    return CssCrush::globalVars( $vars );
}

function csscrush_clearcache ( $dir = '' ) {
    return CssCrush::clearcache( $dir );
}

function csscrush_stat ( $name = null ) {
    return CssCrush::stat( $name );
}
