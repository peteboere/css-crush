<?php
/**
 * Define and embed simple SVG elements, paths and effects inside CSS
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
 *     // Embed SVG with svg() function (generates an svg file in the
 *     // output directory).
 *     body {
 *         background: beige svg(foo);
 *     }
 *     // As above but creates a data URI instead of an svg file.
 *     body {
 *         background: beige svg-data(foo);
 *     }
 *
 * @example
 *
 *     // Skewed circle with radial gradient fill and drop shadow.
 *     @svg circle {
 *         type: circle;
 *         transform: skewX(30);
 *         diameter: 60;
 *         margin: 20;
 *         fill: svg-radial-gradient(at top right, gold 50%, red);
 *         drop-shadow: 2 2 0 rgba(0,0,0,1);
 *         }
 *
 * @example
 *
 *     // 8-sided polygon with an image fill.
 *     // Note: images usually have to be converted to data URIs, see known issues below.
 *     @svg pattern {
 *         type: polygon;
 *         sides: 8;
 *         diameter: 180;
 *         margin: 20;
 *         fill: pattern(data-uri(kitten.jpg), scale(1) translate(-100 0));
 *         fill-opacity: .8;
 *         }
 *
 * @known-issues
 *
 * Firefox does not allow linked images (or other svg) when svg is in "svg as image" mode -
 * i.e. Used in an img tag or as a CSS background:
 * https://bugzilla.mozilla.org/show_bug.cgi?id=628747#c0
 *
 */

CssCrush_Plugin::register('svg', array(
    'enable' => 'csscrush__enable_svg',
    'disable' => 'csscrush__disable_svg',
));

function csscrush__enable_svg () {
    CssCrush_Hook::add('process_extract', 'csscrush__svg_extract');
    CssCrush_Function::register('svg', 'csscrush_fn__svg');
    CssCrush_Function::register('svg-data', 'csscrush_fn__svg_data');
}

function csscrush__disable_svg () {
    CssCrush_Hook::remove('process_extract', 'csscrush__svg_extract');
    CssCrush_Function::deRegister('svg');
    CssCrush_Function::deRegister('svg-data');
}

function csscrush_fn__svg ($input) {

    return csscrush__svg_generator($input, 'svg');
}

function csscrush_fn__svg_data ($input) {

    return csscrush__svg_generator($input, 'svg-data');
}

