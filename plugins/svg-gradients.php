<?php
/**
 * Functions for creating SVG gradients with a CSS gradient like syntax
 *
 * @see docs/plugins/svg-gradients.md
 */
namespace CssCrush;

Plugin::register('svg-gradients', array(
    'load' => function () {
        $GLOBALS['CSSCRUSH_SVG_GRADIENT_UID'] = 0;
    },
    'enable' => function ($process) {
        $GLOBALS['CSSCRUSH_SVG_GRADIENT_UID'] = 0;
        $process->functions->add('svg-linear-gradient', 'CssCrush\fn__svg_linear_gradient');
        $process->functions->add('svg-radial-gradient', 'CssCrush\fn__svg_radial_gradient');
    }
));


function fn__svg_linear_gradient($input) {

    $gradient = create_svg_linear_gradient($input);
    $gradient_markup = reset($gradient);
    $gradient_id = key($gradient);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150">';
    $svg .= '<defs>';
    $svg .= $gradient_markup;
    $svg .= '</defs>';
    $svg .= "<rect x=\"0\" y=\"0\" width=\"100%\" height=\"100%\" fill=\"url(#$gradient_id)\"/>";
    $svg .= '</svg>';

    return Crush::$process->tokens->add(new Url('data:image/svg+xml;base64,' . base64_encode($svg)));
}


function fn__svg_radial_gradient($input) {

    $gradient = create_svg_radial_gradient($input);
    $gradient_markup = reset($gradient);
    $gradient_id = key($gradient);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150">';
    $svg .= '<defs>';
    $svg .= $gradient_markup;
    $svg .= '</defs>';
    $svg .= "<rect x=\"0\" y=\"0\" width=\"100%\" height=\"100%\" fill=\"url(#$gradient_id)\"/>";
    $svg .= '</svg>';

    return Crush::$process->tokens->add(new Url('data:image/svg+xml;base64,' . base64_encode($svg)));
}


function create_svg_linear_gradient($input) {

    static $angle_keywords, $deg_patt;
    if (! $angle_keywords) {
        $angle_keywords = array(
            'to top'    => 180,
            'to right'  => 270,
            'to bottom' => 0,
            'to left'   => 90,
            // Not very magic corners.
            'to top right' => array(array(0, 100), array(100, 0)),
            'to top left' => array(array(100, 100), array(0, 0)),
            'to bottom right' => array(array(0, 0), array(100, 100)),
            'to bottom left' => array(array(100, 0), array(0, 100)),
        );
        $angle_keywords['to right top'] = $angle_keywords['to top right'];
        $angle_keywords['to left top'] = $angle_keywords['to top left'];
        $angle_keywords['to right bottom'] = $angle_keywords['to bottom right'];
        $angle_keywords['to left bottom'] = $angle_keywords['to bottom left'];

        $deg_patt = Regex::make('~^{{number}}deg$~i');
    }

    $args = Functions::parseArgs($input);

    // If no angle argument is passed the default.
    $angle = 0;

    // Parse starting and ending coordinates from the first argument if it's an angle.
    $coords = null;
    $first_arg = $args[0];
    $first_arg_is_angle = false;

    // Try to parse an angle value.
    if (preg_match($deg_patt, $first_arg)) {
        $angle = floatval($first_arg);

        // Quick fix to match standard linear-gradient() angle.
        $angle += 180;
        $first_arg_is_angle = true;
    }
    elseif (isset($angle_keywords[$first_arg])) {
        if (is_array($angle_keywords[$first_arg])) {
            $coords = $angle_keywords[$first_arg];
        }
        else {
            $angle = $angle_keywords[$first_arg];
        }
        $first_arg_is_angle = true;
    }

    // Shift off the first argument if it has been recognised as an angle.
    if ($first_arg_is_angle) {
        array_shift($args);
    }

    // If not using a magic corner, create start/end coordinates from the angle.
    if (! $coords) {

        // Normalize the angle.
        $angle = fmod($angle, 360);
        if ($angle < 0) {
            $angle = 360 + $angle;
        }
        $angle = round($angle, 2);

        $start_x = 0;
        $end_x = 0;
        $start_y = 0;
        $end_y = 100;

        if ($angle >= 0 && $angle <= 45) {
            $start_x = (($angle / 45) * 50) + 50;
            $end_x = 100 - $start_x;
            $start_y = 0;
            $end_y = 100;
        }
        elseif ($angle > 45 && $angle <= 135) {
            $angle_delta = $angle - 45;
            $start_x = 100;
            $end_x = 0;
            $start_y = ($angle_delta / 90) * 100;
            $end_y = 100 - $start_y;
        }
        elseif ($angle > 135 && $angle <= 225) {
            $angle_delta = $angle - 135;
            $start_x = 100 - (($angle_delta / 90) * 100);
            $end_x = 100 - $start_x;
            $start_y = 100;
            $end_y = 0;
        }
        elseif ($angle > 225 && $angle <= 315) {
            $angle_delta = $angle - 225;
            $start_x = 0;
            $end_x = 100;
            $start_y = 100 - (($angle_delta / 90) * 100);
            $end_y = 100 - $start_y;
        }
        elseif ($angle > 315 && $angle <= 360) {
            $angle_delta = $angle - 315;
            $start_x = ($angle_delta / 90) * 100;
            $end_x = 100 - $start_x;
            $start_y = 0;
            $end_y = 100;
        }
        $coords = array(
            array(round($start_x, 1), round($start_y, 1)),
            array(round($end_x, 1), round($end_y, 1)),
        );
    }

    // The remaining arguments are treated as color stops.
    // - Capture their color values and if specified color offset percentages.
    // - Only percentages are supported as SVG gradients to accept other length values
    //   for color stop offsets.
    $color_stops = parse_gradient_color_stops($args);

    // Create the gradient markup with a unique id.
    $uid = ++$GLOBALS['CSSCRUSH_SVG_GRADIENT_UID'];
    $gradient_id = "lg$uid";
    $gradient = "<linearGradient id=\"$gradient_id\" gradientUnits=\"userSpaceOnUse\"";
    $gradient .= " x1=\"{$coords[0][0]}%\" x2=\"{$coords[1][0]}%\" y1=\"{$coords[0][1]}%\" y2=\"{$coords[1][1]}%\">";
    $gradient .= $color_stops;
    $gradient .= '</linearGradient>';

    return array($gradient_id => $gradient);
}


