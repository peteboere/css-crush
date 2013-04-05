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

    // Bail if no SVG registered by this name.
    $canvas_defs =& $process->misc->canvas_defs;
    if (! isset($canvas_defs[$name])) {

        return '';
    }

    // Apply args to template.
    $block = $canvas_defs[$name]->apply($args);

    // Parse the block into a keyed assoc array.
    $raw_data = array_change_key_case(CssCrush_Util::parseBlock($block, true));

    // Resolve properties, set defaults if not present.
    $properties = array_intersect_key($raw_data, $custom_attrs) + array(
        'width' => 100,
        'height' => 100,
        'fill' => '#000',
    );

    // Apply functions.
    $storage = new stdClass();
    csscrush__canvas_apply_css_funcs($properties, $storage);

    // Extract variables.
    extract($properties);
    $width = intval($width);
    $height = intval($height);

    // Create image object.
    $image = imagecreatetruecolor($width, $height);

    // Create transparent canvas background.
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagesavealpha($image, true);

    // Gradient fill.
    if (is_array($fill)) {

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





function csscrush__canvas_fn_linear_gradient ($input, $extra) {

    $colors = CssCrush_Function::parseArgs($input);
    $extra->fill = array();

    return '';
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
            = CssCrush_Regex::createFunctionPatt(array_keys($generic_functions), true);
        $fill_functions_patt
            = CssCrush_Regex::createFunctionPatt(array_keys($fill_functions));
    }

    foreach ($properties as $property => &$value) {
        CssCrush_Function::executeOnString($value, $generic_functions_patt);

        // if ($property === 'fill') {
        //     CssCrush_Function::executeOnString(
        //         $value, $fill_functions_patt, $fill_functions, $extra);
        // }
    }
}


/*
    Helpers.
*/
function csscrush__canvas_opacity ($float) {
    return 127 - max(min(round($float * 127), 127), 0);
}
