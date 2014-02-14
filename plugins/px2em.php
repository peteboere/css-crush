<?php
/**
 * Functions for converting pixel values into em (px2em) or rem (px2rem) values
 *
 * @see docs/plugins/px2em.md
 */
namespace CssCrush;

Plugin::register('px2em', array(
    'enable' => function ($process) {
        $process->functions->add('px2em', 'CssCrush\fn__px2em');
        $process->functions->add('px2rem', 'CssCrush\fn__px2rem');
    }
));


function fn__px2em($input) {

    return px2em($input, 'em', Crush::$process->settings->get('px2em-base', 16));
}

function fn__px2rem($input) {

    return px2em($input, 'rem', Crush::$process->settings->get('px2rem-base', 16));
}

function px2em($input, $unit, $default_base) {

    list($px, $base) = Functions::parseArgsSimple($input) + array(16, $default_base);

    return round($px / $base, 5) . $unit;
}
