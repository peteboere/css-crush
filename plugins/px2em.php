<?php
/**
 * Functions for converting pixel values into em (px2em) or rem (px2rem) values
 *
 * For both functions the optional second argument is base font-size for calculation
 * (16px by default) though usually not required when converting pixel to rem.
 *
 * @before
 *     font-size: px2em(11 13);
 *     font-size: px2rem(16);
 *
 * @after
 *     font-size: .84615em;
 *     font-size: 1rem;
 */
namespace CssCrush;

Plugin::register('px2em', array(
    'enable' => function () {
        Functions::register('px2em', 'CssCrush\fn__px2em');
        Functions::register('px2rem', 'CssCrush\fn__px2rem');
    },
    'disable' => function () {
        Functions::deRegister('px2em');
        Functions::deRegister('px2rem');
    },
));


function fn__px2em($input) {

    $base = 16;

    // Override default base if variable is set.
    if (isset(CssCrush::$process->vars['px2em__base'])) {
        $base = CssCrush::$process->vars['px2em__base'];
    }

    return px2em($input, 'em', $base);
}

function fn__px2rem($input) {

    $base = 16;

    // Override default base if variable is set.
    if (isset(CssCrush::$process->vars['px2rem__base'])) {
        $base = CssCrush::$process->vars['px2rem__base'];
    }

    return px2em($input, 'rem', $base);
}

function px2em($input, $unit, $default_base) {

    list($px, $base) = Functions::parseArgsSimple($input) + array(
        16,
        $default_base,
    );

    return round($px / $base, 5) . $unit;
}
