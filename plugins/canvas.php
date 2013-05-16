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
 *
 * @example
 *
 *     // Google logo resized to 400px width and given a sepia effect.
 *     @canvas foo {
 *         src: url("https://www.google.co.uk/images/srpr/logo4w.png");
 *         width: 400;
 *         canvas-filter: greyscale() colorize(45, 45, 0);
 *     }
 */

CssCrush_Plugin::register('canvas', array(
    'enable' => 'csscrush__enable_canvas',
    'disable' => 'csscrush__disable_canvas',
));

function csscrush__enable_canvas () {
    CssCrush_Hook::add('process_extract', 'csscrush__canvas_extract');
    CssCrush_Function::register('canvas', 'csscrush__canvas_generator');
    CssCrush_Function::register('canvas-data', 'csscrush__canvas_generator');
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

function csscrush__canvas_generator ($input, $context) {

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
    $cache_key = $context->function . $input;
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
        'src' => null,
        'canvas-filter' => null,
        'width' => null,
        'height' => null,
        'margin' => 0,
    );

    // Resolve properties, set defaults if not present.
    $canvas->raw = array_intersect_key($raw, $schema) + $schema;

    // Pre-populate.
    csscrush__canvas_preprocess($canvas);

    // Apply functions.
    csscrush__canvas_apply_css_funcs($canvas);
    // csscrush::log($canvas);

    // Create fingerprint for this canvas based on canvas object.
    $fingerprint = substr(md5(serialize($canvas)), 0, 7);
    $generated_filename = "cnv-$name-$fingerprint.png";
    $generated_filepath = $process->output->dir . '/' . $generated_filename;
    $cached_file = file_exists($generated_filepath);

    // $cached_file = false;
    if (! $cached_file) {

        // Source arguments take priority.
        if ($src = csscrush__canvas_fetch_src($canvas->raw['src'])) {

            // Resolve the src image dimensions and positioning.
            $dst_w = $src->width;
            $dst_h = $src->height;
            if (isset($canvas->width) && isset($canvas->height)) {
                $dst_w = $canvas->width;
                $dst_h = $canvas->height;
            }
            elseif (isset($canvas->width)) {
                $dst_w = $canvas->width;
                $dst_h = ($src->height/$src->width) * $canvas->width;
            }
            elseif (isset($canvas->height)) {
                $dst_w = ($src->width/$src->height) * $canvas->height;
                $dst_h = $canvas->height;
            }

            // Update the canvas height and width based on the src.
            $canvas->width = $dst_w;
            $canvas->height = $dst_h;

            // Create base.
            csscrush__canvas_create($canvas);

            // Apply background layer.
            csscrush__canvas_fill($canvas, 'background-fill');

            // Filters.
            csscrush__canvas_apply_filters($canvas, $src);

            // Place the src image on the base canvas image.
            imagecopyresized(
                $canvas->image,        // dest_img
                $src->image,           // src_img
                $canvas->margin->left, // dst_x
                $canvas->margin->top,  // dst_y
                0,                     // src_x
                0,                     // src_y
                $dst_w,                // dst_w
                $dst_h,                // dst_h
                $src->width,           // src_w
                $src->height           // src_h
            );
            imagedestroy($src->image);
        }
        else {

            // Set defaults.
            $canvas->width = isset($canvas->width) ? intval($canvas->width) : 100;
            $canvas->height = isset($canvas->height) ? intval($canvas->height) : 100;
            $canvas->fills += array('fill' => 'black');

            // Create base.
            csscrush__canvas_create($canvas);

            // Apply background layer.
            csscrush__canvas_fill($canvas, 'background-fill');
            csscrush__canvas_fill($canvas, 'fill');
        }
    }
    else {
        // csscrush::log('file cached');
    }


    // Either write to a file.
    if ($context->function === 'canvas' && $process->ioContext === 'file') {

        if (! $cached_file) {
            imagepng($canvas->image, $generated_filepath);
        }

        // Write to the same directory as the output css.
        $url = new CssCrush_Url("$generated_filename?" . time());
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


function csscrush__canvas_fn_linear_gradient ($input, $context) {

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

    csscrush__canvas_set_fill_dims($fill, $context->canvas);

    // Start color.
    $color = CssCrush_Color::parse($args[0]);
    $fill->stops[] = $color ? $color : array(0,0,0,1);

    // End color.
    $color = CssCrush_Color::parse($args[1]);
    $fill->stops[] = $color ? $color : array(255,255,255,1);

    if ($flip) {
        $fill->stops = array_reverse($fill->stops);
    }

    $context->canvas->fills[$context->currentProperty] = $fill;
}

function csscrush__canvas_fn_filter ($input, $context) {

    $args = CssCrush_Function::parseArgs($input);

    array_unshift($context->canvas->filters, array($context->function, $args));
}


function csscrush__canvas_apply_filters ($canvas, $src) {

    foreach ($canvas->filters as $filter) {
        list($name, $args) = $filter;

        switch ($name) {
            case 'greyscale':
            case 'grayscale':
                imagefilter($src->image, IMG_FILTER_GRAYSCALE);
                break;

            case 'invert':
                imagefilter($src->image, IMG_FILTER_NEGATE);
                break;

            case 'opacity':
                csscrush__canvas_fade($src, floatval($args[0]));
                break;

            case 'colorize':
                $rgb = $args + array('black');
                if (count($rgb) === 1) {
                    // If only one argument parse it as a CSS color value.
                    $rgb = CssCrush_Color::parse($rgb[0]);
                    if (! $rgb) {
                        $rgb = array(0,0,0);
                    }
                }
                imagefilter($src->image, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2]);
                break;

            case 'blur':
                $level = 1;
                if (isset($args[0])) {
                    // Allow multiple blurs for a stronger effect.
                    // Set hard limit.
                    $level = min(max(intval($args[0]), 1), 20);
                }
                while ($level--) {
                    imagefilter($src->image, IMG_FILTER_GAUSSIAN_BLUR);
                }
                break;

            case 'contrast':
                if (isset($args[0])) {
                    // By default it works like this:
                    // (max) -100 <- 0 -> +100 (min)
                    // But we're flipping the polarity to be more predictable:
                    // (min) -100 <- 0 -> +100 (max)
                    $level = intval($args[0]) * -1;
                }
                imagefilter($src->image, IMG_FILTER_CONTRAST, $level);
                break;

            case 'brightness':
                if (isset($args[0])) {
                    // -255 <- 0 -> +255
                    $level = intval($args[0]);
                }
                imagefilter($src->image, IMG_FILTER_BRIGHTNESS, $level);
                break;
        }
    }
}

function csscrush__canvas_apply_css_funcs ($canvas) {

    // Setup functions for using on values.
    static $map;
    if (! $map) {

        $fill_functions = array(
            'canvas-linear-gradient' => 'csscrush__canvas_fn_linear_gradient',
        );
        $map['fill'] = array(
            'patt' => CssCrush_Regex::createFunctionPatt(array_keys($fill_functions)),
            'functions' => $fill_functions,
        );

        $filter_functions = array(
            'contrast' => 'csscrush__canvas_fn_filter',
            'opacity' => 'csscrush__canvas_fn_filter',
            'colorize' => 'csscrush__canvas_fn_filter',
            'grayscale' => 'csscrush__canvas_fn_filter',
            'greyscale' => 'csscrush__canvas_fn_filter',
            'brightness' => 'csscrush__canvas_fn_filter',
            'invert' => 'csscrush__canvas_fn_filter',
            'blur' => 'csscrush__canvas_fn_filter',
        );
        $map['filter'] = array(
            'patt' => CssCrush_Regex::createFunctionPatt(array_keys($filter_functions)),
            'functions' => $filter_functions,
        );

        $generic_functions = array_diff_key(
            CssCrush_Function::$functions, $map['fill']['functions']);
        $map['generic'] = array(
            'patt' => CssCrush_Regex::createFunctionPatt(
                array_keys($generic_functions), array('bare_paren' => true)),
            'functions' => $generic_functions,
        );
    }

    // Function context object.
    $context = new stdClass();

    foreach ($canvas->raw as $property => &$value) {

        if (! is_string($value)) {
            continue;
        }

        // Generic functions.
        CssCrush_Function::executeOnString(
            $value, $map['generic']['patt'], $map['generic']['functions']);

        // Fill functions.
        if (in_array($property, array('fill', 'background-fill'))) {
            $context->currentProperty = $property;
            $context->canvas = $canvas;
            CssCrush_Function::executeOnString(
                $value, $map['fill']['patt'], $map['fill']['functions'], $context);
        }
        elseif ($property === 'canvas-filter') {
            $context->canvas = $canvas;
            CssCrush_Function::executeOnString(
                $value, $map['filter']['patt'], $map['filter']['functions'], $context);
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

    $canvas->margin = (object) array(
        'top' => $margin[0],
        'right' => $margin[1],
        'bottom' => $margin[2],
        'left' => $margin[3],
    );
    $canvas->width = $canvas->raw['width'];
    $canvas->height = $canvas->raw['height'];
}

function csscrush__canvas_fetch_src ($url_token) {

    if ($url_token && $url = CssCrush::$process->fetchToken($url_token)) {

        $file = $url->getAbsolutePath();

        // Testing the image availability and getting info.
        if ($info = @getimagesize($file)) {

            $image = null;

            // If image is available copy it.
            switch ($info['mime']) {
                case 'image/png':
                    $image = imagecreatefrompng($file);
                    break;
                case 'image/jpg':
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($file);
                    break;
            }
            if ($image) {
                return (object) array(
                    'file' => $file,
                    'info' => $info,
                    'width' => $info[0],
                    'height' => $info[1],
                    'image' => $image,
                );
            }
        }
    }
    return false;
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

    $margin = $canvas->margin;
    $width = $canvas->width + $margin->right + $margin->left;
    $height = $canvas->height + $margin->top + $margin->bottom;

    // Create image object.
    $canvas->image = csscrush__canvas_create_transparent($width, $height);
}

function csscrush__canvas_create_transparent ($width, $height) {

    $image = imagecreatetruecolor($width, $height);

    // Set transparent canvas background.
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

    return $image;
}

function csscrush__canvas_fade ($src, $opacity) {

    $width = imagesx($src->image);
    $height = imagesy($src->image);
    $new_image = csscrush__canvas_create_transparent($width, $height);
    $opacity = csscrush__canvas_opacity($opacity);

    // Perform pixel-based alpha map application
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $colors = imagecolorsforindex($src->image, imagecolorat($src->image, $x, $y));
            imagesetpixel($new_image, $x, $y, imagecolorallocatealpha(
                $new_image, $colors['red'], $colors['green'], $colors['blue'], $opacity));
        }
    }

    imagedestroy($src->image);
    $src->image = $new_image;
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
    $margin = $canvas->margin;

    $fill->x1 = 0;
    $fill->y1 = 0;
    $fill->x2 = $canvas->width + $margin->right + $margin->left;
    $fill->y2 = $canvas->height + $margin->top + $margin->bottom;

    if (isset($canvas->currentProperty) && $canvas->currentProperty === 'fill') {
        $fill->x1 = $margin->left;
        $fill->y1 = $margin->top;
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
    public $image, $fills = array(), $filters = array();

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
