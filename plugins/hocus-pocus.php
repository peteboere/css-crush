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

csscrush_plugin::register( 'hocus-pocus', array(
    'enable' => 'csscrush__enable_hocus_pocus',
    'disable' => 'csscrush__disable_hocus_pocus',
));

function csscrush__enable_hocus_pocus () {
    csscrush::$config->selectorAliases[ 'hocus' ] = ':any(:hover,:focus)';
    csscrush::$config->selectorAliases[ 'pocus' ] = ':any(:hover,:focus,:active)';
}

function csscrush__disable_hocus_pocus () {
    unset( csscrush::$config->selectorAliases[ 'hocus' ] );
    unset( csscrush::$config->selectorAliases[ 'pocus' ] );
}
