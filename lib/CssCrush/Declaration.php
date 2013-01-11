<?php
/**
 *
 * Declaration objects.
 *
 */
class CssCrush_Declaration
{
    public $property;
    public $canonicalProperty;
    public $vendor;
    public $functions;
    public $value;
    public $index;
    public $skip;
    public $important;
    public $isValid = true;

    public function __construct ( $prop, $value, $contextIndex = 0 )
    {
        $regex = CssCrush_Regex::$patt;

        // Normalize input. Lowercase the property name.
        $prop = strtolower( trim( $prop ) );
        $value = trim( $value );

        // Check the input.
        if ( $prop === '' || $value === '' || $value === null ) {
            $this->isValid = false;

            return;
        }

        // Test for escape tilde.
        if ( $skip = strpos( $prop, '~' ) === 0 ) {
            $prop = substr( $prop, 1 );
        }

        // Store the canonical property name.
        // Store the vendor mark if one is present.
        if ( preg_match( $regex->vendorPrefix, $prop, $vendor ) ) {
            $canonical_property = $vendor[2];
            $vendor = $vendor[1];
        }
        else {
            $vendor = null;
            $canonical_property = $prop;
        }

        // Check for !important.
        if ( ( $important = stripos( $value, '!important' ) ) !== false ) {
            $value = rtrim( substr( $value, 0, $important ) );
            $important = true;
        }

        // Ignore declarations with null css values.
        if ( $value === false || $value === '' ) {
            $this->isValid = false;

            return;
        }

        // Apply custom functions.
        if ( ! $skip ) {
            CssCrush_Function::executeOnString( $value );
        }

        // Capture all remaining paren pairs.
        CssCrush::$process->captureParens( $value );

        // Create an index of all regular functions in the value.
        $functions = array();
        if ( preg_match_all( $regex->function, $value, $m ) ) {
            foreach ( $m[2] as $index => $fn_name ) {
                $functions[ strtolower( $fn_name ) ] = true;
            }
        }

        $this->property          = $prop;
        $this->canonicalProperty = $canonical_property;
        $this->vendor            = $vendor;
        $this->functions         = $functions;
        $this->index             = $contextIndex;
        $this->value             = $value;
        $this->skip              = $skip;
        $this->important         = $important;
    }

    public function __toString ()
    {
        if ( CssCrush::$process->minifyOutput ) {
            $whitespace = '';
        }
        else {
            $whitespace = ' ';
        }
        $important = $this->important ? "$whitespace!important" : '';

        return "$this->property:$whitespace$this->value$important";
    }

    public function getFullValue ()
    {
        return CssCrush::$process->restoreTokens( $this->value, 'p' );
    }
}
