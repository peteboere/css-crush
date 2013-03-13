<?php
/**
 *
 *  General use CSS templating with arguments.
 *
 */
class CssCrush_Template implements Countable
{
    // Positional argument default values.
    public $defaults = array();

    // The number of expected arguments.
    public $argCount = 0;

    public $substitutions;

    // The string passed in with arg calls replaced by tokens.
    public $string;

    public function __construct ($str)
    {
        // Parse all arg function calls in the passed string,
        // callback creates default values.
        CssCrush_Function::executeOnString($str,
                CssCrush_Regex::$patt->argFunction, array(
                    'arg' => array($this, 'capture'),
                    '#' => array($this, 'capture'),
                ));
        $this->string = $str;
    }

    public function capture ($str)
    {
        $args = CssCrush_Function::parseArgsSimple($str);

        $position = array_shift($args);

        // Match the argument index integer.
        if (! isset($position) || ! ctype_digit($position)) {

            // On failure to match an integer return empty string.
            return '';
        }

        // Store the default value.
        $default_value = isset($args[0]) ? $args[0] : null;

        if (isset($default_value)) {
            $this->defaults[$position] = $default_value;
        }

        // Update the argument count.
        $argNumber = ((int) $position) + 1;
        $this->argCount = max($this->argCount, $argNumber);

        return "?a$position?";
    }

    public function getArgValue ($index, &$args)
    {
        // First lookup a passed value.
        if (isset($args[$index]) && $args[$index] !== 'default') {

            return $args[$index];
        }

        // Get a default value.
        $default = isset($this->defaults[$index]) ? $this->defaults[$index] : '';

        // Recurse for nested arg() calls.
        if (preg_match(CssCrush_Regex::$patt->a_token, $default, $m)) {

            $default = $this->getArgValue((int) $m[1], $args);
        }

        return $default;
    }

    public function prepare (array $args, $persist = true)
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

    public function reset ()
    {
        unset($this->substitutions);
    }

    public function apply (array $args = null, $str = null)
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

        return isset($find) ? str_replace($find, $replace, $str) : $str;
    }

    public function count ()
    {
        return $this->argCount;
    }
}
