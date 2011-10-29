<?php
/**
 * 
 * Custom CSS functions
 * 
 */

class CssCrush_Function {

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


	############
	#  Helpers

	protected static function parseMathArgs ( $input ) {
		// Split on comma, trim, and remove empties
		$args = array_filter( array_map( 'trim', explode( ',', $input ) ) );

		// Pass anything non-numeric through math
		foreach ( $args as &$arg ) {
			if ( !preg_match( '!^-?[\.0-9]+$!', $arg ) ) {
				$arg = self::css_fn__math( $arg );
			}
		}
		return $args;
	}

	protected static function parseArgs ( $input, $argCount = null ) {
		$args = CssCrush::splitDelimList( $input, ',', true, true );
		return array_map( 'trim', $args->list );
	}

	protected static function colorAdjust ( $color, array $adjustments ) {

		$fn_matched = preg_match( '!^(#|rgba?)!', $color, $m );
		$keywords = CssCrush_Color::getKeywords();

		// Support for Hex, RGB, RGBa and keywords
		// HSL and HSLa are passed over
		if ( $fn_matched or array_key_exists( $color, $keywords ) ) {
			
			$alpha = null;
			$rgb = null;

			// Get an RGB value from the color argument
			if ( $fn_matched ) {
				switch ( $m[1] ) {
					case '#':
						$rgb = CssCrush_Color::hexToRgb( $color );
						break;

					case 'rgb':
					case 'rgba':
						$rgba = $m[1] == 'rgba' ? true : false;
						$rgb = substr( $color, $rgba ? 5 : 4 );
						$rgb = substr( $rgb, 0, strlen( $rgb ) - 1 );
						$rgb = array_map( 'trim', explode( ',', $rgb ) );
						$alpha = $rgba ? array_pop( $rgb ) : null;
						$rgb = CssCrush_Color::normalizeCssRgb( $rgb );
						break;
				}
			}
			else {
				$rgb = $keywords[ $color ];
			}
			
			$hsl = CssCrush_Color::rgbToHsl( $rgb );

			// Clean up adjustment parameters to floating point numbers
			// Calculate the new HSL value
			$counter = 0;
			foreach ( $adjustments as &$_val ) {
				$index = $counter++;
				$_val = $_val ? trim( str_replace( '%', '', $_val ) ) : 0;
				// Reduce value to float
				$_val /= 100;
				// Calculate new HSL value
				$hsl[ $index ] = max( 0, min( 1, $hsl[ $index ] + $_val ) );
			}

			// Finally convert new HSL value to RGB
			$rgb = CssCrush_Color::hslToRgb( $hsl );

			// Return as hex if there is no alpha channel
			// Otherwise return RGBa string
			if ( is_null( $alpha ) ) {
				return CssCrush_Color::rgbToHex( $rgb );
			}
			$rgb[] = $alpha;
			return 'rgba(' . implode( ',', $rgb ) . ')';
		}
		else {
			return $color;
		}
	}


	############
	
	public static function css_fn ( $match ) {

		$before_char = $match[1];
		$fn_name_css = $match[2];
		$fn_name_clean = str_replace( '-', '', $fn_name_css );
		$fn_name = str_replace( '-', '_', $fn_name_css );
	
		$paren_id = $match[3];

		if ( !isset( CssCrush::$storage->tmpParens[ $paren_id ] ) ) {
			return $before_char;
		}

		// Get input value and trim parens
		$input = CssCrush::$storage->tmpParens[ $paren_id ];
		$input = trim( substr( $input, 1, strlen( $input ) - 2 ) );

		// An empty function name defaults to math
		if ( empty( $fn_name_clean ) ) {
			$fn_name = 'math';
		}

		// Capture a negative sign e.g -( 20 * 2 )
		if ( $fn_name_css === '-' ) {
			$before_char .= '-';
		}
		return $before_char . call_user_func( array( 'self', "css_fn__$fn_name" ), $input );
	}

	public static function css_fn__math ( $input ) {
		// Whitelist allowed characters
		$input = preg_replace( '![^\.0-9\*\/\+\-\(\)]!', '', $input );
		$result = 0;
		try {
			// CssCrush::log( $input )
			$result = eval( "return $input;" );
		}
		catch ( Exception $e ) {};
		return round( $result, 10 );
	}

	public static function css_fn__percent ( $input ) {

		$args = self::parseMathArgs( $input );

		// Use precision argument if it exists, default to 7
		$precision = isset( $args[2] ) ? $args[2] : 7;

		$result = 0;
		if ( count( $args ) > 1 ) {
			// Arbitary high precision division
			$div = (string) bcdiv( $args[0], $args[1], 25 );
			// Set precision percentage value
			$result = (string) bcmul( $div, '100', $precision );
			// Trim unnecessary zeros and decimals
			$result = trim( $result, '0' );
			$result = rtrim( $result, '.' );
		}
		return $result . '%';
	}

	// Percent function alias
	public static function css_fn__pc ( $input ) {
		return self::css_fn_percent( $input );
	}

	public static function css_fn__data_uri ( $input ) {

		// Normalize, since argument might be a string token
		if ( strpos( $input, '___s' ) === 0 ) {
			$string_labels = array_keys( CssCrush::$storage->tokens->strings );
			$string_values = array_values( CssCrush::$storage->tokens->strings );
			$input = trim( str_replace( $string_labels, $string_values, $input ), '\'"`' );
		}

		// Default return value
		$result = "url($input)";

		// No attempt to process absolute urls
		if (
			strpos( $input, 'http://' ) === 0 or
			strpos( $input, 'https://' ) === 0
		) {
			return $result;
		}

		// Get system file path
		if ( strpos( $input, '/' ) === 0 ) {
			$file = CssCrush::$config->docRoot . $input;
		}
		else {
			$baseDir = CssCrush::$config->baseDir;
			$file = "$baseDir/$input";
		}
		// csscrush::log($file);

		// File not found
		if ( !file_exists( $file ) ) {
			return $result;
		}

		$file_ext = pathinfo( $file, PATHINFO_EXTENSION );

		// Only allow certain extensions
		$allowed_file_extensions = array(
			'woff' => 'font/woff;charset=utf-8',
			'ttf'  => 'font/truetype;charset=utf-8',
			'svg'  => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			'gif'  => 'image/gif',
			'jpeg' => 'image/jpg',
			'jpg'  => 'image/jpg',
			'png'  => 'image/png',
		);
		if ( !array_key_exists( $file_ext, $allowed_file_extensions ) ) {
			return $result;
		}

		$mime_type = $allowed_file_extensions[ $file_ext ];
		$base64 = base64_encode( file_get_contents( $file ) );
		$data_uri = "data:{$mime_type};base64,$base64";
		if ( strlen( $data_uri ) > 32000 ) {
			// Too big for IE
		}
		return "url($data_uri)";
	}

	public static function css_fn__hsl_adjust ( $input ) {
		list( $color, $h, $s, $l ) = self::parseArgs( $input );
		return self::colorAdjust( $color, array( $h, $s, $l ) );
	}

	public static function css_fn__h_adjust ( $input ) {
		list( $color, $h ) = self::parseArgs( $input );
		return self::colorAdjust( $color, array( $h, 0, 0 ) );
	}

	public static function css_fn__s_adjust ( $input ) {
		list( $color, $s ) = self::parseArgs( $input );
		return self::colorAdjust( $color, array( 0, $s, 0 ) );
	}

	public static function css_fn__l_adjust ( $input ) {
		list( $color, $l ) = self::parseArgs( $input );
		return self::colorAdjust( $color, array( 0, 0, $l ) );
	}

}
