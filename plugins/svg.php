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
 * circle, ellipse, rect, polygon, path, line, polyline, star.
 *
 *
 * @example
 *
 *     // Define SVG.
 *     @svg foo {
 *         type: ellipse;
 *         radius: 100 50;
 *         margin: 20;
 *         stroke: black;
 *         fill: red;
 *         fill-opacity: .5;
 *     }
 *
 *     // Embed SVG with svg() function (creates a data URI).
 *     body {
 *         background: beige svg(foo);
 *     }
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
                    array_change_key_case(CssCrush_Util::parseBlock($block, true));
            }
        ');
    }

    // Extract svg definitions.
    $process->stream->pregReplaceCallback($patt, $callback);
}

function csscrush_fn__svg ($input) {

    $svg_deinitions =& CssCrush::$process->misc->svg_defs;
    static $types = array(
        'circle' => array('element' => 'circle'),
        'ellipse' => array('element' => 'ellipse'),
        'rect' => array('element' => 'rect'),
        'polygon' => array('element' => 'polygon'),
        'path' => array('element' => 'path'),
        'line' => array('element' => 'line'),
        'polyline' => array('element' => 'polyline'),
        'star' => array('element' => 'path'),
    );

    $name = strtolower($input);

    // Bail if no SVG registered by this name.
    if (! isset($svg_deinitions[$name])) {
        return '';
    }

    $data = $svg_deinitions[$name];

    // Bail if type not recognised.
    $type = isset($data['type']) ? strtolower($data['type']) : 'rect';
    if (! isset($types[$type])) {
        return '';
    }

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
        'drop-shadow-opacity' => true,
        'sides' => true,
        'width' => true,
        'height' => true,
    );

    // Keys that go to direct to element attributes.
    static $attribute_props = array(
        'transform' => true,
    );

    // Initialize SVG attributes.
    $svg_attrs = array('xmlns' => 'http://www.w3.org/2000/svg');
    $svg_attrs['width'] = 0;
    $svg_attrs['height'] = 0;

    // Initialize element attributes.
    $element_attrs = array('id' => 'e') + array_intersect_key($data, $attribute_props);
    $element_data = array_intersect_key($data, $element_props);

    // Everything remaining is treated as CSS.
    $style_props = array_diff_key($data, $element_props, $attribute_props);

    // Prepopulate common attributes.
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

    // Drop-shadow filter.
    $filters = '';
    if (isset($element_data['drop-shadow'])) {
        $parts = csscrush__svg_parselist($element_data['drop-shadow'], false);
        list($ds_x, $ds_y, $ds_strength, $ds_color) = $parts += array(
            2, // x offset.
            2, // y offset.
            2, // strength.
            'black', // color.
        );
        $filters .= '<filter id="f">';
        $filters .= "<feGaussianBlur in=\"SourceAlpha\" stdDeviation=\"$ds_strength\"/>";
        $filters .= "<feOffset dx=\"$ds_x\" dy=\"$ds_y\" result=\"r1\"/>";
        $filters .= "<feFlood flood-color=\"$ds_color\"/>";
        $filters .= "<feComposite in2=\"r1\" operator=\"in\"/>";
        if (isset($element_data['drop-shadow-opacity'])) {
            $ds_opacity = $element_data['drop-shadow-opacity'];
            $filters .= '<feComponentTransfer>';
            $filters .= "<feFuncA type=\"linear\" slope=\"$ds_opacity\"/>";
            $filters .= '</feComponentTransfer>';
        }
        $filters .= '<feMerge>';
        $filters .= '<feMergeNode/>';
        $filters .= '<feMergeNode in="SourceGraphic"/>';
        $filters .= '</feMerge>';
        $filters .= '</filter>';
        $style_props['filter'] = 'url(#f)';
    }

    // Apply SVG callback.
    call_user_func_array("csscrush__svg_$type",
        array(&$element_data, &$element_attrs, &$svg_attrs, &$style_props));

    // Create SVG markup.
    $svg_attrs = CssCrush_Util::htmlAttributes($svg_attrs);
    $styles = array();
    foreach ($style_props as $property => $value) {
        $styles[] = "$property:$value";
    }
    $styles = implode(';', $styles);
    $element = $types[$type]['element'];
    $element_attrs = CssCrush_Util::htmlAttributes($element_attrs);

    $svg[] = "<svg$svg_attrs>";
    $svg[] = '<defs>';
    $svg[] = $filters;
    $svg[] = '<style type="text/css">';
    $svg[] = "#e{{$styles}}";
    $svg[] = '</style>';
    $svg[] = '</defs>';
    $svg[] = "<$element$element_attrs/>";
    $svg[] = '</svg>';

    // Debugging...
    // $code = implode("\n", $svg);
    // $test = '<pre>' . htmlspecialchars($code) . '</pre>';
    // echo $test, $code;

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
function csscrush__svg_polygon (&$element_data, &$element_attrs, &$svg_attrs, &$style_props) {

    // Ensure required attributes have defaults set.
    $element_data += array(
        'points' => '60,10 10,60 60,60',
        'width' => 70,
        'height' => 70,
    );

    $element_attrs['points'] = $element_data['points'];

    $svg_attrs['width'] = $element_data['width'];
    $svg_attrs['height'] = $element_data['height'];
}


/*
    Helpers.
*/
function csscrush__svg_parselist ($str, $numbers = true) {
    $list = preg_split('~ +~', trim($str));
    return $numbers ? array_map('floatval', $list) : $list;
}
