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

    public function __construct($raw_selector)
    {
        // Look for rooting prefix.
        if (strpos($raw_selector, '^') === 0) {
            $raw_selector = ltrim($raw_selector, "^ \n\r\t");
            $this->allowPrefix = false;
        }

        // Take readable value from original un-altered state.
        $this->readableValue = Selector::makeReadable($raw_selector);

        $this->value = Process::applySelectorAliases($raw_selector);
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
        if (! Stream::endsWith($this->readableValue, $pseudo)) {

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
}