function csscrush__svg_extract ($process) {

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

function csscrush__svg_generator ($input, $fn_name) {

    $process = CssCrush::$process;

    $cache_key = $fn_name . $input;
    if (isset($process->misc->svg_cache[$cache_key])) {

        return $process->misc->svg_cache[$cache_key];
    }

    // Map types to element names.
    static $schemas;
    if (! $schemas) {
        $schemas = array(
            'circle' => array(
                'tag' => 'circle',
                'attrs' => 'cx cy r',
            ),
            'ellipse' => array(
                'tag' => 'ellipse',
                'attrs' => 'cx cy rx ry',
            ),
            'rect' => array(
                'tag' => 'rect',
                'attrs' => 'x y rx ry width height',
            ),
            'polygon' => array(
                'tag' => 'polygon',
                'attrs' => 'points',
            ),
            'line' => array(
                'tag' => 'line',
                'attrs' => 'x1 y1 x2 y2',
            ),
            'polyline' => array(
                'tag' => 'polyline',
                'attrs' => 'points',
            ),
            'path' => array(
                'tag' => 'path',
                'attrs' => 'd',
            ),
            'star' => array(
                'tag' => 'path',
                'attrs' => '',
            ),
            'text' => array(
                'tag' => 'text',
                'attrs' => 'x y dx dy rotate',
            ),
        );

        // Convert attributes to keyed array.
        // Add global attributes.
        foreach ($schemas as $type => &$schema) {
            $schema['attrs'] = array_flip(explode(' ', $schema['attrs']))
                + array(
                    'transform' => true,
                );
        }
    }

    // Non standard attributes.
    static $custom_attrs = array(
        'type' => true,
        'data' => true,
        'twist' => true,
        'diameter' => true,
        'corner-radius' => true,
        'star-points' => true,
        'margin' => true,
        'drop-shadow' => true,
        'sides' => true,
        'text' => true,
        'width' => true,
        'height' => true,
    );

    // Bail if no args.
    $args = CssCrush_Function::parseArgs($input);
    if (! isset($args[0])) {

        return '';
    }

    $name = strtolower(array_shift($args));

    // Bail if no SVG registered by this name.
    $svg_defs =& $process->misc->svg_defs;
    if (! isset($svg_defs[$name])) {

        return '';
    }

    // Apply args to template.
    $block = $svg_defs[$name]->apply($args);

    // Parse the block into a keyed assoc array.
    $raw_data = array_change_key_case(CssCrush_Util::parseBlock($block, true));

    // Resolve the type.
    // Bail if type not recognised.
    $type = isset($raw_data['type']) ? strtolower($raw_data['type']) : 'rect';
    if (! isset($schemas[$type])) {

        return '';
    }

    // Create element object for attaching all required rendering data.
    $element = (object) array(
        'tag' => $schemas[$type]['tag'],
        'fills' => array(
            'gradients' => array(),
            'patterns' => array(),
        ),
        'filters' => array(),
        'data' => array(),
        'attrs' => array(),
        'styles' => array(),
        'svg_attrs' => array(
            'xmlns' => 'http://www.w3.org/2000/svg',
        ),
        'svg_styles' => array(),
        'face_styles' => array(),
    );

    // Filter off prefixed properties that are for the svg element or @font-face.
    foreach ($raw_data as $property => $value) {
        if (strpos($property, 'svg-') === 0) {
            $element->svg_styles[substr($property, 4)] = $value;
            unset($raw_data[$property]);
        }
        elseif (strpos($property, 'face-') === 0) {
            $element->face_styles[substr($property, 5)] = $value;
            unset($raw_data[$property]);
        }
    }

    csscrush__svg_apply_css_funcs($element, $raw_data);

    // Initialize element attributes.
    $element->attrs = array_intersect_key($raw_data, $schemas[$type]['attrs']);
    $element->data = array_intersect_key($raw_data, $custom_attrs);

    // Everything else is treated as CSS.
    $element->styles = array_diff_key($raw_data, $custom_attrs, $schemas[$type]['attrs']);

    // Pre-populate common attributes.
    csscrush__svg_preprocess($element);

    // Filters.
    csscrush__svg_apply_filters($element);

    // Apply element type callback.
    call_user_func("csscrush__svg_$type", $element);

    // Apply optimizations.
    csscrush__svg_compress($element);

    // Build markup.
    $svg = csscrush__svg_render($element);

    // Debugging...
    // $code = implode("\n", $svg);
    // $test = '<pre>' . htmlspecialchars($code) . '</pre>';
    // echo $test;

    // Either write to a file.
    if ($fn_name === 'svg' && $process->ioContext === 'file') {

        $flattened_svg = implode("\n", $svg);

        // Create fingerprint for the created file.
        $fingerprint = substr(md5($flattened_svg), 0, 7);
        $generated_filename = "svg-$name-$fingerprint.svg";

        $generated_path = $process->output->dir . '/' . $generated_filename;
        file_put_contents($generated_path, $flattened_svg, LOCK_EX);

        // Write to the same directory as the output css.
        $generated_url = $generated_filename;
        $url = new CssCrush_Url($generated_url);
        $url->noRewrite = true;
    }
    // Or create data uri.
    else {
        $url = new CssCrush_Url('data:image/svg+xml;base64,' . base64_encode(implode('', $svg)));
    }

    // Cache the output URL.
    $process->misc->svg_cache[$cache_key] = $url->label;

    return $url->label;
}


/*
    Circle callback.
*/
function csscrush__svg_circle ($element) {

    // Ensure required attributes have defaults set.
    $element->data += array(
        'diameter' => 50,
    );

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element->data['margin'];

    $element->attrs['r'] =
    $radius = csscrush__ifset($element->attrs['r'], $element->data['diameter'] / 2);

    $diameter = $radius * 2;

    $element->attrs['cx'] = csscrush__ifset($element->attrs['cx'], $margin_left + $radius);
    $element->attrs['cy'] = csscrush__ifset($element->attrs['cy'], $margin_top + $radius);

    $element->svg_attrs['width'] = $margin_left + $diameter + $margin_right;
    $element->svg_attrs['height'] = $margin_top + $diameter + $margin_bottom;
}

/*
    Rect callback.
*/
function csscrush__svg_rect ($element) {

    $element->data += array(
        'width' => 50,
        'height' => 50,
    );

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element->data['margin'];

    $element->attrs['x'] = $margin_left;
    $element->attrs['y'] = $margin_top;
    $element->attrs['width'] = $element->data['width'];
    $element->attrs['height'] = $element->data['height'];

    if (isset($element->data['corner-radius'])) {
        $args = csscrush__svg_parselist($element->data['corner-radius']);
        $element->attrs['rx'] = isset($args[0]) ? $args[0] : 0;
        $element->attrs['ry'] = isset($args[1]) ? $args[1] : $args[0];
    }

    $element->svg_attrs['width'] = $margin_left + $element->data['width'] + $margin_right;
    $element->svg_attrs['height'] = $margin_top + $element->data['height'] + $margin_bottom;
}

/*
    Ellipse callback.
*/
function csscrush__svg_ellipse ($element) {

    $element->data += array(
        'diameter' => '100 50',
    );

    if (! isset($element->attrs['rx']) && ! isset($element->attrs['ry'])) {
        $diameter = csscrush__svg_parselist($element->data['diameter']);
        $element->attrs['rx'] = $diameter[0] / 2;
        $element->attrs['ry'] = isset($diameter[1]) ? $diameter[1] / 2 : $diameter[0] / 2;
    }

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element->data['margin'];

    $element->attrs['cx'] = $margin_left + $element->attrs['rx'];
    $element->attrs['cy'] = $margin_top + $element->attrs['ry'];

    $element->svg_attrs['width'] = $margin_left + ($element->attrs['rx'] * 2) + $margin_right;
    $element->svg_attrs['height'] = $margin_top + ($element->attrs['ry'] * 2) + $margin_bottom;
}

/*
    Path callback.
*/
function csscrush__svg_path ($element) {

    // Ensure minimum required attributes have defaults set.
    $element->data += array(
        'd' => 'M 10,10 l 10,0 l 0,10 l 10,0 l 0,10',
    );

    // Unclosed paths have implicit fill.
    $element->styles += array(
        'fill' => 'none',
    );
}

/*
    Polyline callback.
*/
function csscrush__svg_polyline ($element) {

    // Ensure required attributes have defaults set.
    $element->data += array(
        'points' => '20,20 40,20 40,40 60,40 60,60',
    );

    // Polylines have implicit fill.
    $element->styles += array(
        'fill' => 'none',
    );
}

/*
    Line callback.
*/
function csscrush__svg_line ($element) {

    // Set a default stroke.
    $element->styles += array(
        'stroke' => '#000',
    );

    $element->attrs += array(
        'x1' => 0,
        'x2' => 0,
        'y1' => 0,
        'y2' => 0,
    );
}

/*
    Polygon callback.
*/
function csscrush__svg_polygon ($element) {

    if (! isset($element->attrs['points'])) {

        // Switch to path element.
        $element->tag = 'path';

        $element->data += array(
            'sides' => 3,
            'diameter' => 100,
        );

        list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element->data['margin'];

        $diameter = csscrush__svg_parselist($element->data['diameter']);
        $diameter = $diameter[0];
        $radius = $diameter / 2;

        $cx = $radius + $margin_left;
        $cy = $radius + $margin_top;
        $sides = $element->data['sides'];

        $element->attrs['d'] = csscrush__svg_starpath($cx, $cy, $sides, $radius);

        $element->svg_attrs['width'] = $diameter + $margin_left + $margin_right;
        $element->svg_attrs['height'] = $diameter + $margin_top + $margin_bottom;
    }
}

/*
    Star callback.
*/
function csscrush__svg_star ($element) {

    // Minimum required attributes have defaults.
    $element->data += array(
        'star-points' => 4,
        'diameter' => '50 30',
        'twist' => 0,
    );

    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $element->data['margin'];

    $diameter = csscrush__svg_parselist($element->data['diameter']);
    if (! isset($diameter[1])) {
        $diameter[1] = ($diameter[0] / 2);
    }
    $outer_r = $diameter[0] / 2;
    $inner_r = $diameter[1] / 2;

    $cx = $outer_r + $margin_left;
    $cy = $outer_r + $margin_top;
    $points = $element->data['star-points'];
    $twist = $element->data['twist'] * 10;

    $element->attrs['d'] = csscrush__svg_starpath($cx, $cy, $points, $outer_r, $inner_r, $twist);

    $element->svg_attrs['width'] = $margin_left + ($outer_r * 2) + $margin_left;
    $element->svg_attrs['height'] = $margin_top + ($outer_r * 2) + $margin_bottom;
}

/*
    Text callback.
    Warning: Very limited for svg-as-image situations.
*/
function csscrush__svg_text ($element) {

    // Minimum required attributes have defaults.
    $element->data += array(
        'x' => 0,
        'y' => 0,
        'width' => 100,
        'height' => 100,
        'text' => '',
    );

    $text = CssCrush::$process->restoreTokens($element->data['text'], 's');

    // Remove open and close quotes.
    $text = substr($text, 1, strlen($text) - 2);

    // Convert CSS unicode sequences to XML unicode.
    $text = preg_replace('~\\\\([[:xdigit:]]{2,6})~', '&#x$1;', $text);

    // Remove excape slashes and encode meta entities.
    $text = htmlentities(stripslashes($text), ENT_QUOTES, 'UTF-8', false);
    $element->data['text'] = $text;

    $element->svg_attrs['width'] = $element->data['width'];
    $element->svg_attrs['height'] = $element->data['height'];
}



/*
    Star/polygon path builder.

    Adapted from http://svg-whiz.com/svg/StarMaker.svg by Doug Schepers.
*/
function csscrush__svg_starpath ($cx, $cy, $points, $outer_r, $inner_r = null, $twist = 0, $orient = 'point') {

    $d = array();

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
            $d[] = "$x,$y";
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

            $d[] = "$ix,$iy";
        }
    }

    return 'M' . implode(' ', $d) . 'Z';
}

