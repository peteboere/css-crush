<?php
/**
 *
 * Custom CSS functions
 *
 */
class csscrush_function {

    // Regex pattern for finding custom functions.
    static public $functionPatt;

    // Stack for function names.
    static protected $customFunctions;

    static public function setMatchPatt () {

        self::$functionPatt = csscrush_regex::createFunctionMatchPatt( array_keys( self::$customFunctions ), true );
    }

    static public function executeCustomFunctions ( &$str, $patt = null, $process_callback = null, $property = null ) {

        // No bracketed expressions, early return.
        if ( false === strpos( $str, '(' ) ) {
            return;
        }

        // Set default pattern if not set.
        if ( is_null( $patt ) ) {
            $patt = csscrush_function::$functionPatt;
        }

        // No custom functions, early return.
        if ( ! preg_match( $patt, $str ) ) {
            return;
        }

        // Find custom function matches.
        $matches = csscrush_regex::matchAll( $patt, $str );

        // Step through the matches from last to first.
        while ( $match = array_pop( $matches ) ) {

            $offset = $match[0][1];

            if ( ! preg_match( csscrush_regex::$patt->balancedParens,
                $str, $parens, PREG_OFFSET_CAPTURE, $offset ) ) {
                continue;
            }

            // No function name default to math expression.
            // Store the raw function name match.
            $raw_fn_name = isset( $match[1] ) ? $match[1][0] : '';
            $fn_name = $raw_fn_name ? $raw_fn_name : 'math';
            if ( '-' === $fn_name ) {
                $fn_name = 'math';
            }

            $opening_paren = $parens[0][1];
            $closing_paren = $opening_paren + strlen( $parens[0][0] );

            // Get the function arguments.
            $args = trim( $parens[1][0] );

            // Workaround the signs.
            $before_operator = '-' === $raw_fn_name ? '-' : '';

            $func_returns = '';

            if ( ! $process_callback ) {
                // If no callback reference it's a built-in.
                if ( array_key_exists( $fn_name, self::$customFunctions ) ) {
                    $func_returns = call_user_func( self::$customFunctions[ $fn_name ], $args );
                }
            }
            else {
                if ( isset( $process_callback[ $fn_name ] ) ) {
                    $func_returns = call_user_func( $process_callback[ $fn_name ], $args, $fn_name, $property );
                }
            }

            // Splice in the function returns.
            $str = substr_replace( $str, "$before_operator$func_returns", $offset, $closing_paren - $offset );
        }
    }


    #############################
    #  API and helpers.

    static public function register ( $name, $callback ) {
        csscrush_function::$customFunctions[ $name ] = $callback;
    }

    static public function deRegister ( $name ) {
        unset( csscrush_function::$customFunctions[ $name ] );
    }

    static public function parseArgs ( $input, $allowSpaceDelim = false ) {
        return csscrush_util::splitDelimList(
            $input, ( $allowSpaceDelim ? '\s*[,\s]\s*' : ',' ) );
    }

    // Intended as a quick arg-list parse for function that take up-to 2 arguments
    // with the proviso the first argument is an ident.
    static public function parseArgsSimple ( $input ) {
        return preg_split( csscrush_regex::$patt->argListSplit, $input, 2 );
    }

    static public function colorAdjust ( $raw_color, array $adjustments ) {

        $hsla = new csscrush_color( $raw_color, true );

        // On failure to parse return input.
        return $hsla->isValid ? $hsla->adjust( $adjustments )->__toString() : $raw_color;
    }
}



#############################
#  Stock custom CSS functions.

function csscrush_fn__math ( $input ) {

    // Strip blacklisted characters
    $input = preg_replace( csscrush_regex::$patt->mathBlacklist, '', $input );

    $result = @eval( "return $input;" );

    return $result === false ? 0 : round( $result, 5 );
}

function csscrush_fn__percent ( $input ) {

    // Strip non-numeric and non delimiter characters
    $input = preg_replace( '![^\d\.\s,]!S', '', $input );

    $args = preg_split( csscrush_regex::$patt->argListSplit, $input, -1, PREG_SPLIT_NO_EMPTY );

    // Use precision argument if it exists, use default otherwise
    $precision = isset( $args[2] ) ? $args[2] : 5;

    // Output zero on failure
    $result = 0;

    // Need to check arguments or we may see divide by zero errors
    if ( count( $args ) > 1 && ! empty( $args[0] ) && ! empty( $args[1] ) ) {

        // Use bcmath if it's available for higher precision

        // Arbitary high precision division
        if ( function_exists( 'bcdiv' ) ) {
            $div = bcdiv( $args[0], $args[1], 25 );
        }
        else {
            $div = $args[0] / $args[1];
        }

        // Set precision percentage value
        if ( function_exists( 'bcmul' ) ) {
            $result = bcmul( (string) $div, '100', $precision );
        }
        else {
            $result = round( $div * 100, $precision );
        }

        // Trim unnecessary zeros and decimals
        $result = trim( (string) $result, '0' );
        $result = rtrim( $result, '.' );
    }

    return $result . '%';
}

function csscrush_fn__hsla_adjust ( $input ) {
    list( $color, $h, $s, $l, $a ) = array_pad( csscrush_function::parseArgs( $input, true ), 5, 0 );
    return csscrush_function::colorAdjust( $color, array( $h, $s, $l, $a ) );
}

function csscrush_fn__hsl_adjust ( $input ) {
    list( $color, $h, $s, $l ) = array_pad( csscrush_function::parseArgs( $input, true ), 4, 0 );
    return csscrush_function::colorAdjust( $color, array( $h, $s, $l, 0 ) );
}

function csscrush_fn__h_adjust ( $input ) {
    list( $color, $h ) = array_pad( csscrush_function::parseArgs( $input, true ), 2, 0 );
    return csscrush_function::colorAdjust( $color, array( $h, 0, 0, 0 ) );
}

function csscrush_fn__s_adjust ( $input ) {
    list( $color, $s ) = array_pad( csscrush_function::parseArgs( $input, true ), 2, 0 );
    return csscrush_function::colorAdjust( $color, array( 0, $s, 0, 0 ) );
}

function csscrush_fn__l_adjust ( $input ) {
    list( $color, $l ) = array_pad( csscrush_function::parseArgs( $input, true ), 2, 0 );
    return csscrush_function::colorAdjust( $color, array( 0, 0, $l, 0 ) );
}

function csscrush_fn__a_adjust ( $input ) {
    list( $color, $a ) = array_pad( csscrush_function::parseArgs( $input, true ), 2, 0 );
    return csscrush_function::colorAdjust( $color, array( 0, 0, 0, $a ) );
}

csscrush_function::register( 'math', 'csscrush_fn__math' );
csscrush_function::register( 'percent', 'csscrush_fn__percent' );
csscrush_function::register( 'pc', 'csscrush_fn__percent' );
csscrush_function::register( 'hsla-adjust', 'csscrush_fn__hsla_adjust' );
csscrush_function::register( 'hsl-adjust', 'csscrush_fn__hsl_adjust' );
csscrush_function::register( 'h-adjust', 'csscrush_fn__h_adjust' );
csscrush_function::register( 's-adjust', 'csscrush_fn__s_adjust' );
csscrush_function::register( 'l-adjust', 'csscrush_fn__l_adjust' );
csscrush_function::register( 'a-adjust', 'csscrush_fn__a_adjust' );

