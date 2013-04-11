<?php
/**
 * Bitmap image generator
 *
 * Requires the GD image library bundled with PHP.
 *
 * @example
 *
 *     // Red semi-transparent square.
 *     @canvas foo {
 *         width: 50;
 *         height: 50;
 *         fill: rgba(255, 0, 0, .5);
 *     }
 *
 *     body {
 *         background: canvas(foo);
 *     }
 *
 * @example
 *
 *     // White to transparent east facing gradient with 10px margin and
 *     // background fill.
 *     @canvas foo {
 *         width: arg(0, 150);
 *         height: 150;
 *         fill: canvas-linear-gradient(to right, white, rgba(255,255,255,0));
 *         background-fill: powderblue;
 *         margin: 10;
 *     }
 *
 *     // Rectangle 300x150.
 *     body {
 *         background: red canvas(foo, 300) no-repeat;
 *     }
 *     // Default dimensions 150x150 as data URI.
 *     .bar {
 *         background: canvas-data(foo) repeat-x;
 *     }
 */

CssCrush_Plugin::register('canvas', array(
    'enable' => 'csscrush__enable_canvas',
    'disable' => 'csscrush__disable_canvas',
));

function csscrush__enable_canvas () {
    CssCrush_Hook::add('process_extract', 'csscrush__canvas_extract');
    CssCrush_Function::register('canvas', 'csscrush_fn__canvas');
    CssCrush_Function::register('canvas-data', 'csscrush_fn__canvas_data');
}

function csscrush__disable_canvas () {
    CssCrush_Hook::remove('process_extract', 'csscrush__canvas_extract');
    CssCrush_Function::deRegister('canvas');
    CssCrush_Function::deRegister('canvas-data');
}

