<?php
/**
 * SVG.
 *
 * Define and embed SVG elements inside CSS.
 *
 * @svg
 * ----
 * @-rule for defining SVG shapes. Uses custom shortcut properites alongside,
 * standard SVG properties.
 *
 *
 * Element types
 * -------------
 * circle, ellipse, rect, polygon, path, line, polyline, star, text.
 *
 *
 * @example
 *
 *     // Define SVG.
 *     @svg foo {
 *         type: star;
 *         star-points: 5;
 *         radius: 100 50;
 *         margin: 20;
 *         stroke: black;
 *         fill: red;
 *         fill-opacity: .5;
 *     }
 *
 *     // Embed SVG with svg() function (generates a data URI).
 *     body {
 *         background: beige svg(foo);
 *     }
 *
 *
 * Issues
 * ------
 * Firefox does not allow linked images (or other svg) when SVG is in "svg as image" mode -
 * i.e. Used in an img tag or as a CSS background:
 * https://bugzilla.mozilla.org/show_bug.cgi?id=628747#c0
 *
 */

CssCrush_Plugin::register('svg', array(
    'enable' => 'csscrush__enable_svg',
    'disable' => 'csscrush__disable_svg',
));

function csscrush__enable_svg () {
    CssCrush_Hook::add('process_extract', 'csscrush__svg');
    CssCrush_Function::register('svg', 'csscrush_fn__svg');
}

function csscrush__disable_svg () {
    CssCrush_Hook::remove('process_extract', 'csscrush__svg');
    CssCrush_Function::deRegister('svg');
}

