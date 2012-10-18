<?php
/**
 *
 *  Utilities
 *
 */
class csscrush_util {


	// Create html attribute string from array
	public static function htmlAttributes ( array $attributes ) {

		$attr_string = '';
		foreach ( $attributes as $name => $value ) {
			$value = htmlspecialchars( $value, ENT_COMPAT, 'UTF-8', false );
			$attr_string .= " $name=\"$value\"";
		}
		return $attr_string;
	}


	public static function strEndsWith ( $haystack, $needle ) {

		return substr( $haystack, -strlen( $needle ) ) === $needle;
	}


	public static function normalizePath ( $path, $strip_drive_letter = false ) {

		if ( $strip_drive_letter ) {
			$path = preg_replace( '!^[a-z]\:!i', '', $path );
		}
		// Backslashes and repeat slashes to a single forward slash.
		$path = rtrim( preg_replace( '![\\\\/]+!', '/', $path ), '/' );

		// Removing redundant './'.
		$path = preg_replace( '!^\./|/\./!', '', $path );

		return $path;
	}


	public static function strReplaceHash ( $str, $map = array() ) {

		if ( ! $map ) {
			return $str;
		}
		$labels = array_keys( $map );
		$values = array_values( $map );
		return str_replace( $labels, $values, $str );
	}


	public static function find () {

		foreach ( func_get_args() as $file ) {
			$file_path = csscrush::$config->location . '/' . $file;
			if ( file_exists( $file_path ) ) {
				return $file_path;
			}
		}
		return false;
	}


	public static function stripCommentTokens ( $str ) {

		return preg_replace( csscrush_regex::$patt->cToken, '', $str );
	}


	public static function normalizeWhiteSpace ( $str ) {

		$replacements = array(
			// Convert all whitespace sequences to a single space.
			'!\s+!S' => ' ',
			// Trim bracket whitespace where it's safe to do it.
			'!([\[(]) | ([\])])| ?([{}]) ?!S' => '${1}${2}${3}',
			// Trim whitespace around delimiters and special characters.
			'! ?([;/,]) ?!S' => '$1',
		);
		return preg_replace(
			array_keys( $replacements ), array_values( $replacements ), $str );
	}


	public static function splitDelimList ( $str, $delim = ',', $trim = true ) {

		$do_preg_split = strlen( $delim ) > 1 ? true : false;

		if ( ! $do_preg_split && strpos( $str, $delim ) === false ) {
			if ( $trim ) {
				$str = trim( $str );
			}
			return array( $str );
		}

		if ( strpos( $str, '(' ) !== false ) {
			$match_count
				= preg_match_all( csscrush_regex::$patt->balancedParens, $str, $matches );
		}
		else {
			$match_count = 0;
		}

		if ( $match_count ) {
			$keys = array();
			foreach ( $matches[0] as $index => &$value ) {
				$keys[] = "?$index?";
			}
			$str = str_replace( $matches[0], $keys, $str );
		}

		if ( $do_preg_split ) {
			$list = preg_split( '!' . $delim . '!', $str );
		}
		else {
			$list = explode( $delim, $str );
		}

		if ( $match_count ) {
			foreach ( $list as &$value ) {
				$value = str_replace( $keys, $matches[0], $value );
			}
		}

		if ( $trim ) {
			$list = array_map( 'trim', $list );
		}

		return $list;
	}


	public static function matchBrackets ( $str, $brackets = '()', $offset = 0, $capture_text = false ) {

		list( $opener, $closer ) = str_split( $brackets, 1 );

		$match = (object) array();

		if ( strpos( $str, $opener, $offset ) === false ) {
			return false;
		}

		if ( substr_count( $str, $opener ) !== substr_count( $str, $closer ) ) {
			$sample = substr( $str, $offset, 25 );
			trigger_error( __METHOD__ . ": Unmatched token near '$sample'.\n", E_USER_WARNING );
			return false;
		}

		$patt = csscrush_regex::$patt->balancedParens;
		if ( $opener === '{' ) {
			$patt = csscrush_regex::$patt->balancedCurlies;
		}

		if ( preg_match( $patt, $str, $m, PREG_OFFSET_CAPTURE, $offset ) ) {

			$match->start = $m[0][1];
			$match->end = $match->start + strlen( $m[0][0] );

			if ( $capture_text ) {
				// Text capturing is optional to avoid using memory when not necessary.
				$match->inside = $m[1][0];
				$match->after = substr( $str, $match->end );
			}
			return $match;
		}

		trigger_error( __METHOD__ . ": Could not match '$opener'. Exiting.\n", E_USER_WARNING );
		return false;
	}


	public static function getLinkBetweenDirs ( $dir1, $dir2 ) {

		// Normalise the paths.
		$dir1 = trim( csscrush_util::normalizePath( $dir1, true ), '/' );
		$dir2 = trim( csscrush_util::normalizePath( $dir2, true ), '/' );

		// The link between.
		$link = '';

		if ( $dir1 != $dir2 ) {

			// Split the directory paths into arrays so we can compare segment by segment.
			$dir1_segs = explode( '/', $dir1 );
			$dir2_segs = explode( '/', $dir2 );

			// Shift the segments until they are on different branches.
			while ( isset( $dir1_segs[0] ) && isset( $dir2_segs[0] ) && ( $dir1_segs[0] === $dir2_segs[0] ) ) {
				array_shift( $dir1_segs );
				array_shift( $dir2_segs );
			}

			$link = str_repeat( '../', count( $dir1_segs ) ) . implode( '/', $dir2_segs );
		}

		// Add closing slash.
		return $link !== '' ? rtrim( $link, '/' ) . '/' : '';
	}
}


