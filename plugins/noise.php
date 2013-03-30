<?php
/**
 * Functions for generating noise textures with SVG filters
 *
 * Supported in any browser that supports SVG filters (Currently IE10 and all other browsers).
 *
 * noise()
 * -------
 * Syntax:
 *      noise(
 *          [ <fill-color> || <size> ]?
 *          [ , <frequency> <octaves>? <sharpness>? ]?
 *          [ , <blend-mode> || <fade> ]?
 *          [ , <color-filter> <color-filter-value> ]?
 *      )
 *
 *      <fill-color>
 *          Any valid CSS color value.
 *      <size>
 *          Pixel size of canvas in format WxH (e.g. 320x480).
 *      <frequency>
 *          Number. Noise frequency; useful values are between 0 and 1.
 *          x and y frequencies can be specified by joining two numbers with a colon.
 *      <octaves>
 *          Number. Noise complexity.
 *      <sharpness>
 *          Noise sharpening; possible values:
 *          "normal", "sharpen"
 *      <blend-mode>
 *          Blend mode for overlaying noise filter; possible values:
 *          "normal", "multiply", "screen", "darken", "lighten"
 *      <fade>
 *          Ranged number (0-1). Opacity of noise effect.
 *      <color-filter>
 *          Color filter type; possible values:
 *          "hueRotate", "saturate"
 *      <color-filter-value>
 *          Mixed. For "hueRotate" a degree as number. For "saturate" a ranged number (0-1).
 *
 * Returns:
 *      A base64 encoded svg data-uri.
 *
 * References:
 *      http://www.w3.org/TR/SVG/filters.html
 *      http://srufaculty.sru.edu/david.dailey/svg/SVGOpen2010/Filters2.htm#S11
 *
 * Examples:
 *      // Grainy noise with 50% opacity and de-saturated.
 *      // Demonstrates the "default" keyword for skipping arguments.
 *      background-image: noise( slategray, default, .5, saturate 0 );
 *
 *      // Cloud effect.
 *      background: noise( 700x700 skyblue, .01 4 normal, screen, saturate 0 );
 *
 * turbulence()
 * ------------
 * As noise() except uses default turbulance type 'turbulance' and not 'fractalNoise'
 *
 * Syntax:
 *      See noise().
 *
 * Returns:
 *      See noise().
 *
 * References:
 *      See noise().
 *
 * Examples:
 *      // Typical turbulence effect.
 *      background: turbulence();
 *
 *      // Sand effect.
 *      background: turbulence( wheat 400x400, .35:.2 4 sharpen, normal, saturate .4 );
 */

CssCrush_Plugin::register('noise', array(
    'enable' => 'csscrush__enable_noise',
    'disable' => 'csscrush__disable_noise',
));

function csscrush__enable_noise () {
    CssCrush_Function::register('noise', 'csscrush_fn__noise');
    CssCrush_Function::register('turbulence', 'csscrush_fn__turbulence');
}

function csscrush__disable_noise () {
    CssCrush_Function::deRegister('noise');
    CssCrush_Function::deRegister('turbulence');
}

function csscrush_fn__noise ($input) {
    return csscrush__noise_generator($input, array(
        'type' => 'fractalNoise',
        'frequency' => .7,
        'sharpen' => 'sharpen',
        'dimensions' => array(150, 150),
    ));
}

function csscrush_fn__turbulence ($input) {
    return csscrush__noise_generator($input, array(
        'type' => 'turbulence',
        'frequency' => .01,
        'sharpen' => 'normal',
        'dimensions' => array(200, 200),
    ));
}