function csscrush__svg_apply_filters ($element) {

    if (isset($element->data['drop-shadow'])) {

        $parts = csscrush__svg_parselist($element->data['drop-shadow'], false);

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

        $element->styles['filter'] = 'url(#f)';
        $element->filters[] = $filter;
    }
}

function csscrush__svg_preprocess ($element) {

    if (isset($element->data['margin'])) {

        $margin =& $element->data['margin'];

        $parts = csscrush__svg_parselist($margin);
        $count = count($parts);
        if ($count === 1) {
            $margin = array($parts[0], $parts[0], $parts[0], $parts[0]);
        }
        elseif ($count === 2) {
            $margin = array($parts[0], $parts[1], $parts[0], $parts[1]);
        }
        elseif ($count === 3) {
            $margin = array($parts[0], $parts[1], $parts[2], $parts[1]);
        }
        else {
            $margin = $parts;
        }
    }
    else {
        $element->data['margin'] = array(0,0,0,0);
    }

    // 'Unzip' string tokens on data attributes.
    foreach (array('points', 'd') as $point_data_attr) {

        if (isset($element->attrs[$point_data_attr])) {

            $value = $element->attrs[$point_data_attr];

            if (CssCrush::$process->isToken($value, 's')) {
                $element->attrs[$point_data_attr] =
                    trim(CssCrush::$process->fetchToken($value), '"\'');;
            }
        }
    }

    if (isset($element->data['width'])) {
        $element->svg_attrs['width'] = $element->data['width'];
    }
    if (isset($element->data['height'])) {
        $element->svg_attrs['height'] = $element->data['height'];
    }
}

