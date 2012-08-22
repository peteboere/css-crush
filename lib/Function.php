<?php
/**
 *
 * Custom CSS functions
 *
 */

class csscrush_function {

	// Regex pattern for finding custom functions
	public static $functionPatt;

	// Cache for function names
	public static $functionList;

	public static function init () {

		// Set the custom function regex pattern
		self::$functionList = self::getFunctions();
		self::$functionPatt = csscrush_regex::createFunctionMatchPatt( self::$functionList, true );
	}

	public static function getFunctions () {

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

	public static function executeCustomFunctions ( &$str, $patt = null, $process_callback = null, $property = null ) {

		// No bracketed expressions, early return
		if ( false === strpos( $str, '(' ) ) {
			return;
		}

		// Set default pattern if not set
		if ( is_null( $patt ) ) {
			$patt = csscrush_function::$functionPatt;
		}

		// No custom functions, early return
		if ( ! preg_match( $patt, $str ) ) {
			return;
		}

		// Need a space inside the front function paren for the following match_all to be reliable
		$str = preg_replace( '!\(([^\s])!', '( $1', $str, -1, $spacing_count );

		// Find custom function matches
		$matches = csscrush_regex::matchAll( $patt, $str );

		// Step through the matches from last to first
		while ( $match = array_pop( $matches ) ) {

			$offset = $match[0][1];
			$before_char = $match[1][0];
			$before_char_len = strlen( $before_char );

			// No function name default to math expression
			// Store the raw function name match
			$raw_fn_name = isset( $match[2] ) ? $match[2][0] : '';
			$fn_name = $raw_fn_name ? $raw_fn_name : 'math';
			if ( '-' === $fn_name ) {
				$fn_name = 'math';
			}

			// Loop throught the string
			$first_paren_offset = strpos( $str, '(', $offset );
			$paren_score = 0;

			for ( $index = $first_paren_offset; $index < strlen( $str ); $index++ ) {

				$char = $str[ $index ];
				if ( '(' === $char ) {
					$paren_score++;
				}
				elseif ( ')' === $char ) {
					$paren_score--;
				}

				if ( 0 === $paren_score ) {

					// Get the function inards
					$content_start = $offset + strlen( $before_char ) + strlen( $raw_fn_name ) + 1;
					$content_finish = $index;
					$content = substr( $str, $content_start, $content_finish - $content_start );
					$content = trim( $content );

					// Calculate offsets
					$func_start = $offset + strlen( $before_char );
					$func_end = $index + 1;

					// Workaround the minus
					$minus_before = '-' === $raw_fn_name ? '-' : '';

					$result = '';
					
					if ( ! $process_callback ) {

						// If no callback reference it's a built-in
						if ( in_array( $fn_name, self::$functionList ) ) {
							$fn_name_clean = str_replace( '-', '_', $fn_name );
							$result = call_user_func( array( 'self', "css_fn__$fn_name_clean" ), $content );
						}
					}
					else {
						if ( isset( $process_callback[ $fn_name ] ) ) {
							$result = call_user_func( $process_callback[ $fn_name ], $content, $fn_name, $property );
						}
					}

					// Join together the result
					$str = substr( $str, 0, $func_start ) . $minus_before . $result . substr( $str, $func_end );
					break;
				}
			}
		} // while

		// Restore the whitespace
		if ( $spacing_count ) {
			$str = str_replace( '( ', '(', $str );
		}

		// return $str;
	} 


	############
	#  Helpers

	public static function parseArgs ( $input, $allowSpaceDelim = false ) {

		$args = csscrush_util::splitDelimList( 
			$input, 
			( $allowSpaceDelim ? '\s*[,\s]\s*' : ',' ), 
			true, 
			true );

		return $args->list;
	}

	// Intended as a quick arg-list parse for function that take up-to 2 arguments
	// with the proviso the first argument is a name
	public static function parseArgsSimple ( $input ) {

		return preg_split( csscrush_regex::$patt->argListSplit, $input, 2 );
	}

	protected static function colorAdjust ( $color, array $adjustments ) {

		$fn_matched = preg_match( '!^(#|rgba?|hsla?)!', $color, $m );
		$keywords = csscrush_color::getKeywords();

		// Support for Hex, RGB, RGBa and keywords
		// HSL and HSLa are passed over
		if ( $fn_matched || array_key_exists( $color, $keywords ) ) {

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


	############

	public static function css_fn__math ( $input ) {

		// Strip blacklisted characters
		$input = preg_replace( csscrush_regex::$patt->mathBlacklist, '', $input );

		$result = @eval( "return $input;" );
		
		return $result === false ? 0 : round( $result, 5 );
	}

	public static function css_fn__percent ( $input ) {

		// Strip non-numeric and non delimiter characters
		$input = preg_replace( '![^\d\.\s,]!S', '', $input );

		$args = preg_split( csscrush_regex::$patt->argListSplit, $input, -1, PREG_SPLIT_NO_EMPTY );

		// csscrush::log( $input );

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

	// Percent function alias
	public static function css_fn__pc ( $input ) {
		return self::css_fn__percent( $input );
	}

	public static function css_fn__data_uri ( $input ) {

		// Normalize, since argument might be a string token
		if ( strpos( $input, '___s' ) === 0 ) {
			$string_labels = array_keys( csscrush::$storage->tokens->strings );
			$string_values = array_values( csscrush::$storage->tokens->strings );
			$input = trim( str_replace( $string_labels, $string_values, $input ), '\'"`' );
		}

		// Default return value
		$result = "url($input)";

		// No attempt to process absolute urls
		if ( preg_match( csscrush_regex::$patt->absoluteUrl, $input ) ) {
			return $result;
		}

		// Get system file path
		if ( strpos( $input, '/' ) === 0 ) {
			$file = csscrush::$config->docRoot . $input;
		}
		else {
			$file = csscrush::$process->inputDir . "/$input";
		}

		// File not found
		if ( ! file_exists( $file ) ) {
			return $result;
		}

		$file_ext = pathinfo( $file, PATHINFO_EXTENSION );

		// Only allow certain extensions
		$allowed_file_extensions = array(
			'woff' => 'application/x-font-woff;charset=utf-8',
			'ttf'  => 'font/truetype;charset=utf-8',
			'svg'  => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			'gif'  => 'image/gif',
			'jpeg' => 'image/jpg',
			'jpg'  => 'image/jpg',
			'png'  => 'image/png',
		);
		if ( ! array_key_exists( $file_ext, $allowed_file_extensions ) ) {
			return $result;
		}

		$mime_type = $allowed_file_extensions[ $file_ext ];
		$base64 = base64_encode( file_get_contents( $file ) );
		$data_uri = "data:{$mime_type};base64,$base64";

		return "url(\"$data_uri\")";
	}

	public static function css_fn__hsla_adjust ( $input ) {
		list( $color, $h, $s, $l, $a ) = array_pad( self::parseArgs( $input, true ), 5, 0 );
		return self::colorAdjust( $color, array( $h, $s, $l, $a ) );
	}

	public static function css_fn__hsl_adjust ( $input ) {
		list( $color, $h, $s, $l ) = array_pad( self::parseArgs( $input, true ), 4, 0 );
		return self::colorAdjust( $color, array( $h, $s, $l, 0 ) );
	}

	public static function css_fn__h_adjust ( $input ) {
		list( $color, $h ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
		return self::colorAdjust( $color, array( $h, 0, 0, 0 ) );
	}

	public static function css_fn__s_adjust ( $input ) {
		list( $color, $s ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
		return self::colorAdjust( $color, array( 0, $s, 0, 0 ) );
	}

	public static function css_fn__l_adjust ( $input ) {
		list( $color, $l ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
		return self::colorAdjust( $color, array( 0, 0, $l, 0 ) );
	}
	
	public static function css_fn__a_adjust ( $input ) {
		list( $color, $a ) = array_pad( self::parseArgs( $input, true ), 2, 0 );
		return self::colorAdjust( $color, array( 0, 0, 0, $a ) );
	}

}



