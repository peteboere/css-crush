<?php
/**
 *
 * Selector objects.
 *
 */
class CssCrush_Selector
{
    public $value;
    public $readableValue;
    public $allowPrefix = true;

    public function __construct ($raw_selector, $associated_rule = null)
    {
        // Look for rooting prefix.
        if (strpos($raw_selector, '^') === 0) {
            $raw_selector = ltrim($raw_selector, "^ \n\r\t");
            $this->allowPrefix = false;
        }

        // Take readable value from original un-altered state.
        $this->readableValue = CssCrush_Selector::makeReadable($raw_selector);

        CssCrush_Process::applySelectorAliases($raw_selector);

        // Capture top-level paren groups.
        CssCrush::$process->captureParens($raw_selector);

        $this->value = $raw_selector;
    }

    public function __toString ()
    {
        if (! CssCrush::$process->minifyOutput) {
            $this->value = CssCrush_Selector::normalizeWhiteSpace($this->value);
        }
        return $this->value;
    }

    public function appendPseudo ($pseudo)
    {
        // Check to avoid doubling-up
        if (! CssCrush_Stream::endsWith($this->readableValue, $pseudo)) {

            $this->readableValue .= $pseudo;
            $this->value .= $pseudo;
        }
        return $this->readableValue;
    }

    static public function normalizeWhiteSpace ($str)
    {
        // Create space around combinators, then normalize whitespace.
        $str = preg_replace('~([>+]|\~(?!=))~S', ' $1 ', $str);
        return CssCrush_Util::normalizeWhiteSpace($str);
    }

    static function makeReadable ($str)
    {
        // Quick test for paren tokens.
        if (strpos($str, '?p') !== false) {
            $str = CssCrush::$process->restoreTokens($str, 'p');
        }

        $str = CssCrush_Selector::normalizeWhiteSpace($str);

        // Quick test for string tokens.
        if (strpos($str, '?s') !== false) {
            $str = CssCrush::$process->restoreTokens($str, 's');
        }

        return $str;
    }
}
