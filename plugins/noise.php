<?php
/**
 * Functions for generating noise textures with SVG filters
 *
 * @see docs/plugins/noise.md
 */
namespace CssCrush;

Plugin::register('noise', array(

    'enable' => function ($process) {
        $process->functions->add('noise', function ($input) {
            return noise_generator($input, array(
                'type' => 'fractalNoise',
                'frequency' => .7,
                'sharpen' => 'sharpen',
                'dimensions' => array(150, 150),
            ));
        });
        $process->functions->add('turbulence', function ($input) {
            return noise_generator($input, array(
                'type' => 'turbulence',
                'frequency' => .01,
                'sharpen' => 'normal',
                'dimensions' => array(200, 200),
            ));
        });
    }
));


function noise_generator($input, $defaults) {

    $args = array_pad(Functions::parseArgs($input), 4, 'default');

    $type = $defaults['type'];

    // Color-fill and dimensions.
    $fill_color = 'transparent';
    $dimensions = $defaults['dimensions'];
    if (($arg = array_shift($args)) !== 'default') {
        // May be a color function so explode(' ', $value) is not sufficient.
        foreach (Functions::parseArgs($arg, true) as $part) {
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
                    if (preg_match(Regex::$patt->rooted_number, $value)) {
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

    return Crush::$process->tokens->add(new Url('data:image/svg+xml;base64,' . base64_encode($svg)));
}
