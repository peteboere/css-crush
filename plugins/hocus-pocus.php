<?php
/**
 * :hover/:focus and :hover/:focus/:active composite pseudo classes
 *
 * @see docs/plugins/hocus-pocus.md
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
