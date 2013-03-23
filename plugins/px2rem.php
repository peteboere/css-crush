<?php
/**
 * px2rem
 *
 * Functions for converting pixel values into rem (px2rem) or em (px2em) values.
 * For both functions the optional second argument is base font-size for calculation
 * (16px by default) though usually not required when converting to rem values.
 *
 * @before
 *     font-size: px2rem(16);
 *     font-size: px2em(11 13);
 *
 * @after
 *     font-size: 1rem;
 *     font-size: .84615em;
 */

CssCrush_Plugin::register( 'px2rem', array(
    'enable' => 'csscrush__enable_px2rem',
    'disable' => 'csscrush__disable_px2rem',
));

function csscrush__enable_px2rem () {
    CssCrush_Function::register('px2rem', 'csscrush_fn__px2rem');
    CssCrush_Function::register('px2em', 'csscrush_fn__px2em');
}

function csscrush__disable_px2rem () {
    CssCrush_Function::deRegister('px2rem');
    CssCrush_Function::deRegister('px2em');
}

function csscrush_fn__px2rem ($input) {
    return csscrush__px2rem($input, 'rem');
}

function csscrush_fn__px2em ($input) {
    return csscrush__px2rem($input, 'em');
}

function csscrush__px2rem ($input, $unit) {

    list($px, $base) = CssCrush_Function::parseArgsSimple($input) + array(
        16,
        16,
    );

    return round($px / $base, 5) . $unit;
}
