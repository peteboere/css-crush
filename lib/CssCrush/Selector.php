<?php
/**
 *
 * Selector objects.
 *
 */
namespace CssCrush;

class Selector
{
    public $value;
    public $readableValue;
    public $allowPrefix = true;

    public function __construct($rawSelector)
    {
        // Look for rooting prefix.
        if (strpos($rawSelector, '^') === 0) {
            $rawSelector = ltrim($rawSelector, "^ \n\r\t");
            $this->allowPrefix = false;
        }

        $this->readableValue = Selector::makeReadable($rawSelector);

        $this->value = Selector::expandAliases($rawSelector);
    }

    public function __toString()
    {
        if (Crush::$process->minifyOutput) {
            // Trim whitespace around selector combinators.
            $this->value = preg_replace('~ ?([>\~+]) ?~S', '$1', $this->value);
        }
        else {
            $this->value = Selector::normalizeWhiteSpace($this->value);
        }
        return $this->value;
    }

    public function appendPseudo($pseudo)
    {
        // Check to avoid doubling-up.
        if (! StringObject::endsWith($this->readableValue, $pseudo)) {

            $this->readableValue .= $pseudo;
            $this->value .= $pseudo;
        }
        return $this->readableValue;
    }

    public static function normalizeWhiteSpace($str)
    {
        // Create space around combinators, then normalize whitespace.
        return Util::normalizeWhiteSpace(preg_replace('~([>+]|\~(?!=))~S', ' $1 ', $str));
    }

    public static function makeReadable($str)
    {
        $str = Selector::normalizeWhiteSpace($str);

        // Quick test for string tokens.
        if (strpos($str, '?s') !== false) {
            $str = Crush::$process->tokens->restore($str, 's');
        }

        return $str;
    }

    public static function expandAliases($str)
    {
        $process = Crush::$process;

        if (! $process->selectorAliases || ! preg_match($process->selectorAliasesPatt, $str)) {
            return $str;
        }

        while (preg_match_all($process->selectorAliasesPatt, $str, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {

            $alias_call = end($m);
            $alias_name = strtolower($alias_call[1][0]);

            $start = $alias_call[0][1];
            $length = strlen($alias_call[0][0]);
            $args = array();

            // It's a function alias if a start paren is matched.
            if (isset($alias_call[2])) {

                // Parse argument list.
                if (preg_match(Regex::$patt->parens, $str, $parens, PREG_OFFSET_CAPTURE, $start)) {
                    $args = Functions::parseArgs($parens[2][0]);

                    // Amend offsets.
                    $paren_start = $parens[0][1];
                    $paren_len = strlen($parens[0][0]);
                    $length = ($paren_start + $paren_len) - $start;
                }
            }

            $str = substr_replace($str, $process->selectorAliases[$alias_name]($args), $start, $length);
        }

        return $str;
    }
}