function csscrush__svg_apply_css_funcs ($element, &$raw_data) {

    // Setup functions for using on values.
    // Note using custom versions of svg-*-gradient().
    static $generic_functions_patt, $fill_functions, $fill_functions_patt;
    if (! $generic_functions_patt) {
        $fill_functions = array(
            'svg-linear-gradient' => 'csscrush__svg_fn_linear_gradient',
            'svg-radial-gradient' => 'csscrush__svg_fn_radial_gradient',
            'pattern' => 'csscrush__svg_fn_pattern',
        );
        $generic_functions =
            array_diff_key(CssCrush_Function::$functions, $fill_functions);
        $generic_functions_patt = CssCrush_Regex::createFunctionPatt(
            array_keys($generic_functions), array('bare_paren' => true));
        $fill_functions_patt = CssCrush_Regex::createFunctionPatt(
            array_keys($fill_functions));
    }

    foreach ($raw_data as $property => &$value) {
        CssCrush_Function::executeOnString($value, $generic_functions_patt);

        // Only capturing fills for fill and stoke properties.
        if ($property === 'fill' || $property === 'stroke') {
            CssCrush_Function::executeOnString(
                $value, $fill_functions_patt, $fill_functions, $element);

            // If the value is a color with alpha component we split the color
            // and set the corresponding *-opacity property because Webkit doesn't
            // support rgba()/hsla() in SVG.
            if ($components = CssCrush_Color::colorSplit($value)) {
                list($color, $opacity) = $components;
                $raw_data[$property] = $color;
                if ($opacity < 1) {
                    $raw_data += array("$property-opacity" => $opacity);
                }
            }
        }
    }
}

