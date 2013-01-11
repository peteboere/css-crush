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

    static function makeReadableSelector ( $selector_string )
    {
        // Quick test for paren tokens.
        if ( strpos( $selector_string, '?p' ) !== false ) {
            $selector_string = CssCrush::$process->restoreTokens( $selector_string, 'p' );
        }

        // Create space around combinators, then normalize whitespace.
        $selector_string = preg_replace( '#([>+]|~(?!=))#', ' $1 ', $selector_string );
        $selector_string = CssCrush_Util::normalizeWhiteSpace( $selector_string );

        // Quick test for string tokens.
        if ( strpos( $selector_string, '?s' ) !== false ) {
            $selector_string = CssCrush::$process->restoreTokens( $selector_string, 's' );
        }

        // Quick test for double-colons for backwards compat.
        if ( strpos( $selector_string, '::' ) !== false ) {
            $selector_string = preg_replace( '!::(after|before|first-(?:letter|line))!iS', ':$1', $selector_string );
        }

        return $selector_string;
    }

    public function __construct ( $raw_selector, $associated_rule = null )
    {
        if ( strpos( $raw_selector, '^' ) === 0 ) {

            $raw_selector = ltrim( $raw_selector, "^ \n\r\t" );
            $this->allowPrefix = false;
        }

        $this->readableValue = self::makeReadableSelector( $raw_selector );
        $this->value = $raw_selector;
    }

    public function __toString ()
    {
        return $this->readableValue;
    }

    public function appendPseudo ( $pseudo )
    {
        // Check to avoid doubling-up
        if ( ! CssCrush_Stream::endsWith( $this->readableValue, $pseudo ) ) {

            $this->readableValue .= $pseudo;
            $this->value .= $pseudo;
        }
        return $this->readableValue;
    }
}
