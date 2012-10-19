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

csscrush::$config->selectorAliases[ 'hocus' ] = ':any(:hover,:focus)';
csscrush::$config->selectorAliases[ 'pocus' ] = ':any(:hover,:focus,:active)';
