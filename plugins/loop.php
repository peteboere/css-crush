<?php
/**
 * For...in loops with lists and generator functions
 *
 * @see docs/plugins/loop.md
 */
namespace CssCrush;

Plugin::register('loop', array(
    'enable' => function ($process) {
        $process->hooks->add('capture_phase1', 'CssCrush\loop');
    }
));


define('CssCrush\LOOP_VAR_PATT',
    '~\#\( \s* (?<arg>[a-zA-Z][\.a-zA-Z0-9-_]*) \s* \)~x');
define('CssCrush\LOOP_PATT',
    Regex::make('~(?<expression> @for \s+ (?<var>{{ident}}) \s+ in \s+ (?<list>[^{]+) ) \s* {{block}}~xiS'));


function loop($process) {

    $process->string->pregReplaceCallback(LOOP_PATT, function ($m) {

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

    $list_text = Crush::$process->functions->apply($list_text);
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
