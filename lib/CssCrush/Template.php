<?php
/**
 *
 *  Generalized 'in CSS' templating.
 *
 */
namespace CssCrush;

class Template
{
    // Positional argument default values.
    public $defaults = array();

    // The number of expected arguments.
    public $argCount = 0;

    public $substitutions;

    // The string passed in with arg calls replaced by tokens.
    public $string;

    public function __construct($str)
    {
        static $templateFunctions;
        if (! $templateFunctions) {
            $templateFunctions = new Functions();
        }

        $str = Template::unTokenize($str);

        // Parse all arg function calls in the passed string,
        // callback creates default values.
        $self = $this;
        $captureCallback = function ($str) use (&$self)
        {
            $args = Functions::parseArgsSimple($str);

            $position = array_shift($args);

            // Match the argument index integer.
            if (! isset($position) || ! ctype_digit($position)) {
                return '';
            }

            // Store the default value.
            $defaultValue = isset($args[0]) ? $args[0] : null;
            if (isset($defaultValue)) {
                $self->defaults[$position] = $defaultValue;
            }

            // Update argument count.
            $argNumber = ((int) $position) + 1;
            $self->argCount = max($self->argCount, $argNumber);

            return "?a$position?";
        };

        $templateFunctions->register['#'] = $captureCallback;
        $templateFunctions->register['arg'] = $captureCallback;

        $this->string = $templateFunctions->apply($str);
    }

    public function __invoke(array $args = null, $str = null)
    {
        $str = isset($str) ? $str : $this->string;

        // Apply passed arguments as priority.
        if (isset($args)) {

            list($find, $replace) = $this->prepare($args, false);
        }

        // Secondly use prepared substitutions if available.
        elseif ($this->substitutions) {

            list($find, $replace) = $this->substitutions;
        }

        // Apply substitutions.
        $str = isset($find) ? str_replace($find, $replace, $str) : $str;

        return Template::tokenize($str);
    }

    public function getArgValue($index, &$args)
    {
        // First lookup a passed value.
        if (isset($args[$index]) && $args[$index] !== 'default') {

            return $args[$index];
        }

        // Get a default value.
        $default = isset($this->defaults[$index]) ? $this->defaults[$index] : '';

        // Recurse for nested arg() calls.
        while (preg_match(Regex::$patt->a_token, $default, $m)) {
            $default = str_replace(
                $m[0],
                $this->getArgValue((int) $m[1], $args),
                $default);
        }

        return $default;
    }

    public function prepare(array $args, $persist = true)
    {
        // Create table of substitutions.
        $find = array();
        $replace = array();

        if ($this->argCount) {

            $argIndexes = range(0, $this->argCount-1);

            foreach ($argIndexes as $index) {
                $find[] = "?a$index?";
                $replace[] = $this->getArgValue($index, $args);
            }
        }

        $substitutions = array($find, $replace);

        // Persist substitutions by default.
        if ($persist) {
            $this->substitutions = $substitutions;
        }

        return $substitutions;
    }

    public static function tokenize($str)
    {
        $str = Crush::$process->tokens->capture($str, 's');
        $str = Crush::$process->tokens->capture($str, 'u');

        return $str;
    }

    public static function unTokenize($str)
    {
        $str = Crush::$process->tokens->restore($str, array('u', 's'), true);

        return $str;
    }
}