function csscrush__svg_compress ($element) {

    foreach ($element->attrs as $key => &$value) {

        // Compress numbers on data attributes.
        if (in_array($key, array('points', 'd'))) {
            $value = preg_replace_callback(
                CssCrush_Regex::$patt->number, 'csscrush__svg_number_compress', $value);
        }
    }
}

function csscrush__svg_render ($element) {

    // Flatten styles.
    $styles = '';
    $styles_data = array(
        '@font-face' => $element->face_styles,
        'svg' => $element->svg_styles,
        $element->tag => $element->styles,
    );
    foreach ($styles_data as $selector => $declarations) {
        if ($declarations) {
            $out = array();
            foreach ($declarations as $property => $value) {
                $out[] = "$property:$value";
            }
            $styles .= $selector . '{' . implode(';', $out) . '}';
        }
    }
    $styles = CssCrush::$process->restoreTokens($styles, 'u', true);
    $styles = CssCrush::$process->restoreTokens($styles, 's');

    $attrs = CssCrush_Util::htmlAttributes($element->attrs);
    $svg_attrs = CssCrush_Util::htmlAttributes($element->svg_attrs);

    // Markup.
    $svg[] = "<svg$svg_attrs>";
    $svg[] = '<defs>';
    $svg[] = implode($element->fills['gradients']);
    $svg[] = implode($element->fills['patterns']);
    $svg[] = implode($element->filters);
    if ($styles) {
        $cdata = preg_match('~[<>&]~', $styles);
        $svg[] = '<style type="text/css">';
        $svg[] = $cdata ? '<![CDATA[' : '';
        $svg[] = $styles;
        $svg[] = $cdata ? ']]>' : '';
        $svg[] = '</style>';
    }
    $svg[] = '</defs>';
    if ($element->tag === 'text') {
        $svg[] = "<text$attrs>{$element->data['text']}</text>";
    }
    else {
        $svg[] = "<{$element->tag}$attrs/>";
    }
    $svg[] = '</svg>';

    return array_filter($svg, 'strlen');
}


/*
    Custom versions of svg-*-gradient() for integrating.
*/
function csscrush__svg_fn_linear_gradient ($input, $element) {

    // Relies on functions from svg-gradients plugin.
    CssCrush_Plugin::load('svg-gradients');

    $generated_gradient = csscrush__create_svg_linear_gradient($input);
    $element->fills['gradients'][] = reset($generated_gradient);

    return 'url(#' . key($generated_gradient) . ')';
}

function csscrush__svg_fn_radial_gradient ($input, $element) {

    // Relies on functions from svg-gradients plugin.
    CssCrush_Plugin::load('svg-gradients');

    $generated_gradient = csscrush__create_svg_radial_gradient($input);
    $element->fills['gradients'][] = reset($generated_gradient);

    return 'url(#' . key($generated_gradient) . ')';
}

function csscrush__svg_fn_pattern ($input, $element) {

    static $uid = 0;
    $pid = 'p' . (++$uid);

    // Get args in order with defaults.
    list($url, $transform_list, $width, $height, $x, $y) =
        CssCrush_Function::parseArgs($input) +
        array('', '', 0, 0, 0, 0);

    $url = CssCrush::$process->fetchToken($url);
    if (! $url) {
        return '';
    }

    // If $width or $height is not specified get image dimensions the slow way.
    if (! $width || ! $height) {
        $file = $url->getAbsolutePath();
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

    $element->fills['patterns'][] = $generated_pattern;
    $element->svg_attrs['xmlns:xlink'] = "http://www.w3.org/1999/xlink";

    return 'url(#' . $pid . ')';
}


/*
    Helpers.
*/
function csscrush__svg_parselist ($str, $numbers = true) {
    $list = preg_split('~ +~', trim($str));
    return $numbers ? array_map('floatval', $list) : $list;
}

function csscrush__svg_number_compress ($m) {
    return round($m[0], 2);
}

function csscrush__ifset (&$var, $fallback = null) {
    if (isset($var)) {
        return $var;
    }
    return $fallback;
}
