<?php
/**
 *
 * Custom CSS functions
 *
 */
namespace CssCrush;

class Functions
{
    // Regex pattern for finding custom functions.
    public static $functionPatt;

    public static $functions;

    static protected $customFunctions = array();

    static protected $builtinFunctions = array(

        // These functions must come first in this order.
        'query' => 'CssCrush\fn__query',

        // These functions can be any order.
        'math' => 'CssCrush\fn__math',
        'percent' => 'CssCrush\fn__percent',
        'pc' => 'CssCrush\fn__percent',
        'hsla-adjust' => 'CssCrush\fn__hsla_adjust',
        'hsl-adjust' => 'CssCrush\fn__hsl_adjust',
        'h-adjust' => 'CssCrush\fn__h_adjust',
        's-adjust' => 'CssCrush\fn__s_adjust',
        'l-adjust' => 'CssCrush\fn__l_adjust',
        'a-adjust' => 'CssCrush\fn__a_adjust',
    );

    public static function setMatchPatt()
    {
        self::$functions = self::$builtinFunctions + self::$customFunctions;
        self::$functionPatt = Regex::makeFunctionPatt(
            array_keys(self::$functions), array('bare_paren' => true));
    }

    public static function executeOnString($str, $patt = null, $process_callback = null, \stdClass $context = null)
    {
        // No bracketed expressions, early return.
        if (strpos($str, '(') === false) {

            return $str;
        }

        // Set default pattern if not set.
        if (! isset($patt)) {
            $patt = Functions::$functionPatt;
        }

        // No custom functions, early return.
        if (! preg_match($patt, $str)) {

            return $str;
        }

        // Always pass in a context object.
        if (! $context) {
            $context = new \stdClass();
        }

        // Find custom function matches.
        $matches = Regex::matchAll($patt, $str);

        // Step through the matches from last to first.
        while ($match = array_pop($matches)) {

            $offset = $match[0][1];

            if (! preg_match(Regex::$patt->parens, $str, $parens, PREG_OFFSET_CAPTURE, $offset)) {
                continue;
            }

            // No function name default to math expression.
            // Store the raw function name match.
            $raw_fn_name = isset($match[1]) ? strtolower($match[1][0]) : '';
            $fn_name = $raw_fn_name ? $raw_fn_name : 'math';
            if ('-' === $fn_name) {
                $fn_name = 'math';
            }

            $opening_paren = $parens[0][1];
            $closing_paren = $opening_paren + strlen($parens[0][0]);

            // Get the function arguments.
            $raw_args = trim($parens['parens_content'][0]);

            // Workaround the signs.
            $before_operator = '-' === $raw_fn_name ? '-' : '';

            $func_returns = '';
            $context->function = $fn_name;

            // First look for function as directly passed.
            if (isset($process_callback[$fn_name])) {

                $func_returns = $process_callback[$fn_name]($raw_args, $context);
            }
            // Secondly look for built-in function.
            elseif (isset(self::$functions[$fn_name])) {

                $func = self::$functions[$fn_name];
                $func_returns = $func($raw_args, $context);
            }

            // Splice in the function result.
            $str = substr_replace($str, "$before_operator$func_returns", $offset, $closing_paren - $offset);
        }

        return $str;
    }


    #############################
    #  API and helpers.

    public static function register($name, $callback)
    {
        Functions::$customFunctions[$name] = $callback;
    }

    public static function deRegister($name)
    {
        unset(Functions::$customFunctions[$name]);
    }

    public static function parseArgs($input, $allowSpaceDelim = false)
    {
        return Util::splitDelimList(
            $input, ($allowSpaceDelim ? '\s*[,\s]\s*' : ','));
    }

    // Intended as a quick arg-list parse for function that take up-to 2 arguments
    // with the proviso the first argument is an ident.
    public static function parseArgsSimple($input)
    {
        return preg_split(Regex::$patt->argListSplit, $input, 2);
    }
}


#############################
#  Stock CSS functions.

