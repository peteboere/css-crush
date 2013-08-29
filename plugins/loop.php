<?php
/**
 * For...in loops with lists and generator functions
 *
 * @example
 *      @for fruit in apple, orange, pear, blueberry {
 *          .#(fruit) {
 *              background-image: url("images/#(fruit).jpg");
 *              }
 *      }
 *
 *      // Yields:
 *      .apple { background-image: url(images/apple.jpg); }
 *      .orange { background-image: url(images/orange.jpg); }
 *      ...
 *
 * @example
 *      @for i in range(1, 12) {
 *          .grid-#(i)-of-12 {
 *              width: (#(i) / 12 * 100)%;
 *              }
 *      }
 *
 *      // Yields:
 *      .grid-1-of-12 { width: 8.33333%; }
 *      ...
 *      .grid-12-of-12 { width: 100%; }
 *
 * @example
 *      // The last argument to color-range() is an integer specifying how many
 *      // transition colors to generate between the color arguments.
 *
 *      @for color in color-range(powderblue, deeppink, a-adjust(yellow, -80), 5) {
 *          .foo-#(loop.counter) {
 *              background-color: #(color);
 *              }
 *      }
 *
 *      // Yields:
 *      .foo-1 { background-color: #b0e0e6; }
 *      .foo-2 { background-color: #bdbed8; }
 *      ...
 *      .foo-12 { background-color: rgba(255,216,25,.33); }
 *      .foo-13 { background-color: rgba(255,255,0,.2); }
 *
 */
namespace CssCrush;

Plugin::register('loop', array(
    'enable' => function () {
        Hook::add('capture_phase1', 'CssCrush\loop');
    },
    'disable' => function () {
        Hook::remove('capture_phase1', 'CssCrush\loop');
    },
));


function loop () {

    $for_block_patt_base = '@for \s+ (?<var>{{ident}}) \s+ in \s+ (?<list>[^{]+) \s*';
    $for_block_patt = Regex::make('~' . $for_block_patt_base . '{{block}}~xiS');
    $start_for_block_patt = Regex::make('~' . $for_block_patt_base . '\{~xiS');
    $generator_func_patt = Regex::make('~(?<func>range|color-range) {{parens}}~ix');
    $loop_var_patt = '~\#\( \s* (?<arg>[a-zA-Z][\.a-zA-Z0-9-_]*) \s* \)~x';

    // Matching each root level loop construct then evaluating all nested loops and the enclosing loop.
    CssCrush::$process->stream->pregReplaceCallback($for_block_patt, function ($top_m) use (
        $start_for_block_patt,
        $for_block_patt,
        $generator_func_patt,
        $loop_var_patt
    ) {

        $full_match = $top_m[0];

        $count = preg_match_all($start_for_block_patt, $full_match, $loops, PREG_OFFSET_CAPTURE);
        while ($count--) {

            preg_match($for_block_patt, $full_match, $loop, PREG_OFFSET_CAPTURE, $loops[0][$count][1]);

            $list_text = Functions::executeOnString($loop['list'][0]);

            // Resolve the list of items for iteration.
            // Either a generator function or a plain list.
            $items = array();
            if (preg_match($generator_func_patt, $list_text, $m)) {
                $func = strtolower($m['func']);
                $args = Functions::parseArgs($m['parens_content']);
                switch ($func) {
                    case 'range':
                        $items = call_user_func_array('range', $args);
                        break;
                    default:
                        $func = str_replace('-', '_', $func);
                        if (function_exists("CssCrush\loop_$func")) {
                            $items = call_user_func_array("CssCrush\loop_$func", $args);
                        }
                }
            }
            else {
                $items = Util::splitDelimList($list_text);
            }

            // Multiply the text applying each iterated value.
            $source_text = Template::unTokenize($loop['block_content'][0]);
            $loop_output = '';
            $loop_var = $loop['var'][0];

            foreach ($items as $index => $item) {
                $loop_output .= preg_replace_callback($loop_var_patt,
                    function ($m) use ($index, $item, $loop_var) {
                        $arg = $m['arg'];
                        $arg_lc = strtolower($m['arg']);

                        $parent_ref_patt = '~^loop\.parent\.~i';
                        if (preg_match($parent_ref_patt, $arg)) {
                            return '#(' . preg_replace($parent_ref_patt, 'loop.', $arg) . ')';
                        }
                        elseif ($arg_lc === 'loop.counter') {
                            return $index + 1;
                        }
                        elseif ($arg_lc === 'loop.counter0') {
                            return $index;
                        }
                        elseif ($arg === $loop_var || preg_replace('~^loop\.~i', 'loop.', $arg) === "loop.$loop_var") {
                            return $item;
                        }
                        else {
                            // Skip over.
                            return $m[0];
                        }
                    }, $source_text);
            }
            $loop_output = Template::tokenize($loop_output);

            $full_match = substr_replace($full_match, $loop_output, $loop[0][1], strlen($loop[0][0]));
        }

        // Remove unused loop variables before returning.
        return preg_replace($loop_var_patt, '', $full_match);
    });
}


function loop_color_range () {

    $args = func_get_args();

    $source_colors = array();
    while ($args && $color = Color::parse($args[0])) {
        $source_colors[] = $color;
        array_shift($args);
    }

    $steps = max(1, isset($args[0]) ? (int) $args[0] : 1);

    $generated_colors = array();
    foreach ($source_colors as $index => $source_color) {

        $generated_colors[] = new Color($source_color);

        // Generate the in-between colors.
        $next_source_color = isset($source_colors[$index + 1]) ? $source_colors[$index + 1] : null;
        if (! $next_source_color) {
            break;
        }
        for ($i = 0; $i < $steps; $i++) {
            $rgba = array();
            foreach ($source_color as $component_index => $component_value) {
                if ($component_diff = $next_source_color[$component_index] - $component_value) {
                    $component_step = $component_diff / ($steps+1);
                    $rgba[] = min(round($component_value + ($component_step * ($i+1)), 2), 255);
                }
                else {
                    $rgba[] = $component_value;
                }
            }
            $generated_colors[] = new Color($rgba);
        }
    }

    return $generated_colors;
}
