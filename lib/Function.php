<?php
/**
 *
 * Custom CSS functions
 *
 */
class csscrush_function {

    // Regex pattern for finding custom functions
    static public $functionPatt;

    // Cache for function names
    static public $functionList;

    static public function init () {

        // Set the custom function regex pattern
        self::$functionList = self::getFunctions();
        self::$functionPatt = csscrush_regex::createFunctionMatchPatt( self::$functionList, true );
    }

    static public function getFunctions () {

        // Fetch custom function names
        // Include subtraction operator
        $fn_methods = array( '-' );
        $all_methods = get_class_methods( __CLASS__ );
        foreach ( $all_methods as &$_method ) {
            $prefix = 'css_fn__';
            if ( ( $pos = strpos( $_method, $prefix ) ) === 0 ) {
                $fn_methods[] = str_replace( '_', '-', substr( $_method, strlen( $prefix ) ) );
            }
        }
        return $fn_methods;
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

            // Workaround the minus.
            $minus_before = '-' === $raw_fn_name ? '-' : '';

            $func_returns = '';

            if ( ! $process_callback ) {

                // If no callback reference it's a built-in.
                if ( in_array( $fn_name, self::$functionList ) ) {
                    $fn_name_clean = str_replace( '-', '_', $fn_name );
                    $func_returns = call_user_func( array( 'self', "css_fn__$fn_name_clean" ), $args );
                }
            }
            else {
                if ( isset( $process_callback[ $fn_name ] ) ) {
                    $func_returns = call_user_func( $process_callback[ $fn_name ], $args, $fn_name, $property );
                }
            }

            // Join together the result.
            $str = substr( $str, 0, $offset ) . $minus_before . $func_returns . substr( $str, $closing_paren );
        }
    }


    #############################
    #  Helpers.

    static public function parseArgs ( $input, $allowSpaceDelim = false ) {
        return csscrush_util::splitDelimList(
            $input, ( $allowSpaceDelim ? '\s*[,\s]\s*' : ',' ) );
    }

    // Intended as a quick arg-list parse for function that take up-to 2 arguments
    // with the proviso the first argument is a name
    static public function parseArgsSimple ( $input ) {
        return preg_split( csscrush_regex::$patt->argListSplit, $input, 2 );
    }

    static protected function colorAdjust ( $color, array $adjustments ) {

        $fn_matched = preg_match( '!^(#|rgba?|hsla?)!', $color, $m );
        $keywords =& csscrush_color::loadKeywords();

        // Support for Hex, RGB, RGBa and keywords
        // HSL and HSLa are passed over
        if ( $fn_matched || isset( $keywords[ $color ] ) ) {

            $alpha = 1;
            $rgb = null;

            // Get an RGB array from the color argument
            if ( $fn_matched ) {
                switch ( $m[1] ) {
                    case '#':
                        $rgb = csscrush_color::hexToRgb( $color );
                        break;

                    case 'rgb':
                    case 'rgba':
                    case 'hsl':
                    case 'hsla':
                        $function = $m[1];
                        $alpha_channel = 4 === strlen( $function ) ? true : false;
                        $vals = substr( $color, strlen( $function ) + 1 );  // Trim function name and start paren
                        $vals = substr( $vals, 0, strlen( $vals ) - 1 );    // Trim end paren
                        $vals = array_map( 'trim', explode( ',', $vals ) ); // Explode to array of arguments
                        if ( $alpha_channel ) {
                            $alpha = array_pop( $vals );
                        }
                        if ( 0 === strpos( $function, 'rgb' ) ) {
                            $rgb = csscrush_color::normalizeCssRgb( $vals );
                        }
                        else {
                            $rgb = csscrush_color::cssHslToRgb( $vals );
                        }
                        break;
                }
            }
            else {
                $rgb = $keywords[ $color ];
            }

            $hsl = csscrush_color::rgbToHsl( $rgb );

            // Normalize adjustment parameters to floating point numbers
            // then calculate the new HSL value
            $index = 0;
            foreach ( $adjustments as $val ) {
                // Normalize argument
                $_val = $val ? trim( str_replace( '%', '', $val ) ) : 0;

                // Reduce value to float
                $_val /= 100;

                // Adjust alpha component if necessary
                if ( 3 === $index ) {
                    if ( 0 != $val ) {
                        $alpha = max( 0, min( 1, $alpha + $_val ) );
                    }
                }
                // Adjust HSL component value if necessary
                else {
                    if ( 0 != $val ) {
                        $hsl[ $index ] = max( 0, min( 1, $hsl[ $index ] + $_val ) );
                    }
                }
                $index++;
            }

            // Finally convert new HSL value to RGB
            $rgb = csscrush_color::hslToRgb( $hsl );

            // Return as hex if there is no modified alpha channel
            // Otherwise return RGBA string
            if ( 1 === $alpha ) {
                return csscrush_color::rgbToHex( $rgb );
            }
            $rgb[] = $alpha;
            return 'rgba(' . implode( ',', $rgb ) . ')';
        }
        else {
            return $color;
        }
    }


    #############################
    #  CSS functions.

    static protected function css_fn__math ( $input ) {

        // Strip blacklisted characters
        $input = preg_replace( csscrush_regex::$patt->mathBlacklist, '', $input );

        $result = @eval( "return $input;" );

        return $result === false ? 0 : round( $result, 5 );
    }

    static protected function css_fn__percent ( $input ) {

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

    // Percent function alias.
    static protected function css_fn__pc ( $input ) {
        return self::css_fn__percent( $input );
    }

    static protected function css_fn__hsla_adjust ( $input ) {
        list( $color, $h, $s, $l, $a ) = array_pad( self::parseArgs( $input, true ), 5, 0 );
        return self::colorAdjust( $color, array( $h, $s, $l, $a ) );
    }

    static protected function css_fn__hsl_adjust ( $input ) {
        list( $color, $h, $s, $l ) = array_pad( self::parseArgs( $input, true ), 4, 0 );
        return self::colorAdjust( $color, array( $h, $s, $l, 0 ) );
    }

    static protected function css_fn__h_adjust ( $input ) {
        list( $color, $h ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
        return self::colorAdjust( $color, array( $h, 0, 0, 0 ) );
    }

    static protected function css_fn__s_adjust ( $input ) {
        list( $color, $s ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
        return self::colorAdjust( $color, array( 0, $s, 0, 0 ) );
    }

    static protected function css_fn__l_adjust ( $input ) {
        list( $color, $l ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
        return self::colorAdjust( $color, array( 0, 0, $l, 0 ) );
    }

    static protected function css_fn__a_adjust ( $input ) {
        list( $color, $a ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
        return self::colorAdjust( $color, array( 0, 0, 0, $a ) );
    }
}