function csscrush__canvas_extract ($process) {

    static $callback, $patt;
    if (! $callback) {
        $patt = CssCrush_Regex::create('@canvas +(<ident>) *\{ *(.*?) *\};?', 'iS');
        $callback = create_function('$m', '
            $name = strtolower($m[1]);
            $block = $m[2];
            if (! empty($name) && ! empty($block)) {
                CssCrush::$process->misc->canvas_defs[$name] =
                    new CssCrush_Template($block);
            }
        ');
    }

    // Extract definitions.
    $process->stream->pregReplaceCallback($patt, $callback);
}

function csscrush_fn__canvas ($input) {

    return csscrush__canvas_generator($input, 'canvas');
}

function csscrush_fn__canvas_data ($input) {

    return csscrush__canvas_generator($input, 'canvas-data');
}

function csscrush__canvas_generator ($input, $fn_name) {

    $process = CssCrush::$process;

    // Check GD requirements are met.
    static $requirements;
    if (! isset($requirements)) {
        $requirements = csscrush__canvas_requirements();
    }
    if ($requirements === false) {
        return '';
    }

    // Check process cache.
    $cache_key = $fn_name . $input;
    if (isset($process->misc->canvas_cache[$cache_key])) {
        return $process->misc->canvas_cache[$cache_key];
    }

    // Parse args, bail if none.
    $args = CssCrush_Function::parseArgs($input);
    if (! isset($args[0])) {
        return '';
    }

    $name = strtolower(array_shift($args));

    // Bail if name not registered.
    $canvas_defs =& $process->misc->canvas_defs;
    if (! isset($canvas_defs[$name])) {
        return '';
    }

    // Apply args to template.
    $block = $canvas_defs[$name]->apply($args);

    // Parse the block into a keyed array.
    $raw = array_change_key_case(CssCrush_Util::parseBlock($block, true));

    // Create canvas object.
    $canvas = new CssCrush_Canvas();

    // Parseable canvas attributes with default values.
    static $schema = array(
        'fill' => null,
        'background-fill' => null,
        'width' => 100,
        'height' => 100,
        'margin' => 0,
    );

    // Resolve properties, set defaults if not present.
    $canvas->raw = array_intersect_key($raw, $schema) + $schema;

    // Pre-populate.
    csscrush__canvas_preprocess($canvas);

    // Apply functions.
    csscrush__canvas_apply_css_funcs($canvas);

    // Create fingerprint for this canvas based on canvas object.
    $fingerprint = substr(md5(serialize($canvas)), 0, 7);
    $generated_filename = "cnv-$name-$fingerprint.png";
    $generated_filepath = $process->output->dir . '/' . $generated_filename;
    $cached_file = file_exists($generated_filepath);

    if (! $cached_file) {
        // Create transparent image as base.
        csscrush__canvas_create($canvas);

        // Apply fill layers.
        csscrush__canvas_fill($canvas, 'background-fill');
        csscrush__canvas_fill($canvas, 'fill');
    }
    else {
        // csscrush::log('file cached');
    }

    // Either write to a file.
    if ($fn_name === 'canvas') {

        if (! $cached_file) {
            imagepng($canvas->image, $generated_filepath);
        }

        // Write to the same directory as the output css.
        $url = new CssCrush_Url($generated_filename);
        $url->noRewrite = true;
    }
    // Or create data uri.
    else {
        if (! $cached_file) {
            ob_start();
            imagepng($canvas->image);
            $data = ob_get_clean();
        }
        else {
            $data = file_get_contents($generated_filepath);
        }

        $url = new CssCrush_Url('data:image/png;base64,' . base64_encode($data));
    }

    // Cache the output URL.
    $process->misc->canvas_cache[$cache_key] = $url->label;

    return $url->label;
}


function csscrush__canvas_fn_linear_gradient ($input, $canvas) {

    $args = CssCrush_Function::parseArgs($input) + array(
        'white', 'black',
    );

    $first_arg = strtolower($args[0]);

    static $directions = array(
        'to top' => array('vertical', true),
        'to right' => array('horizontal', false),
        'to bottom' => array('vertical', false),
        'to left' => array('horizontal', true),
    );

    if (isset($directions[$first_arg])) {
        list($direction, $flip) = $directions[$first_arg];
        array_shift($args);
    }
    else {
        list($direction, $flip) = $directions['to bottom'];
    }

    // Create fill object.
    $fill = new stdClass();
    $fill->stops = array();
    $fill->direction = $direction;

    csscrush__canvas_set_fill_dims($fill, $canvas);

    // Start color.
    $color = CssCrush_Color::parse($args[0]);
    $fill->stops[] = $color ? $color : array(0,0,0,1);

    // End color.
    $color = CssCrush_Color::parse($args[1]);
    $fill->stops[] = $color ? $color : array(255,255,255,1);

    if ($flip) {
        $fill->stops = array_reverse($fill->stops);
    }

    $canvas->fills[$canvas->currentProperty] = $fill;
}

function csscrush__canvas_apply_css_funcs ($canvas) {

    // Setup functions for using on values.
    static $generic_functions_patt, $fill_functions, $fill_functions_patt;
    if (! $generic_functions_patt) {
        $fill_functions = array(
            'canvas-linear-gradient' => 'csscrush__canvas_fn_linear_gradient',
        );
        $generic_functions
            = array_diff_key(CssCrush_Function::$functions, $fill_functions);
        $generic_functions_patt
            = CssCrush_Regex::createFunctionPatt(array_keys($generic_functions), array('bare_paren' => true));
        $fill_functions_patt
            = CssCrush_Regex::createFunctionPatt(array_keys($fill_functions));
    }

    foreach ($canvas->raw as $property => &$value) {

        if (! is_string($value)) {
            continue;
        }

        CssCrush_Function::executeOnString($value, $generic_functions_patt);

        if (in_array($property, array('fill', 'background-fill'))) {
            $canvas->currentProperty = $property;
            CssCrush_Function::executeOnString(
                $value, $fill_functions_patt, $fill_functions, $canvas);
        }
    }
}

function csscrush__canvas_preprocess ($canvas) {

    if (isset($canvas->raw['margin'])) {

        $parts = csscrush__canvas_parselist($canvas->raw['margin']);
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
        $margin = array(0,0,0,0);
    }

    foreach (array('fill', 'background-fill') as $fill_name) {
        if (isset($canvas->raw[$fill_name])) {
            $canvas->fills[$fill_name] = $canvas->raw[$fill_name];
        }
    }

    $canvas->margin = $margin;
    $canvas->width = intval($canvas->raw['width']);
    $canvas->height = intval($canvas->raw['height']);
}

/*
    Adapted from GD Gradient Fill by Ozh (http://planetozh.com):
    http://planetozh.com/blog/my-projects/images-php-gd-gradient-fill
*/
function csscrush__canvas_gradient ($canvas, $fill) {

    $image = $canvas->image;

    // Resolve drawing direction.
    if ($fill->direction === 'horizontal') {
        $line_numbers = $fill->x2 - $fill->x1;
    }
    else {
        $line_numbers = $fill->y2 - $fill->y1;
    }

    list($r1, $g1, $b1, $a1) = $fill->stops[0];
    list($r2, $g2, $b2, $a2) = $fill->stops[1];

    $r = $g = $b = $a = -1;

    for ($line = 0; $line < $line_numbers; $line++) {

        $last = "$r,$g,$b,$a";

        $r = $r2 - $r1 ? intval($r1 + ($r2 - $r1) * ($line / $line_numbers)): $r1;
        $g = $g2 - $g1 ? intval($g1 + ($g2 - $g1) * ($line / $line_numbers)): $g1;
        $b = $b2 - $b1 ? intval($b1 + ($b2 - $b1) * ($line / $line_numbers)): $b1;
        $a = $a2 - $a1 ? ($a1 + ($a2 - $a1) * ($line / $line_numbers)) : $a1;
        $a = csscrush__canvas_opacity($a);

        if ($last != "$r,$g,$b,$a") {
            $color = imagecolorallocatealpha($image, $r, $g, $b, $a);
        }

        switch($fill->direction) {
            case 'horizontal':
                imagefilledrectangle($image,
                    $fill->x1 + $line,
                    $fill->y1,
                    $fill->x1 + $line,
                    $fill->y2,
                    $color);

                break;
            case 'vertical':
            default:
                imagefilledrectangle($image,
                    $fill->x1,
                    $fill->y1 + $line,
                    $fill->x2,
                    $fill->y1 + $line,
                    $color);
                break;
        }
        imagealphablending($image, true);
    }
}

function csscrush__canvas_create ($canvas) {

    // Create image object.
    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $canvas->margin;

    $width = $canvas->width + $margin_right + $margin_left;
    $height = $canvas->height + $margin_top + $margin_bottom;
    $canvas->image = imagecreatetruecolor($width, $height);

    // Set transparent canvas background.
    imagealphablending($canvas->image, false);
    $fill = imagecolorallocatealpha($canvas->image, 0, 0, 0, 127);
    imagefill($canvas->image, 0, 0, $fill);

    imagesavealpha($canvas->image, true);
}

function csscrush__canvas_fill ($canvas, $property) {

    if (! isset($canvas->fills[$property])) {
        return false;
    }
    $fill = $canvas->fills[$property];

    // Gradient fill.
    if (is_object($fill)) {
        csscrush__canvas_gradient($canvas, $fill);
    }

    // Solid color fill.
    elseif ($solid = CssCrush_Color::parse($fill)) {

        list($r, $g, $b, $a) = $solid;
        $color = imagecolorallocatealpha($canvas->image, $r, $g, $b, csscrush__canvas_opacity($a));

        $fill = new stdClass();
        $canvas->currentProperty = $property;
        csscrush__canvas_set_fill_dims($fill, $canvas);

        imagefilledrectangle($canvas->image, $fill->x1, $fill->y1, $fill->x2, $fill->y2, $color);
        imagealphablending($canvas->image, true);
    }

    // Can't parse.
    else {
        return false;
    }
}

function csscrush__canvas_set_fill_dims ($fill, $canvas) {

    // Resolve fill dimensions and coordinates.
    list($margin_top, $margin_right, $margin_bottom, $margin_left) = $canvas->margin;

    $fill->x1 = 0;
    $fill->y1 = 0;
    $fill->x2 = $canvas->width + $margin_right + $margin_left;
    $fill->y2 = $canvas->height + $margin_top + $margin_bottom;

    if ($canvas->currentProperty === 'fill') {
        $fill->x1 = $margin_left;
        $fill->y1 = $margin_top;
        $fill->x2 = $canvas->width + $fill->x1 - 1;
        $fill->y2 = $canvas->height + $fill->y1 - 1;
    }
}

function csscrush__canvas_requirements () {

    $error_messages = array();

    if (! extension_loaded('gd')) {
        $error_messages[] = 'GD extension not available.';
    }
    else {
        $info = array_change_key_case(gd_info());
        foreach (array('png', 'jpeg') as $key) {
            if (empty($info["$key support"])) {
                $error_messages[] = "GD extension has no $key support.";
            }
        }
    }

    if ($error_messages) {
        CssCrush::logError($error_messages);
        $error = implode(' ' . PHP_EOL, $error_messages);
        trigger_error(__METHOD__ . ": $error\n", E_USER_WARNING);

        return false;
    }
    return true;
}


/*
    Canvas object.
*/
class CssCrush_Canvas
{
    public $image, $fills = array();

    public function __destruct ()
    {
        if (isset($this->image)) {
            imagedestroy($this->image);
        }
    }
}

/*
    Helpers.
*/
function csscrush__canvas_opacity ($float) {
    return 127 - max(min(round($float * 127), 127), 0);
}

function csscrush__canvas_parselist ($str, $numbers = true) {
    $list = preg_split('~ +~', trim($str));
    return $numbers ? array_map('floatval', $list) : $list;
}
