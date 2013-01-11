<?php
/**
 *
 *  Argument list management for mixins and fragments.
 *
 */
class CssCrush_ArgList implements Countable
{
    // Positional argument default values.
    public $defaults = array();

    // The number of expected arguments.
    public $argCount = 0;

    // The string passed in with arg calls replaced by tokens.
    public $string;

    public function __construct ( $str )
    {
        // Parse all arg function calls in the passed string, callback creates default values
        CssCrush_Function::executeOnString( $str, 
                CssCrush_Regex::$patt->argFunction, array(
                    'arg' => array( $this, 'store' )
                ));
        $this->string = $str;
    }

    public function store ( $raw_argument )
    {
        $args = CssCrush_Function::parseArgsSimple( $raw_argument );

        // Match the argument index integer
        if ( ! ctype_digit( $args[0] ) ) {

            // On failure to match an integer, return an empty string
            return '';
        }

        // Get the match from the array
        $position_match = $args[0];

        // Store the default value
        $default_value = isset( $args[1] ) ? $args[1] : null;

        if ( ! is_null( $default_value ) ) {
            $this->defaults[ $position_match ] = trim( $default_value );
        }

        // Update the mixin argument count
        $argNumber = ( (int) $position_match ) + 1;
        $this->argCount = max( $this->argCount, $argNumber );

        // Return the argument token
        return "?arg$position_match?";
    }

    public function getArgValue ( $index, &$args )
    {
        // First lookup a passed value
        if ( isset( $args[ $index ] ) && $args[ $index ] !== 'default' ) {
            return $args[ $index ];
        }

        // Get a default value
        $default = isset( $this->defaults[ $index ] ) ? $this->defaults[ $index ] : '';

        // Recurse for nested arg() calls
        if ( preg_match( CssCrush_Regex::$patt->aToken, $default, $m ) ) {

            $default = $this->getArgValue( (int) $m[1], $args );
        }
        return $default;
    }

    public function getSubstitutions ( $args )
    {
        $argIndexes = range( 0, $this->argCount-1 );

        // Create table of substitutions
        $find = array();
        $replace = array();

        foreach ( $argIndexes as $index ) {

            $find[] = "?arg$index?";
            $replace[] = $this->getArgValue( $index, $args );
        }

        return array( $find, $replace );
    }

    public function count ()
    {
        return $this->argCount;
    }
}
