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
        Crush::addSelectorAlias('hocus', ':any(:hover,:focus)');
        Crush::addSelectorAlias('pocus', ':any(:hover,:focus,:active)');
    },
    'disable' => function () {
        Crush::removeSelectorAlias('hocus');
        Crush::removeSelectorAlias('pocus');
    },
));