function csscrush__noise_generator ($input, $defaults) {

    $args = array_pad(CssCrush_Function::parseArgs($input), 4, 'default');

    $type = $defaults['type'];

    // Color-fill and dimensions.
    $fill_color = 'transparent';
    $dimensions = $defaults['dimensions'];
    if (($arg = array_shift($args)) !== 'default') {
        // May be a color function so explode(' ', $value) is not sufficient.
        foreach (CssCrush_Function::parseArgs($arg, true) as $part) {
            if (preg_match('~^(\d+)x(\d+)$~i', $part, $m)) {
                $dimensions = array_slice($m, 1);
            }
            else {
                $fill_color = $part;
            }
        }
    }

    // Frequency, octaves and sharpening.
    static $sharpen_modes = array('normal', 'sharpen');
    $frequency = $defaults['frequency'];
    $octaves = 1;
    $sharpen = $defaults['sharpen'];

    if (($arg = array_shift($args)) !== 'default') {
        foreach (explode(' ', $arg) as $index => $value) {
            switch ($index) {
                case 0:
                    // x and y frequency values can be specified by joining with a colon.
                    $frequency = str_replace(':', ',', $value);
                    break;
                case 1:
                case 2:
                    if (preg_match(CssCrush_Regex::$patt->rooted_number, $value)) {
                        $octaves = $value;
                    }
                    elseif (in_array($value, $sharpen_modes)) {
                        $sharpen = $value;
                    }
            }
        }
    }

    // Blend-mode and fade.
    static $blend_modes = array('normal', 'multiply', 'screen', 'darken', 'lighten');
    $blend_mode = 'normal';
    $opacity = 1;
    if (($arg = array_shift($args)) !== 'default') {
        foreach (explode(' ', $arg) as $part) {
            if (ctype_alpha($part)) {
                if (in_array($part, $blend_modes)) {
                    $blend_mode = $part;
                }
            }
            else {
                $opacity = $part;
            }
        }
    }

    // Color filter.
    static $color_filters = array('saturate', 'hueRotate', 'luminanceToAlpha');
    $color_filter = null;
    if (($arg = array_shift($args)) !== 'default') {
        // Saturate by default.
        $color_filter = array('saturate', 1);
        foreach (explode(' ', $arg) as $part) {
            if (ctype_alpha($part)) {
                if (in_array($part, $color_filters)) {
                    $color_filter[0] = $part;
                }
            }
            else {
                $color_filter[1] = $part;
            }
        }
    }

    // Creating the svg.
    $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$dimensions[0]}\" height=\"{$dimensions[1]}\">";
    $svg .= '<defs>';
    $svg .= "<filter id=\"f\" x=\"0\" y=\"0\" width=\"100%\" height=\"100%\">";

    $svg .= "<feTurbulence type=\"$type\" baseFrequency=\"$frequency\" numOctaves=\"$octaves\" result=\"f1\"/>";

    $component_adjustments = array();
    if ($sharpen === 'sharpen') {
        // It's more posterizing than sharpening, but it hits a sweet spot.
        $component_adjustments[] = "<feFuncR type=\"discrete\" tableValues=\"0 .5 1 1\"/>";
        $component_adjustments[] = "<feFuncG type=\"discrete\" tableValues=\"0 .5 1\"/>";
        // Some unpredictable results with this:
        // $component_adjustments[] = "<feFuncB type=\"discrete\" tableValues=\"0\"/>";
    }
    if ($opacity != '1') {
        $component_adjustments[] = "<feFuncA type=\"table\" tableValues=\"0 $opacity\"/>";
    }
    if ($component_adjustments) {
        $svg .= "<feComponentTransfer>";
        $svg .= implode('', $component_adjustments);
        $svg .= "</feComponentTransfer>";
    }

    if ($color_filter) {
        $svg .= "<feColorMatrix type=\"{$color_filter[0]}\" values=\"{$color_filter[1]}\"/>";
    }
    if ($blend_mode !== 'normal') {
        $svg .= "<feBlend mode=\"$blend_mode\" in=\"SourceGraphic\"/>";
    }
    $svg .= '</filter>';
    $svg .= '</defs>';
    $svg .= "<rect x=\"0\" y=\"0\" width=\"100%\" height=\"100%\" fill=\"$fill_color\"/>";
    $svg .= "<rect x=\"0\" y=\"0\" width=\"100%\" height=\"100%\" fill=\"$fill_color\" filter=\"url(#f)\"/>";
    $svg .= '</svg>';

    // Create data-uri url and return token label.
    $url = new CssCrush_Url('data:image/svg+xml;base64,' . base64_encode($svg));

    return $url->label;
}
