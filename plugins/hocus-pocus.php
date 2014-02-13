<?php
/**
 * :hover/:focus and :hover/:focus/:active composite pseudo classes
 *
 * @see docs/plugins/hocus-pocus.md
 */
namespace CssCrush;

Plugin::register('hocus-pocus', array(
    'enable' => function ($process) {
        $process->addSelectorAlias('hocus', ':hover,:focus');
        $process->addSelectorAlias('pocus', ':hover,:focus,:active');
    }
));