function csscrush__svg ($process) {

    static $callback, $patt;
    if (! $callback) {
        $patt = CssCrush_Regex::create('@svg +(<ident>) *\{ *(.*?) *\};?', 'iS');
        $callback = create_function('$m', '
            $name = strtolower($m[1]);
            $block = $m[2];
            if (! empty($name) && ! empty($block)) {
                CssCrush::$process->misc->svg_defs[$name] =
                    new CssCrush_Template($block);
            }
        ');
    }

    // Extract svg definitions.
    $process->stream->pregReplaceCallback($patt, $callback);
}

function csscrush_fn__svg ($input) {

    // Map types to elements.
    static $types = array(
        'circle' => 'circle',
        'ellipse' => 'ellipse',
        'rect' => 'rect',
        'polygon' => 'polygon',
        'path' => 'path',
        'line' => 'line',
        'polyline' => 'polyline',
        'star' => 'path',
        'text' => 'text',
    );

    // Keys that represent custom svg properties.
    static $element_props = array(
        'type' => true,
        'data' => true,
        'twist' => true,
        'radius' => true,
        'corner-radius' => true,
        'star-points' => true,
        'points' => true,
        'margin' => true,
        'drop-shadow' => true,
        'sides' => true,
        'width' => true,
        'height' => true,
        'text' => true,
    );

    // Keys that go to direct to element attributes.
    static $attribute_props = array(
        'transform' => true,
        'x' => true,
        'y' => true,
    );

    $args = CssCrush_Function::parseArgs($input);

    if (! isset($args[0])) {
        return '';
    }

    $name = strtolower(array_shift($args));

    // Bail if no SVG registered by this name.
    $svg_definitions =& CssCrush::$process->misc->svg_defs;
    if (! isset($svg_definitions[$name])) {
        return '';
    }

    // Apply args to template.
    $block = $svg_definitions[$name]->apply($args);

    // Parse the block into a keyed assoc array.
    $data = array_change_key_case(CssCrush_Util::parseBlock($block, true));

    // Bail if type not recognised.
    $type = isset($data['type']) ? strtolower($data['type']) : 'rect';
    if (! isset($types[$type])) {
        return '';
    }

    // Setup functions for using on values.
    // Note using custom versions of svg-*-gradient().
    static $generic_functions_patt, $fill_functions, $fill_functions_patt;
    if (! $generic_functions_patt) {
        $fill_functions = array(
            'svg-linear-gradient' => 'csscrush__svg_fn_linear_gradient',
            'svg-radial-gradient' => 'csscrush__svg_fn_radial_gradient',
            'pattern' => 'csscrush__svg_fn_pattern',
        );
        $generic_functions = array_diff_key(CssCrush_Function::$functions, $fill_functions);
        $generic_functions_patt = CssCrush_Regex::createFunctionPatt(array_keys($generic_functions), true);
        $fill_functions_patt = CssCrush_Regex::createFunctionPatt(array_keys($fill_functions));
    }

    // Placeholder for capturing generated fills.
    $fills = array(
        'gradients' => array(),
        'patterns' => array(),
    );
    foreach ($data as $property => &$value) {
        CssCrush_Function::executeOnString($value, $generic_functions_patt);

        // Only capturing fills for fill and stoke properties.
        if ($property === 'fill' || $property === 'stroke') {
            CssCrush_Function::executeOnString($value, $fill_functions_patt, $fill_functions, $fills);

            // If the value is a color with alpha component we split the color
            // and set the corresponding *-opacity property because Webkit doesn't
            // support rgba()/hsla() in SVG.
            if ($components = CssCrush_Color::colorSplit($value)) {
                list($color, $opacity) = $components;
                $data[$property] = $color;
                if ($opacity < 1) {
                    $data += array("$property-opacity" => $opacity);
                }
            }
        }
    }
    // Delete loop reference after use.
    unset($value);

    // Initialize SVG attributes.
    $svg_attrs = array('xmlns' => 'http://www.w3.org/2000/svg');
    $svg_attrs['width'] = 0;
    $svg_attrs['height'] = 0;
    if ($fills['patterns']) {
        $svg_attrs['xmlns:xlink'] ="http://www.w3.org/1999/xlink";
    }

    // Filter off prefixed properties that are for the svg element or @font-face.
    $svg_styles = array();
    $face_styles = array();
    foreach ($data as $property => $value) {
        if (strpos($property, 'svg-') === 0) {
            $svg_styles[substr($property, 4)] = $value;
            unset($data[$property]);
        }
        elseif (strpos($property, 'face-') === 0) {
            $face_styles[substr($property, 5)] = $value;
            unset($data[$property]);
        }
    }

    // Initialize element attributes.
    $element_name = $types[$type];
    $element_attrs = array_intersect_key($data, $attribute_props);
    $element_data = array_intersect_key($data, $element_props);

    // Everything remaining is treated as CSS.
    $styles = array_diff_key($data, $element_props, $attribute_props);

    // Prepopulate common attributes.
    csscrush__svg_decorate($element_data);

    // Filters.
    $filter = csscrush__svg_filter($element_data, $styles);

    // Apply SVG callback.
    call_user_func_array("csscrush__svg_$type",
        array(&$element_data, &$element_attrs, &$svg_attrs, &$styles, &$element_name));

    // Flatten CSS styles.
    $styles_data = array(
        'svg' => $svg_styles,
        $element_name => $styles
    );
    $styles_out = '';
    foreach ($styles_data as $selector => $declarations) {
        $pairs = array();
        foreach ($declarations as $property => $value) {
            $pairs[] = "$property:$value";
        }
        if ($pairs) {
            $styles_out .= $selector . '{' . implode(';', $pairs) . '}';
        }
    }
    $styles_out = CssCrush::$process->restoreTokens($styles_out, 'u');

    $element_attrs = CssCrush_Util::htmlAttributes($element_attrs);
    $svg_attrs = CssCrush_Util::htmlAttributes($svg_attrs);

    // Create SVG markup.
    $svg[] = "<svg$svg_attrs>";
    // $svg[] = '<defs>';
    $svg[] = implode($fills['gradients']);
    $svg[] = implode($fills['patterns']);
    $svg[] = $filter;
    if ($styles_out) {
        $svg[] = '<style type="text/css"><![CDATA[';
        $svg[] = $styles_out;
        $svg[] = ']]></style>';
    }
    // $svg[] = '</defs>';
    if ($element_name === 'text') {
        $svg[] = "<text$element_attrs>{$element_data['text']}</text>";
    }
    else {
        $svg[] = "<$element_name$element_attrs/>";
    }
    $svg[] = '</svg>';

    // Debugging...
    $code = implode("\n", $svg);
    $test = '<pre>' . htmlspecialchars($code) . '</pre>';
    echo $test, $code;

    // Create data-uri url and return token label.
    $url = new CssCrush_Url('data:image/svg+xml;base64,' . base64_encode(implode('', $svg)));

    return $url->label;
}


/*
    Circle callback.
*/
function csscrush__svg_circle (&$element_data, &$element_attrs, &$svg_attrs) {

    // Ensure required attributes have defaults set.
    $element_data += array(
        'radius' => 50,
    );

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element_data['margin'];

    $element_attrs['r'] = $element_data['radius'];

    $radius = $element_data['radius'];
    $diameter = $radius * 2;

    $element_attrs['cx'] = $margin_left + $radius;
    $element_attrs['cy'] = $margin_top + $radius;

    $svg_attrs['width'] = $margin_left + $diameter + $margin_right;
    $svg_attrs['height'] = $margin_top + $diameter + $margin_bottom;
}

/*
    Rect callback.
*/
function csscrush__svg_rect (&$element_data, &$element_attrs, &$svg_attrs) {

    // Ensure required attributes have defaults set.
    $element_data += array(
        'width' => 50,
        'height' => 50,
    );

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element_data['margin'];

    $element_attrs['x'] = $margin_left;
    $element_attrs['y'] = $margin_top;
    $element_attrs['width'] = $element_data['width'];
    $element_attrs['height'] = $element_data['height'];

    if (isset($element_data['corner-radius'])) {
        $args = csscrush__svg_parselist($element_data['corner-radius']);
        $element_attrs['rx'] = isset($args[0]) ? $args[0] : 0;
        $element_attrs['ry'] = isset($args[1]) ? $args[1] : $args[0];
    }

    $svg_attrs['width'] = $margin_left + $element_data['width'] + $margin_right;
    $svg_attrs['height'] = $margin_top + $element_data['height'] + $margin_bottom;
}

/*
    Ellipse callback.
*/
function csscrush__svg_ellipse (&$element_data, &$element_attrs, &$svg_attrs) {

    // Ensure required attributes have defaults set.
    $element_data += array(
        'radius' => '100 50',
    );

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element_data['margin'];

    $radius = csscrush__svg_parselist($element_data['radius']);
    $radius_x = $radius[0];
    $radius_y = isset($radius[1]) ? $radius[1] : $radius[0];

    $element_attrs['rx'] = $radius_x;
    $element_attrs['ry'] = $radius_y;

    $element_attrs['cx'] = $margin_left + $radius_x;
    $element_attrs['cy'] = $margin_top + $radius_y;

    $svg_attrs['width'] = $margin_left + ($radius_x * 2) + $margin_right;
    $svg_attrs['height'] = $margin_top + ($radius_y * 2) + $margin_bottom;
}

/*
    Path callback.
*/
function csscrush__svg_path (&$element_data, &$element_attrs, &$svg_attrs, &$style_props) {

    // Ensure required attributes have defaults set.
    $element_data += array(
        'data' => 'M 10,10 l 10,0 l 0,10 l 10,0 l 0,10',
        'width' => 40,
        'height' => 40,
    );

    // Unclosed paths have implicit fill.
    $style_props += array(
        'fill' => 'none',
    );

    $element_attrs['d'] = $element_data['data'];

    $svg_attrs['width'] = $element_data['width'];
    $svg_attrs['height'] = $element_data['height'];
}

/*
    Polyline callback.
*/
function csscrush__svg_polyline (&$element_data, &$element_attrs, &$svg_attrs, &$style_props) {

    // Ensure required attributes have defaults set.
    $element_data += array(
        'points' => '20,20 40,20 40,40 60,40 60,60',
        'width' => 80,
        'height' => 80,
    );

    // Polylines have implicit fill.
    $style_props += array(
        'fill' => 'none',
    );

    $element_attrs['points'] = $element_data['points'];

    $svg_attrs['width'] = $element_data['width'];
    $svg_attrs['height'] = $element_data['height'];
}

/*
    Line callback.
*/
function csscrush__svg_line (&$element_data, &$element_attrs, &$svg_attrs, &$style_props) {

    // Ensure required attributes have defaults set.
    $element_data += array(
        'points' => '10,10 70,70',
        'width' => 80,
        'height' => 80,
    );

    // Set a default stroke.
    $style_props += array(
        'stroke' => '#000',
    );

    $points = preg_split('~[, ]+~', $element_data['points']);
    $element_attrs['x1'] = $points[0];
    $element_attrs['y1'] = $points[1];
    $element_attrs['x2'] = $points[2];
    $element_attrs['y2'] = $points[3];

    $svg_attrs['width'] = $element_data['width'];
    $svg_attrs['height'] = $element_data['height'];
}

/*
    Polygon callback.
*/
function csscrush__svg_polygon (&$element_data, &$element_attrs, &$svg_attrs, &$style_props, &$element_name) {

    // Check for points.
    if (isset($element_data['points'])) {

        $element_data += array(
            'width' => 70,
            'height' => 70,
        );

        $element_attrs['points'] = $element_data['points'];

        $svg_attrs['width'] = $element_data['width'];
        $svg_attrs['height'] = $element_data['height'];
    }

    // Fallback with sides.
    else {

        // Switch to path element.
        $element_name = 'path';

        $element_data += array(
            'sides' => 3,
            'radius' => 100,
        );

        list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element_data['margin'];

        $radius = csscrush__svg_parselist($element_data['radius']);
        $radius = $radius[0];

        $cx = $radius + $margin_left;
        $cy = $radius + $margin_top;
        $sides = $element_data['sides'];

        $element_attrs['d'] = csscrush__svg_starpath($cx, $cy, $sides, $radius);

        $svg_attrs['width'] = ($radius * 2) + $margin_left + $margin_right;
        $svg_attrs['height'] = ($radius * 2) + $margin_top + $margin_bottom;
    }
}

/*
    Star callback.
*/
function csscrush__svg_star (&$element_data, &$element_attrs, &$svg_attrs, &$style_props) {

    // Minimum required attributes have defaults.
    $element_data += array(
        'star-points' => 4,
        'radius' => '50 30',
        'twist' => 0,
    );

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element_data['margin'];

    $radius = csscrush__svg_parselist($element_data['radius']);
    if (! isset($radius[1])) {
        $radius[1] = ($radius[0] / 2);
    }
    list($outer_r, $inner_r) = $radius;

    $cx = $outer_r + $margin_left;
    $cy = $outer_r + $margin_top;
    $points = $element_data['star-points'];
    $twist = $element_data['twist'];

    $element_attrs['d'] = csscrush__svg_starpath($cx, $cy, $points, $outer_r, $inner_r, $twist);

    $svg_attrs['width'] = $margin_left + ($outer_r * 2) + $margin_left;
    $svg_attrs['height'] = $margin_top + ($outer_r * 2) + $margin_bottom;
}

/*
    Text callback.
*/
function csscrush__svg_text (&$element_data, &$element_attrs, &$svg_attrs, &$style_props) {

    // Minimum required attributes have defaults.
    $element_data += array(
        'x' => 0,
        'y' => 0,
        'width' => 100,
        'height' => 100,
        'text' => '',
    );

    $text = CssCrush::$process->restoreTokens($element_data['text'], 's');

    // Remove open and close quotes.
    $text = substr($text, 1, strlen($text) - 2);

    // Convert CSS unicode sequences to XML unicode.
    $text = preg_replace('~\\\\([[:xdigit:]]{2,6})~', '&#x$1;', $text);

    // Remove excape slashes and encode meta entities.
    $text = htmlentities(stripslashes($text), ENT_QUOTES, 'UTF-8', false);
    $element_data['text'] = $text;

    $svg_attrs['width'] = $element_data['width'];
    $svg_attrs['height'] = $element_data['height'];
}



/*
    Star/polygon path builder.

    Adapted from http://svg-whiz.com/svg/StarMaker.svg by Doug Schepers.
*/
function csscrush__svg_starpath ($cx, $cy, $points, $outer_r, $inner_r = null, $twist = 0, $orient = 'point') {

    $data[] = 'M';

    // Enforce minimum number of points.
    $points = max(3, $points);

    for ($s = 0; $points >= $s; $s++) {

        // Outer angle.
        $outer_angle = 2.0 * M_PI * ($s / $points);

        if ($orient === 'point') {
            $outer_angle -= (M_PI / 2);
        }
        elseif ($orient === 'edge') {
            $outer_angle = ($outer_angle + (M_PI / $points)) - (M_PI / 2);
        }

        // Outer point based on outer angle.
        $x = ( $outer_r * cos($outer_angle) ) + $cx;
        $y = ( $outer_r * sin($outer_angle) ) + $cy;

        if ($points != $s) {
            $data[] = array($x, $y);
        }

        // If star shape is required need inner angles too.
        if ($inner_r != null && $points != $s) {

            $inner_angle = (2 * M_PI * ($s / $points)) + (M_PI / $points);

            if ($orient === 'point') {
                $inner_angle -= (M_PI / 2);
            }
            $inner_angle += $twist;

            $ix = ( $inner_r * cos($inner_angle) ) + $cx;
            $iy = ( $inner_r * sin($inner_angle) ) + $cy;

            $data[] = array($ix, $iy);
        }
        elseif ($points == $s) {

            $data[] = 'Z';
        }
    }

    // Round path coordinates down to save bytes.
    foreach ($data as &$item) {
        if (is_array($item)) {
            $item = round($item[0], 2) . ',' . round($item[1], 2);
        }
    }

    return implode(' ', $data);
}


function csscrush__svg_filter (&$element_data, &$style_props) {

    $filter = '';

    if (isset($element_data['drop-shadow'])) {

        $parts = csscrush__svg_parselist($element_data['drop-shadow'], false);

        list($ds_x, $ds_y, $ds_strength, $ds_color) = $parts += array(
            2, // x offset.
            2, // y offset.
            2, // strength.
            'black', // color.
        );

        // Opacity.
        $drop_shadow_opacity = null;
        if ($color_components = CssCrush_Color::colorSplit($ds_color)) {
            list($ds_color, $drop_shadow_opacity) = $color_components;
        }

        $filter = '<filter id="f" x="-50%" y="-50%" width="200%" height="200%">';
        $filter .= "<feGaussianBlur in=\"SourceAlpha\" stdDeviation=\"$ds_strength\"/>";
        $filter .= "<feOffset dx=\"$ds_x\" dy=\"$ds_y\" result=\"r1\"/>";
        $filter .= "<feFlood flood-color=\"$ds_color\"/>";
        $filter .= "<feComposite in2=\"r1\" operator=\"in\"/>";
        if (isset($drop_shadow_opacity)) {
            $filter .= '<feComponentTransfer>';
            $filter .= "<feFuncA type=\"linear\" slope=\"$drop_shadow_opacity\"/>";
            $filter .= '</feComponentTransfer>';
        }
        $filter .= '<feMerge>';
        $filter .= '<feMergeNode/>';
        $filter .= '<feMergeNode in="SourceGraphic"/>';
        $filter .= '</feMerge>';
        $filter .= '</filter>';
        $style_props['filter'] = 'url(#f)';
    }

    return $filter;
}


function csscrush__svg_decorate (&$element_data) {

    if (isset($element_data['margin'])) {

        $parts = csscrush__svg_parselist($element_data['margin']);
        $count = count($parts);
        if ($count === 1) {
            $element_data['margin'] = array($parts[0], $parts[0], $parts[0], $parts[0]);
        }
        elseif ($count === 2) {
            $element_data['margin'] = array($parts[0], $parts[1], $parts[0], $parts[1]);
        }
        elseif ($count === 3) {
            $element_data['margin'] = array($parts[0], $parts[1], $parts[2], $parts[1]);
        }
        else {
            $element_data['margin'] = $parts;
        }
    }
    else {
        $element_data['margin'] = array(0,0,0,0);
    }
}


/*
    Custom versions of svg-*-gradient() for integrating.
*/
function csscrush__svg_fn_linear_gradient ($input, &$fills) {

    static $booted;
    if (! $booted) {
        // Relies on functions from svg-gradients plugin.
        CssCrush_Plugin::load('svg-gradients');
    }
    $generated_gradient = csscrush__create_svg_linear_gradient($input);
    $fills['gradients'][] = reset($generated_gradient);

    return 'url(#' . key($generated_gradient) . ')';
}


function csscrush__svg_fn_radial_gradient ($input, &$fills) {

    static $booted;
    if (! $booted) {
        // Relies on functions from svg-gradients plugin.
        CssCrush_Plugin::load('svg-gradients');
    }
    $generated_gradient = csscrush__create_svg_radial_gradient($input);
    $fills['gradients'][] = reset($generated_gradient);

    return 'url(#' . key($generated_gradient) . ')';
}


function csscrush__svg_fn_pattern ($input, &$fills) {

    static $uid = 0;
    $pid = 'p' . (++$uid);

    // Get args in order with defaults.
    list($url, $transform_list, $width, $height, $x, $y) =
        CssCrush_Function::parseArgs($input) +
        array('', '', 0, 0, 0, 0);

    $url = CssCrush::$process->popToken($url);

    // If $width or $height is not specified get image dimensions the slow way.
    if (! $width || ! $height) {
        if (in_array($url->protocol, array('http', 'https'))) {
            $file = $url->value;
        }
        elseif ($url->isRelative || $url->isRooted) {
            $file = CssCrush::$config->docRoot .
                ($url->isRelative ? $url->toRoot()->simplify()->value : $url->value);
        }
        list($width, $height) = getimagesize($file);
    }

    // If a data-uri function has been used.
    if ($url->convertToData) {
        $url->toData();
    }

    $transform_list = $transform_list ? " patternTransform=\"$transform_list\"" : '';
    $generated_pattern = "<pattern id=\"$pid\" patternUnits=\"userSpaceOnUse\" width=\"$width\" height=\"$height\"$transform_list>";
    $generated_pattern .= "<image xlink:href=\"{$url->value}\" x=\"$x\" y=\"$y\" width=\"$width\" height=\"$height\"/>";
    $generated_pattern .= '</pattern>';

    $fills['patterns'][] = $generated_pattern;
    return 'url(#' . $pid . ')';
}


/*
    Helpers.
*/
function csscrush__svg_parselist ($str, $numbers = true) {
    $list = preg_split('~ +~', trim($str));
    return $numbers ? array_map('floatval', $list) : $list;
}
