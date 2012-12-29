<?php
/**
 * Hocus Pocus
 * Non-standard composite pseudo classes
 * 
 * @before
 *     a:hocus { color: red; }
 *     a:pocus { color: red; }
 * 
 * @after
 *    a:hover, a:focus { color: red; }
 *    a:hover, a:focus, a:active { color: red; }
 * 
 */

CssCrush_Plugin::register( 'hocus-pocus', array(
    'enable' => 'csscrush__enable_hocus_pocus',
    'disable' => 'csscrush__disable_hocus_pocus',
));

function csscrush__enable_hocus_pocus () {
    CssCrush::$config->selectorAliases[ 'hocus' ] = ':any(:hover,:focus)';
    CssCrush::$config->selectorAliases[ 'pocus' ] = ':any(:hover,:focus,:active)';
}

function csscrush__disable_hocus_pocus () {
    unset( CssCrush::$config->selectorAliases[ 'hocus' ] );
    unset( CssCrush::$config->selectorAliases[ 'pocus' ] );
}