function fn__math($input) {

    // Swap in math constants.
    $input = preg_replace(
        array('~\bpi\b~i'),
        array(M_PI),
        $input);

    // Strip blacklisted characters.
    $input = preg_replace(Regex::$patt->mathBlacklist, '', $input);

    $result = @eval("return $input;");

    return $result === false ? 0 : round($result, 5);
}

function fn__percent($input) {

    // Strip non-numeric and non delimiter characters
    $input = preg_replace('~[^\d\.\s,]~S', '', $input);

    $args = preg_split(Regex::$patt->argListSplit, $input, -1, PREG_SPLIT_NO_EMPTY);

    // Use precision argument if it exists, use default otherwise
    $precision = isset($args[2]) ? $args[2] : 5;

    // Output zero on failure
    $result = 0;

    // Need to check arguments or we may see divide by zero errors
    if (count($args) > 1 && ! empty($args[0]) && ! empty($args[1])) {

        // Use bcmath if it's available for higher precision

        // Arbitary high precision division
        if (function_exists('bcdiv')) {
            $div = bcdiv($args[0], $args[1], 25);
        }
        else {
            $div = $args[0] / $args[1];
        }

        // Set precision percentage value
        if (function_exists('bcmul')) {
            $result = bcmul((string) $div, '100', $precision);
        }
        else {
            $result = round($div * 100, $precision);
        }

        // Trim unnecessary zeros and decimals
        $result = trim((string) $result, '0');
        $result = rtrim($result, '.');
    }

    return $result . '%';
}

function fn__hsla_adjust($input) {
    list($color, $h, $s, $l, $a) = array_pad(Functions::parseArgs($input, true), 5, 0);
    return Color::colorAdjust($color, array($h, $s, $l, $a));
}

function fn__hsl_adjust($input) {
    list($color, $h, $s, $l) = array_pad(Functions::parseArgs($input, true), 4, 0);
    return Color::colorAdjust($color, array($h, $s, $l, 0));
}

function fn__h_adjust($input) {
    list($color, $h) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array($h, 0, 0, 0));
}

function fn__s_adjust($input) {
    list($color, $s) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array(0, $s, 0, 0));
}

function fn__l_adjust($input) {
    list($color, $l) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array(0, 0, $l, 0));
}

function fn__a_adjust($input) {
    list($color, $a) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array(0, 0, 0, $a));
}

function fn__this($input, $context) {

    $args = Functions::parseArgsSimple($input);
    $property = $args[0];

    // Function relies on a context rule, bail if none.
    if (! isset($context->rule)) {
        return '';
    }
    $rule = $context->rule;

    $rule->declarations->expandData('data', $property);

    if (isset($rule->declarations->data[$property])) {

        return $rule->declarations->data[$property];
    }

    // Fallback value.
    elseif (isset($args[1])) {

        return $args[1];
    }

    return '';
}

function fn__query($input, $context) {

    $args = Functions::parseArgs($input);

    // Function relies on a context property, bail if none.
    if (count($args) < 1 || ! isset($context->property)) {
        return '';
    }

    $call_property = $context->property;
    $references =& Crush::$process->references;

    // Resolve arguments.
    $name = array_shift($args);
    $property = $call_property;

    if (isset($args[0])) {
        $args[0] = strtolower($args[0]);
        if ($args[0] !== 'default') {
            $property = array_shift($args);
        }
        else {
            array_shift($args);
        }
    }
    $default = isset($args[0]) ? $args[0] : null;

    if (! preg_match(Regex::$patt->rooted_ident, $name)) {
        $name = Selector::makeReadable($name);
    }

    // If a rule reference is found, query its data.
    $result = '';
    if (isset($references[$name])) {
        $query_rule = $references[$name];
        $query_rule->declarations->process($query_rule);
        $query_rule->declarations->expandData('queryData', $property);

        if (isset($query_rule->declarations->queryData[$property])) {
            $result = $query_rule->declarations->queryData[$property];
        }
    }

    if ($result === '' && isset($default)) {
        $result = $default;
    }

    return $result;
}
