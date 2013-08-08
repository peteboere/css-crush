<?php
/**
 * :hover/:focus and :hover/:focus/:active composite pseudo classes
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
namespace CssCrush;

Plugin::register('hocus-pocus', array(
    'enable' => function () {
        CssCrush::addSelectorAlias('hocus', ':any(:hover,:focus)');
        CssCrush::addSelectorAlias('pocus', ':any(:hover,:focus,:active)');
    },
    'disable' => function () {
        CssCrush::removeSelectorAlias('hocus');
        CssCrush::removeSelectorAlias('pocus');
    },
));
