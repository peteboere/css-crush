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
 *      @for base in range(2, 24) {
 *          @for i in range(1, #(base)) {
 *              .grid-#(i)-of-#(base) {
 *                  width: (#(i) / #(base) * 100)%;
 *                  }
 *          }
 *      }
 *
 *      // Yields:
 *      .grid-1-of-2 { width: 50%; }
 *      .grid-2-of-2 { width: 100%; }
 *      ...
 *      .grid-23-of-24 { width: 95.83333%; }
 *      .grid-24-of-24 { width: 100%; }
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


define('CssCrush\LOOP_VAR_PATT',
    '~\#\( \s* (?<arg>[a-zA-Z][\.a-zA-Z0-9-_]*) \s* \)~x');
define('CssCrush\LOOP_PATT',
    Regex::make('~(?<expression> @for \s+ (?<var>{{ident}}) \s+ in \s+ (?<list>[^{]+) ) \s* {{block}}~xiS'));


function loop() {

    CssCrush::$process->stream->pregReplaceCallback(LOOP_PATT, function ($m) {

        return Template::tokenize(loop_unroll(Template::unTokenize($m[0])));
    });
}

function loop_unroll($str, $context = array()) {

    $str = loop_apply_scope($str, $context);

    while (preg_match(LOOP_PATT, $str, $m, PREG_OFFSET_CAPTURE)) {

        $str = substr_replace($str, '', $m[0][1], strlen($m[0][0]));

        $context['loop.parent.counter'] = isset($context['loop.counter']) ? $context['loop.counter'] : -1;
        $context['loop.parent.counter0'] = isset($context['loop.counter0']) ? $context['loop.counter0'] : -1;

        foreach (loop_resolve_list($m['list'][0]) as $index => $value) {

            $str .= loop_unroll($m['block_content'][0], array(
                            $m['var'][0] => $value,
                            'loop.counter' => $index + 1,
                            'loop.counter0' => $index,
                        ) + $context);
        }
    }

    return $str;
}

function loop_resolve_list($list_text) {

    // Resolve the list of items for iteration.
    // Either a generator function or a plain list.
    $items = array();

    $list_text = Functions::executeOnString($list_text);
    $generator_func_patt = Regex::make('~(?<func>range|color-range) {{parens}}~ix');

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

    return $items;
}

function loop_apply_scope($str, $context) {

    // Need to temporarily hide child block scopes.
    $child_scopes = array();

    $str = preg_replace_callback(LOOP_PATT, function ($m) use (&$child_scopes) {
        $label = '?B' . count($child_scopes) . '?';
        $child_scopes[$label] = $m['block'];
        return $m['expression'] . $label;
    }, $str);

    $str = preg_replace_callback(LOOP_VAR_PATT, function ($m) use ($context) {

        // Normalize casing of built-in loop variables.
        // User variables are case-sensitive.
        $arg = preg_replace_callback('~^loop\.(parent\.)?counter0?$~i', function ($m) {
            return strtolower($m[0]);
        }, $m['arg']);

        return isset($context[$arg]) ? $context[$arg] : '';
    }, $str);

    return str_replace(array_keys($child_scopes), array_values($child_scopes), $str);
}

function loop_color_range() {

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
