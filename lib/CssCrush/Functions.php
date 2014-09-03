<?php
/**
 *
 * Custom CSS functions
 *
 */
namespace CssCrush;

class Functions
{
    protected static $builtins = array(

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

    public $register = array();

    protected $pattern;

    protected $patternOptions;

    public function __construct($register = array())
    {
        $this->register = $register;
    }

    public function add($name, $callback)
    {
        $this->register[$name] = $callback;
    }

    public function remove($name)
    {
        unset($this->register[$name]);
    }

    public function setPattern($useAll = false)
    {
        if ($useAll) {
            $this->register = self::$builtins + $this->register + csscrush_add_function();
        }

        $this->pattern = Functions::makePattern(array_keys($this->register));
    }

    public function apply($str, \stdClass $context = null)
    {
        if (strpos($str, '(') === false) {
            return $str;
        }

        if (! $this->pattern) {
            $this->setPattern();
        }

        if (! preg_match($this->pattern, $str)) {
            return $str;
        }

        $matches = Regex::matchAll($this->pattern, $str);

        while ($match = array_pop($matches)) {

            list($function, $offset) = $match['function'];

            if (! preg_match(Regex::$patt->parens, $str, $parens, PREG_OFFSET_CAPTURE, $offset)) {
                continue;
            }

            $openingParen = $parens[0][1];
            $closingParen = $openingParen + strlen($parens[0][0]);
            $rawArgs = trim($parens['parens_content'][0]);

            // Update the context function identifier.
            if ($context) {
                $context->function = $function;
            }

            $returns = '';
            if (isset($this->register[$function])) {
                $fn = $this->register[$function];
                if (is_array($fn) && !empty($fn['parse_args'])) {
                    $returns = $fn['callback'](self::parseArgs($rawArgs), $context);
                }
                else {
                    $returns = $fn($rawArgs, $context);
                }
            }

            $str = substr_replace($str, $returns, $offset, $closingParen - $offset);
        }

        return $str;
    }


    #############################
    #  API and helpers.

    public static function parseArgs($input, $allowSpaceDelim = false)
    {
        $options = array();
        if ($allowSpaceDelim) {
            $options['regex'] = Regex::$patt->argListSplit;
        }

        return Util::splitDelimList($input, $options);
    }

    /*
        Quick argument list parsing for functions that take 1 or 2 arguments
        with the proviso the first argument is an ident.
    */
    public static function parseArgsSimple($input)
    {
        return preg_split(Regex::$patt->argListSplit, $input, 2);
    }

    public static function makePattern($functionNames)
    {
        $idents = array();
        $nonIdents = array();

        foreach ($functionNames as $functionName) {
            if (preg_match(Regex::$patt->ident, $functionName[0])) {
                $idents[] = preg_quote($functionName);
            }
            else {
                $nonIdents[] = preg_quote($functionName);
            }
        }

        $flatList = '';
        if (! $idents) {
            $flatList = implode('|', $nonIdents);
        }
        else {
            $idents = '{{ LB }}(?:' . implode('|', $idents) . ')';
            $flatList = $nonIdents ? '(?:' . implode('|', $nonIdents) . "|$idents)" : $idents;
        }

        return Regex::make("~(?<function>$flatList)\(~iS");
    }
}


#############################
#  Stock CSS functions.

function fn__math($input) {

    list($expression, $unit) = array_pad(Functions::parseArgs($input), 2, '');

    // Swap in math constants.
    $expression = preg_replace(
        array('~\bpi\b~i'),
        array(M_PI),
        $expression);

    // Strip blacklisted characters.
    $expression = preg_replace('~[^\.0-9\*\/\+\-\(\)]~S', '', $expression);

    $result = @eval("return $expression;");

    return ($result === false ? 0 : round($result, 5)) . $unit;
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
    return Color::test($color) ? Color::colorAdjust($color, array($h, $s, $l, $a)) : '';
}

function fn__hsl_adjust($input) {
    list($color, $h, $s, $l) = array_pad(Functions::parseArgs($input, true), 4, 0);
    return Color::test($color) ? Color::colorAdjust($color, array($h, $s, $l, 0)) : '';
}

function fn__h_adjust($input) {
    list($color, $h) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::test($color) ? Color::colorAdjust($color, array($h, 0, 0, 0)) : '';
}

function fn__s_adjust($input) {
    list($color, $s) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::test($color) ? Color::colorAdjust($color, array(0, $s, 0, 0)) : '';
}

function fn__l_adjust($input) {
    list($color, $l) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::test($color) ? Color::colorAdjust($color, array(0, 0, $l, 0)) : '';
}

function fn__a_adjust($input) {
    list($color, $a) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::test($color) ? Color::colorAdjust($color, array(0, 0, 0, $a)) : '';
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

    // Context property is required.
    if (! count($args) || ! isset($context->property)) {
        return '';
    }

    list($target, $property, $fallback) = $args + array(null, $context->property, null);

    if (strtolower($property) === 'default') {
        $property = $context->property;
    }

    if (! preg_match(Regex::$patt->rooted_ident, $target)) {
        $target = Selector::makeReadable($target);
    }

    $targetRule = null;
    $references =& Crush::$process->references;

    switch (strtolower($target)) {
        case 'parent':
            $targetRule = $context->rule->parent;
            break;
        case 'previous':
            $targetRule = $context->rule->previous;
            break;
        case 'next':
            $targetRule = $context->rule->next;
            break;
        case 'top':
            $targetRule = $context->rule->parent;
            while ($targetRule && $targetRule->parent && $targetRule = $targetRule->parent);
            break;
        default:
            if (isset($references[$target])) {
                $targetRule = $references[$target];
            }
            break;
    }

    $result = '';
    if ($targetRule) {
        $targetRule->declarations->process($targetRule);
        $targetRule->declarations->expandData('queryData', $property);
        if (isset($targetRule->declarations->queryData[$property])) {
            $result = $targetRule->declarations->queryData[$property];
        }
    }

    if ($result === '' && isset($fallback)) {
        $result = $fallback;
    }

    return $result;
}