/**
 *
 *  URL tokens.
 *
 */
class csscrush_url {

	public $protocol;
	public $isRelative;
	public $isRooted;
	public $convertToData;
	public $value;
	public $label;

	public function __construct ( $raw_value, $convert_to_data = false ) {

		$regex = csscrush_regex::$patt;

		if ( preg_match( $regex->sToken, $raw_value ) ) {
			$this->value = trim( csscrush::tokenFetch( $raw_value ), '\'"' );
			csscrush::tokenRelease( $raw_value );
		}
		else {
			$this->value = $raw_value;
		}

		$this->evaluate();
		$this->label = csscrush::tokenLabelCreate( 'u' );
		csscrush::$process->tokens->u[ $this->label ] = $this;
	}

	public function __toString () {
		$quote = '';
		if ( preg_match( '![()*]!', $this->value ) || 'data' === $this->protocol ) {
			$quote = '"';
		}
		return "url($quote$this->value$quote)";
	}

	public static function get ( $token ) {
		return csscrush::$process->tokens->u[ $token ];
	}

	public function evaluate () {

		$leading_variable = strpos( $this->value, '$(' ) === 0;

		if ( preg_match( '!^([a-z]+)\:!i', $this->value, $m ) ) {
			$this->protocol = strtolower( $m[1] );
		}
		else {
			// Normalize './' led paths.
			$this->value = preg_replace( '!^\.\/+!i', '', $this->value );
			if ( $this->value[0] === '/' ) {
				$this->isRooted = true;
			}
			elseif ( ! $leading_variable ) {
				$this->isRelative = true;
			}
			// Normalize slashes.
			$this->value = rtrim( preg_replace( '![\\\\/]+!', '/', $this->value ), '/' );
		}
	}

	public function applyVariables () {
		csscrush::placeVariables( $this->value );
		$this->evaluate();
	}

	public function toData () {

		if ( $this->isRooted ) {
			$file = csscrush::$config->docRoot . $this->value;
		}
		else {
			$file = csscrush::$process->input->dir . "/$this->value";
		}

		// File not found.
		if ( ! file_exists( $file ) ) {
			return;
		}

		$file_ext = pathinfo( $file, PATHINFO_EXTENSION );

		// Only allow certain extensions
		static $allowed_file_extensions = array(
			'woff' => 'application/x-font-woff;charset=utf-8',
			'ttf'  => 'font/truetype;charset=utf-8',
			'svg'  => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			'gif'  => 'image/gif',
			'jpeg' => 'image/jpg',
			'jpg'  => 'image/jpg',
			'png'  => 'image/png',
		);

		if ( ! isset( $allowed_file_extensions[ $file_ext ] ) ) {
			return;
		}

		$mime_type = $allowed_file_extensions[ $file_ext ];
		$base64 = base64_encode( file_get_contents( $file ) );
		$this->value = "data:$mime_type;base64,$base64";
		$this->protocol = 'data';
	}

	public function prepend ( $path_fragment ) {
		$this->value = $path_fragment . $this->value;
	}

	public function resolveRootedPath () {

		$config = csscrush::$config;
		$process = csscrush::$process;

		if ( ! file_exists ( $config->docRoot . $this->value ) ) {
			return false;
		}

		// Move upwards '..' by the number of slashes in baseURL to get a relative path.
		$this->value = str_repeat( '../', substr_count( $process->input->dirUrl, '/' ) ) .
			substr( $this->value, 1 );
	}

	public function simplify () {

		// Reduce redundant path segments (issue #32):
		// e.g 'foo/../bar' => 'bar'
		$patt = '![^/.]+/\.\./!';

		while ( preg_match( $patt, $this->value ) ) {
			$this->value = preg_replace( $patt, '', $this->value );
		}
	}
}


/**
 *
 *  Version string sugar
 *
 */
class csscrush_version {

	public $major = 0;
	public $minor = 0;
	public $revision = 0;
	public $extra;

	public function __construct ( $version_string ) {

		if ( ( $hyphen_pos = strpos( $version_string, '-' ) ) !== false ) {
			$this->extra = substr( $version_string, $hyphen_pos + 1 );
			$version_string = substr( $version_string, 0, $hyphen_pos );
		}

		$parts = explode( '.', $version_string );

		if ( ( $major = array_shift( $parts ) ) !== null ) {
			$this->major = (int) $major;
		}
		if ( ( $minor = array_shift( $parts ) ) !== null ) {
			$this->minor = (int) $minor;
		}
		if ( ( $revision = array_shift( $parts ) ) !== null ) {
			$this->revision = (int) $revision;
		}
	}

	public function __toString () {

		$out = (string) $this->major;

		if ( ! is_null( $this->minor ) ) {
			$out .= ".$this->minor";
		}
		if ( ! is_null( $this->revision ) ) {
			$out .= ".$this->revision";
		}
		if ( ! is_null( $this->extra ) ) {
			$out .= "-$this->extra";
		}

		return $out;
	}

	public function compare ( $version_string ) {

		$LESS  = -1;
		$MORE  = 1;
		$EQUAL = 0;

		$test = new csscrush_version( $version_string );

		foreach ( array( 'major', 'minor', 'revision' ) as $level ) {

			if ( $this->{ $level } < $test->{ $level } ) {
				return $LESS;
			}
			elseif ( $this->{ $level } > $test->{ $level } ) {
				return $MORE;
			}
		}

		return $EQUAL;
	}
}

