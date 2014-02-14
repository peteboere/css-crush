<?php
/**
 * :hover/:focus and :hover/:focus/:active composite pseudo classes
 *
 * @see docs/plugins/hocus-pocus.md
 */
namespace CssCrush;

Plugin::register('hocus-pocus', array(
    'enable' => function ($process) {
        $process->addSelectorAlias('hocus', ':any(:hover,:focus)');
        $process->addSelectorAlias('pocus', ':any(:hover,:focus,:active)');
    }
));
