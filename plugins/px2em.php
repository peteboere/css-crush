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

CssCrush_Plugin::register( 'px2em', array(
    'enable' => 'csscrush__enable_px2em',
    'disable' => 'csscrush__disable_px2em',
));

function csscrush__enable_px2em () {
    CssCrush_Function::register('px2em', 'csscrush_fn__px2em');
    CssCrush_Function::register('px2rem', 'csscrush_fn__px2rem');
}

function csscrush__disable_px2em () {
    CssCrush_Function::deRegister('px2em');
    CssCrush_Function::deRegister('px2rem');
}

function csscrush_fn__px2em ($input) {

    $base = 16;

    // Override default base if variable is set.
    if (isset(CssCrush::$process->variables['px2em__base'])) {
        $base = CssCrush::$process->variables['px2em__base'];
    }

    return csscrush__px2em($input, 'em', $base);
}

function csscrush_fn__px2rem ($input) {

    $base = 16;

    // Override default base if variable is set.
    if (isset(CssCrush::$process->variables['px2rem__base'])) {
        $base = CssCrush::$process->variables['px2rem__base'];
    }

    return csscrush__px2em($input, 'rem', $base);
}

function csscrush__px2em ($input, $unit, $default_base) {

    list($px, $base) = CssCrush_Function::parseArgsSimple($input) + array(
        16,
        $default_base,
    );

    return round($px / $base, 5) . $unit;
}