function create_svg_radial_gradient($input) {

    static $position_keywords, $origin_patt;
    if (! $position_keywords) {
        $position_keywords = array(
            'at top'    => array('50%', '0%'),
            'at right'  => array('100%', '50%'),
            'at bottom' => array('50%', '100%'),
            'at left'   => array('0%', '50%'),
            'at center' => array('50%', '50%'),
            // Not very magic corners.
            'at top right'    => array('100%', '0%'),
            'at top left'     => array('0%', '0%'),
            'at bottom right' => array('100%', '100%'),
            'at bottom left'  => array('0%', '100%'),
        );
        $position_keywords['at right top'] = $position_keywords['at top right'];
        $position_keywords['at left top'] = $position_keywords['at top left'];
        $position_keywords['at right bottom'] = $position_keywords['at bottom right'];
        $position_keywords['at left bottom'] = $position_keywords['at bottom left'];

        $origin_patt = Regex::make('~^({{number}}%?) +({{number}}%?)$~');
    }

    $args = Functions::parseArgs($input);

    // Default origin,
    $position = $position_keywords['at center'];

    // Parse origin coordinates from the first argument if it's an origin.
    $first_arg = $args[0];
    $first_arg_is_position = false;

    // Try to parse an origin value.
    if (preg_match($origin_patt, $first_arg, $m)) {
        $position = array($m[1], $m[2]);
        $first_arg_is_position = true;
    }
    elseif (isset($position_keywords[$first_arg])) {
        $position = $position_keywords[$first_arg];
        $first_arg_is_position = true;
    }

    // Shift off the first argument if it has been recognised as an origin.
    if ($first_arg_is_position) {
        array_shift($args);
    }

    // The remaining arguments are treated as color stops.
    // - Capture their color values and if specified color offset percentages.
    // - Only percentages are supported as SVG gradients to accept other length values
    //   for color stop offsets.
    $color_stops = parse_gradient_color_stops($args);

    // Create the gradient markup with a unique id.
    $uid = ++$GLOBALS['CSSCRUSH_SVG_GRADIENT_UID'];
    $gradient_id = "rg$uid";
    $gradient = "<radialGradient id=\"$gradient_id\" gradientUnits=\"userSpaceOnUse\"";
    $gradient .= " cx=\"{$position[0]}\" cy=\"{$position[1]}\" r=\"100%\">";
    $gradient .= $color_stops;
    $gradient .= '</radialGradient>';

    return array($gradient_id => $gradient);
}


function parse_gradient_color_stops(array $color_stop_args) {

    $offsets = array();
    $colors = array();
    $offset_patt = '~ +([\d\.]+%)$~';
    $last_index = count($color_stop_args) - 1;

    foreach ($color_stop_args as $index => $color_arg) {

        if (preg_match($offset_patt, $color_arg, $m)) {
            $offsets[] = floatval($m[1]);
            $color = preg_replace($offset_patt, '', $color_arg);
        }
        else {
            if ($index === 0) {
                $offsets[] = 0;
            }
            elseif ($index === $last_index) {
                $offsets[] = 100;
            }
            else {
                $offsets[] = null;
            }
            $color = $color_arg;
        }

        // For hsla()/rgba() extract alpha component from color values and
        // convert to hsl()/rgb().
        // Webkit doesn't support them for SVG colors.
        $colors[] = Color::colorSplit($color);
    }

    // For unspecified color offsets fill in the blanks.
    $next_index_not_null = 0;
    $prev_index_not_null = 0;
    $n = count($offsets);

    foreach ($offsets as $index => $offset) {

        if (! isset($offset)) {

            // Scan for next non-null offset.
            for ($i = $index; $i < $n; $i++) {
                if (isset($offsets[$i])) {
                    $next_index_not_null = $i;
                    break;
                }
            }

            // Get the difference between previous 'not null' offset and the next 'not null' offset.
            // Divide by the number of null offsets to get a value for padding between them.
            $padding_increment =
                ($offsets[$next_index_not_null] - $offsets[$prev_index_not_null]) /
                ($next_index_not_null - $index + 1);
            $padding = $padding_increment;

            for ($i = $index; $i < $n; $i++) {
                if (isset($offsets[$i])) {
                    break;
                }
                // Replace the null offset with the new padded value.
                $offsets[$i] = $offsets[$prev_index_not_null] + $padding;
                // Bump the padding for the next null offset.
                $padding += $padding_increment;
            }
        }
        else {
            $prev_index_not_null = $index;
        }
    }

    $stops = '';
    foreach (array_combine($offsets, $colors) as $offset => $color) {
        list($color_value, $opacity) = $color;
        $stop_opacity = $opacity < 1 ? " stop-opacity=\"$opacity\"" : '';
        $stops .= "<stop offset=\"$offset%\" stop-color=\"$color_value\"$stop_opacity/>";
    }

    return $stops;
}
