<?php
/**
 * 
 * Colour parsing and conversion
 * 
 */

class csscrush_color {

	protected static $keywords = array();
	
	public static function getKeywords () {
		// Load the keywords if necessary
		if ( empty( self::$keywords ) ) {
			$path = csscrush::$config->location . '/misc/color-keywords.ini';
			if ( $keywords = parse_ini_file( $path ) ) {
				foreach ( $keywords as $word => $rgb ) {
					$rgb = array_map( 'intval', explode( ',', $rgb ) );
					self::$keywords[ $word ] = $rgb;
				}
			}
		}
		return self::$keywords;
	}
	
	/**
	 * http://mjijackson.com/2008/02/
	 * rgb-to-hsl-and-rgb-to-hsv-color-model-conversion-algorithms-in-javascript
	 * 
	 * Converts an RGB color value to HSL. Conversion formula
	 * adapted from http://en.wikipedia.org/wiki/HSL_color_space.
	 * Assumes r, g, and b are contained in the set [0, 255] and
	 * returns h, s, and l in the set [0, 1].
	 */
	public static function rgbToHsl ( array $rgb ) {

		list( $r, $g, $b ) = $rgb;
		$r /= 255;
		$g /= 255;
		$b /= 255;
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$h;
		$s;
		$l = ( $max + $min ) / 2;

		if ( $max == $min ) {
			$h = $s = 0;
		}
		else {
			$d = $max - $min;
			$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
			switch( $max ) {
				case $r:
					$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
					break;
				case $g:
					$h = ( $b - $r ) / $d + 2;
					break;
				case $b:
					$h = ( $r - $g ) / $d + 4;
					break;
			}
			$h /= 6;
		}

	    return array( $h, $s, $l );
	}

	/**
	 * http://mjijackson.com/2008/02/
	 * rgb-to-hsl-and-rgb-to-hsv-color-model-conversion-algorithms-in-javascript
	 *
	 * Converts an HSL color value to RGB. Conversion formula
	 * adapted from http://en.wikipedia.org/wiki/HSL_color_space.
	 * Assumes h, s, and l are contained in the set [0, 1] and
	 * returns r, g, and b in the set [0, 255].
	 */
	public static function hslToRgb ( array $hsl ) {
		list( $h, $s, $l ) = $hsl;
		$r;
		$g;
		$b;
		if ( $s == 0 ) {
			$r = $g = $b = $l;
		}
		else {
			$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
			$p = 2 * $l - $q;
			$r = self::hueToRgb( $p, $q, $h + 1 / 3 );
			$g = self::hueToRgb( $p, $q, $h );
			$b = self::hueToRgb( $p, $q, $h - 1 / 3 );
		}
		return array( round( $r * 255 ), round( $g * 255 ), round( $b * 255 ) );
	}

	// Convert percentages to points (0-255)
	public static function normalizeCssRgb ( array $rgb ) {
		foreach ( $rgb as &$val ) {
			if ( strpos( $val, '%' ) !== false ) {
				$val = str_replace( '%', '', $val );
				$val = round( $val * 2.55 );
			}
		}
		return $rgb;
	}

	public static function cssHslToRgb ( array $hsl ) {

		// Normalize the hue degree value then convert to float
		$h = array_shift( $hsl );
		$h = $h % 360;
		if ( $h < 0 ) {
			$h = 360 + $h;
		}
		$h = $h / 360;

		// Convert s and l to floats
		foreach ( $hsl as &$val ) {
			$val = str_replace( '%', '', $val );
			$val /= 100;
		}
		list( $s, $l ) = $hsl;
		
		$hsl = array( $h, $s, $l );
		$rgb = self::hslToRgb( $hsl );
		
		return $rgb;
	}

	public static function hueToRgb ( $p, $q, $t ) {
		if ( $t < 0 ) $t += 1;
		if ( $t > 1 ) $t -= 1;
		if ( $t < 1/6 ) return $p + ( $q - $p ) * 6 * $t;
		if ( $t < 1/2 ) return $q;
		if ( $t < 2/3 ) return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		return $p;
	}

	public static function rgbToHex ( array $rgb ) {
		$hex_out = '#'; 
		foreach ( $rgb as $val ) {
			$hex_out .= str_pad( dechex( $val ), 2, '0', STR_PAD_LEFT );
		}
		return $hex_out;
	}

	public static function hexToRgb ( $hex ) {
		$hex = substr( $hex, 1 );
		
		// Handle shortened format
		if ( strlen( $hex ) === 3 ) {
			$long_hex = array();
			foreach ( str_split( $hex ) as $val ) {
				$long_hex[] = $val . $val;
			}
			$hex = $long_hex;
		}
		else {
			$hex = str_split( $hex, 2 );
		}
		return array_map( 'hexdec', $hex );
	}

}

