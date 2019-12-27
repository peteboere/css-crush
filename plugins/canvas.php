<?php
/**
 * Bitmap image generator
 *
 * @see docs/plugins/canvas.md
 */
namespace CssCrush;

use stdClass;

\csscrush_plugin('canvas', function ($process) {
    $process->on('capture_phase2', 'CssCrush\canvas_capture');
    $process->functions->add('canvas', 'CssCrush\canvas_generator');
    $process->functions->add('canvas-data', 'CssCrush\canvas_generator');
});

function canvas_capture($process) {

    $process->string->pregReplaceCallback(
        Regex::make('~@canvas\s+(?<name>{{ ident }})\s*{{ block }}~iS'),
        function ($m) {
            Crush::$process->misc->canvas_defs[strtolower($m['name'])] = new Template($m['block_content']);
            return '';
        });
}

function canvas_generator($input, $context) {

    $process = Crush::$process;

    // Check GD requirements are met.
    static $requirements;
    if (! isset($requirements)) {
        $requirements = canvas_requirements();
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
    $args = Functions::parseArgs($input);
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
    $block = $canvas_defs[$name]($args);

    $raw = DeclarationList::parse($block, [
        'keyed' => true,
        'lowercase_keys' => true,
        'flatten' => true,
        'apply_hooks' => true,
    ]);

    // Create canvas object.
    $canvas = new Canvas();

    // Parseable canvas attributes with default values.
    static $schema = [
        'fill' => null,
        'background-fill' => null,
        'src' => null,
        'canvas-filter' => null,
        'width' => null,
        'height' => null,
        'margin' => 0,
    ];

    // Resolve properties, set defaults if not present.
    $canvas->raw = array_intersect_key($raw, $schema) + $schema;

    // Pre-populate.
    canvas_preprocess($canvas);

    // Apply functions.
    canvas_apply_css_funcs($canvas);
    // debug($canvas);

    // Create fingerprint for this canvas based on canvas object.
    $fingerprint = substr(md5(serialize($canvas)), 0, 7);
    $generated_filename = "cnv-$name-$fingerprint.png";

    if (! empty($process->options->asset_dir)) {
        $generated_filepath = $process->options->asset_dir . '/' . $generated_filename;
        $generated_url = Util::getLinkBetweenPaths(
            $process->output->dir, $process->options->asset_dir) . $generated_filename;
    }
    else {
        $generated_filepath = $process->output->dir . '/' . $generated_filename;
        $generated_url = $generated_filename;
    }
    $cached_file = file_exists($generated_filepath);

    // $cached_file = false;
    if (! $cached_file) {

        // Source arguments take priority.
        if ($src = canvas_fetch_src($canvas->raw['src'])) {

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
            canvas_create($canvas);

            // Apply background layer.
            canvas_fill($canvas, 'background-fill');

            // Filters.
            canvas_apply_filters($canvas, $src);

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
            $canvas->fills += ['fill' => 'black'];

            // Create base.
            canvas_create($canvas);

            // Apply background layer.
            canvas_fill($canvas, 'background-fill');
            canvas_fill($canvas, 'fill');
        }
    }
    else {
        // debug('file cached');
    }


    // Either write to a file.
    if ($context->function === 'canvas' && $process->ioContext === 'file') {

        if (! $cached_file) {
            imagepng($canvas->image, $generated_filepath);
        }

        $url = new Url($generated_url);
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

        $url = new Url('data:image/png;base64,' . base64_encode($data));
    }

    $label = $process->tokens->add($url);

    // Cache the output URL.
    $process->misc->canvas_cache[$cache_key] = $label;

    return $label;
}


function canvas_fn_linear_gradient($input, $context) {

    $args = Functions::parseArgs($input) + ['white', 'black'];

    $first_arg = strtolower($args[0]);

    static $directions = [
        'to top' => ['vertical', true],
        'to right' => ['horizontal', false],
        'to bottom' => ['vertical', false],
        'to left' => ['horizontal', true],
    ];

    if (isset($directions[$first_arg])) {
        list($direction, $flip) = $directions[$first_arg];
        array_shift($args);
    }
    else {
        list($direction, $flip) = $directions['to bottom'];
    }

    // Create fill object.
    $fill = new stdClass();
    $fill->stops = [];
    $fill->direction = $direction;

    canvas_set_fill_dims($fill, $context->canvas);

    // Start color.
    $color = Color::parse($args[0]);
    $fill->stops[] = $color ? $color : [0, 0, 0, 1];

    // End color.
    $color = Color::parse($args[1]);
    $fill->stops[] = $color ? $color : [255, 255, 255, 1];

    if ($flip) {
        $fill->stops = array_reverse($fill->stops);
    }

    $context->canvas->fills[$context->currentProperty] = $fill;
}

function canvas_fn_filter($input, $context) {

    $args = Functions::parseArgs($input);

    array_unshift($context->canvas->filters, [$context->function, $args]);
}


function canvas_apply_filters($canvas, $src) {

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
                canvas_fade($src, floatval($args[0]));
                break;

            case 'colorize':
                $rgb = $args + ['black'];
                if (count($rgb) === 1) {
                    // If only one argument parse it as a CSS color value.
                    $rgb = Color::parse($rgb[0]);
                    if (! $rgb) {
                        $rgb = [0, 0, 0];
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

function canvas_apply_css_funcs($canvas) {

    static $functions;
    if (! $functions) {
        $functions = new stdClass();

        $functions->fill = new Functions(['canvas-linear-gradient' => 'CssCrush\canvas_fn_linear_gradient']);

        $functions->generic = new Functions(array_diff_key(Crush::$process->functions->register, $functions->fill->register));

        $functions->filter = new Functions([
            'contrast' => 'CssCrush\canvas_fn_filter',
            'opacity' => 'CssCrush\canvas_fn_filter',
            'colorize' => 'CssCrush\canvas_fn_filter',
            'grayscale' => 'CssCrush\canvas_fn_filter',
            'greyscale' => 'CssCrush\canvas_fn_filter',
            'brightness' => 'CssCrush\canvas_fn_filter',
            'invert' => 'CssCrush\canvas_fn_filter',
            'blur' => 'CssCrush\canvas_fn_filter',
        ]);
    }

    $context = new stdClass();

    foreach ($canvas->raw as $property => &$value) {

        if (! is_string($value)) {
            continue;
        }

        $value = $functions->generic->apply($value);
        $context->canvas = $canvas;

        if (in_array($property, ['fill', 'background-fill'])) {
            $context->currentProperty = $property;
            $value = $functions->fill->apply($value, $context);
        }
        elseif ($property === 'canvas-filter') {
            $value = $functions->filter->apply($value, $context);
        }
    }
}

function canvas_preprocess($canvas) {

    if (isset($canvas->raw['margin'])) {

        $parts = canvas_parselist($canvas->raw['margin']);
        $count = count($parts);
        if ($count === 1) {
            $margin = [$parts[0], $parts[0], $parts[0], $parts[0]];
        }
        elseif ($count === 2) {
            $margin = [$parts[0], $parts[1], $parts[0], $parts[1]];
        }
        elseif ($count === 3) {
            $margin = [$parts[0], $parts[1], $parts[2], $parts[1]];
        }
        else {
            $margin = $parts;
        }
    }
    else {
        $margin = [0, 0, 0, 0];
    }

    foreach (['fill', 'background-fill'] as $fill_name) {
        if (isset($canvas->raw[$fill_name])) {
            $canvas->fills[$fill_name] = $canvas->raw[$fill_name];
        }
    }

    $canvas->margin = (object) [
        'top' => $margin[0],
        'right' => $margin[1],
        'bottom' => $margin[2],
        'left' => $margin[3],
    ];
    $canvas->width = $canvas->raw['width'];
    $canvas->height = $canvas->raw['height'];
}

function canvas_fetch_src($url_token) {

    if ($url_token && $url = Crush::$process->tokens->get($url_token)) {

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
                return (object) [
                    'file' => $file,
                    'info' => $info,
                    'width' => $info[0],
                    'height' => $info[1],
                    'image' => $image,
                ];
            }
        }
    }
    return false;
}


/*
    Adapted from GD Gradient Fill by Ozh (http://planetozh.com):
    http://planetozh.com/blog/my-projects/images-php-gd-gradient-fill
*/
function canvas_gradient($canvas, $fill) {

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
        $a = canvas_opacity($a);

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

function canvas_create($canvas) {

    $margin = $canvas->margin;
    $width = $canvas->width + $margin->right + $margin->left;
    $height = $canvas->height + $margin->top + $margin->bottom;

    // Create image object.
    $canvas->image = canvas_create_transparent($width, $height);
}

function canvas_create_transparent($width, $height) {

    $image = imagecreatetruecolor($width, $height);

    // Set transparent canvas background.
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

    return $image;
}

function canvas_fade($src, $opacity) {

    $width = imagesx($src->image);
    $height = imagesy($src->image);
    $new_image = canvas_create_transparent($width, $height);
    $opacity = canvas_opacity($opacity);

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


function canvas_fill($canvas, $property) {

    if (! isset($canvas->fills[$property])) {
        return false;
    }
    $fill = $canvas->fills[$property];

    // Gradient fill.
    if (is_object($fill)) {
        canvas_gradient($canvas, $fill);
    }

    // Solid color fill.
    elseif ($solid = Color::parse($fill)) {

        list($r, $g, $b, $a) = $solid;
        $color = imagecolorallocatealpha($canvas->image, $r, $g, $b, canvas_opacity($a));

        $fill = new stdClass();
        $canvas->currentProperty = $property;
        canvas_set_fill_dims($fill, $canvas);

        imagefilledrectangle($canvas->image, $fill->x1, $fill->y1, $fill->x2, $fill->y2, $color);
        imagealphablending($canvas->image, true);
    }

    // Can't parse.
    else {
        return false;
    }
}

function canvas_set_fill_dims($fill, $canvas) {

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

function canvas_requirements() {

    $requirements_met = true;

    if (! extension_loaded('gd')) {
        $requirements_met = false;
        warning('GD extension not available.');
    }
    else {
        $gd_info = implode('|', array_keys(array_filter(gd_info())));

        foreach (['jpe?g' => 'JPG', 'png' => 'PNG'] as $file_ext_patt => $file_ext) {
            if (! preg_match("~\b(?<ext>$file_ext_patt) support\b~i", $gd_info)) {
                $requirements_met = false;
                warning("GD extension has no $file_ext support.");
            }
        }
    }

    return $requirements_met;
}


/*
    Canvas object.
*/
class Canvas
{
    public $image, $fills = [], $filters = [];

    public function __destruct()
    {
        if (isset($this->image)) {
            imagedestroy($this->image);
        }
    }
}

/*
    Helpers.
*/
function canvas_opacity($float) {
    return 127 - max(min(round($float * 127), 127), 0);
}

function canvas_parselist($str, $numbers = true) {
    $list = preg_split('~ +~', trim($str));
    return $numbers ? array_map('floatval', $list) : $list;
}
