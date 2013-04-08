<?php
/**
 * Image generator
 *
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

function csscrush_fn__canvas ($input) {

    return csscrush__canvas_generator($input, 'canvas');
}

function csscrush_fn__canvas_data ($input) {

    return csscrush__canvas_generator($input, 'canvas-data');
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

function csscrush__canvas_generator ($input, $fn_name) {

    $process = CssCrush::$process;

    $cache_key = $fn_name . $input;
    if (isset($process->misc->canvas_cache[$cache_key])) {

        return $process->misc->canvas_cache[$cache_key];
    }

    // Non standard attributes.
    static $custom_attrs = array(
        'fill' => true,
        'width' => true,
        'height' => true,
    );

    // Bail if no args.
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
    $raw_data = array_change_key_case(CssCrush_Util::parseBlock($block, true));

    // Resolve properties, set defaults if not present.
    $properties = array_intersect_key($raw_data, $custom_attrs) + array(
        'width' => 100,
        'height' => 100,
        'fill' => 'black',
    );

    // Apply functions.
    $context = new stdClass();
    csscrush__canvas_apply_css_funcs($properties, $context);

    // Extract variables.
    extract($properties);
    $width = intval($width);
    $height = intval($height);
    if (isset($context->fill)) {
        $fill = $context->fill;
    }

    // Create image object.
    $image = imagecreatetruecolor($width, $height);

    // Create transparent canvas background.
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagesavealpha($image, true);

    // Gradient fill.
    if (is_object($fill)) {

        // Resolve drawing direction.
        if ($fill->direction === 'horizontal') {
            $line_numbers = imagesx($image);
            $line_width = imagesy($image);
        }
        else {
            $line_numbers = imagesy($image);
            $line_width = imagesx($image);
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
                    imagefilledrectangle($image, $line, 0, $line, $line_width, $color);
                    break;
                case 'vertical':
                default:
                    imagefilledrectangle($image, 0, $line, $line_width, $line, $color);
                    break;
            }
            imagealphablending($image, true);
        }
    }

    // Solid color fill.
    elseif ($solid = CssCrush_Color::parse($fill)) {

        list($r, $g, $b, $a) = $solid;
        $fill = imagecolorallocatealpha($image, $r, $g, $b, csscrush__canvas_opacity($a));
        imagefilledrectangle($image, 0, 0, $width, $height, $fill);
    }

    // Failure to parse fill.
    else {

        imagedestroy($image);
        return '';
    }

    // Either write to a file.
    if ($fn_name === 'canvas') {

        // Create fingerprint for the created file.
        $fingerprint = substr(md5("{$width}x{$height}$fill"), 0, 7);
        $generated_filename = "cnv-$name-$fingerprint.png";
        $generated_path = $process->output->dir . '/' . $generated_filename;

        imagepng($image, $generated_path);

        // Write to the same directory as the output css.
        $url = new CssCrush_Url($generated_filename);
        $url->noRewrite = true;
    }
    // Or create data uri.
    else {
        ob_start();
        imagepng($image);
        $data = ob_get_clean();

        $url = new CssCrush_Url('data:image/png;base64,' . base64_encode($data));
    }

    // Cache the output URL.
    $process->misc->canvas_cache[$cache_key] = $url->label;

    imagedestroy($image);
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

    // Start color.
    $color = CssCrush_Color::parse($args[0]);
    $fill->stops[] = $color ? $color : array(0,0,0,1);

    // End color.
    $color = CssCrush_Color::parse($args[1]);
    $fill->stops[] = $color ? $color : array(255,255,255,1);

    if ($flip) {
        $fill->stops = array_reverse($fill->stops);
    }

    $context->fill = $fill;
}

function csscrush__canvas_apply_css_funcs (&$properties, $extra) {

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

    foreach ($properties as $property => &$value) {
        CssCrush_Function::executeOnString($value, $generic_functions_patt);

        if ($property === 'fill') {
            CssCrush_Function::executeOnString(
                $value, $fill_functions_patt, $fill_functions, $extra);
        }
    }
}


/*
    Helpers.
*/
function csscrush__canvas_opacity ($float) {
    return 127 - max(min(round($float * 127), 127), 0);
}
