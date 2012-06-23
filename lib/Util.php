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


	public static function normalizePath ( $path, $strip_ms_dos = false ) {

		$path = rtrim( preg_replace( '![\\/]+!', '/', $path ), '/' );

		if ( $strip_ms_dos ) {
			$path = preg_replace( '!^[a-z]\:!i', '', $path );
		}
		return $path;
	}


	public static function cleanUpUrl ( $url ) {

		// Reduce redundant path segments (issue #32):
		// e.g 'foo/../bar' => 'bar'
		$patt = '![^/.]+/\.\./!';

		while ( preg_match( $patt, $url ) ) {
			$url = preg_replace( $patt, '', $url );
		}

		if ( strpos( $url, '(' ) !== false || strpos( $url, ')' ) !== false ) {
			$url = "\"$url\"";
		}

		return $url;
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


	public static function stripComments ( $str ) {
		return preg_replace( csscrush_regex::$patt->commentToken, '', $str );
	}


	public static function normalizeWhiteSpace ( $str ) {

		$replacements = array(
			'!\s+!'                             => ' ',
			'!(\[)\s*|\s*(\])|(\()\s*|\s*(\))!' => '${1}${2}${3}${4}',  // Trim internal bracket WS
			'!\s*(;|,|\/|\!)\s*!'               => '$1',     // Trim WS around delimiters and special characters
		);
		return preg_replace(
			array_keys( $replacements ), array_values( $replacements ), $str );
	}


	public static function tokenReplace ( $string, $token_replace, $type = 'parens' ) {

		// The tokens to replace
		$token_replace = (array) $token_replace;

		// Reference the token table
		$token_table =& csscrush::$storage->tokens->{ $type };

		// Replace the tokens listed
		foreach ( $token_replace as $token ) {
			if ( isset( $token_table[ $token ] ) ) {
				$string = str_replace( $token, $token_table[ $token ], $string );
			}
		}

		return $string;
	}


	public static function tokenReplaceAll ( $str, $type = 'parens' ) {

		// Only $type 'parens' or 'strings' make any sense
		$token_patt = 'parenToken';

		if ( $type === 'strings' ) {
			$token_patt = 'stringToken';
		}

		// Reference the token table
		$token_table =& csscrush::$storage->tokens->{ $type };

		// Find tokens
		$matches = csscrush_regex::matchAll( csscrush_regex::$patt->{ $token_patt }, $str );

		foreach ( $matches as $m ) {

			$token = $m[0][0];

			if ( isset( $token_table[ $token ] ) ) {

				$str = str_replace( $token, $token_table[ $token ], $str );
			}
		}
		return $str;
	}


	public static function splitDelimList ( $str, $delim, $fold_in = false, $trim = false ) {

		$match_obj = self::matchAllBrackets( $str );

		// If the delimiter is one character do a simple split
		// Otherwise do a regex split
		if ( 1 === strlen( $delim ) ) {

			$match_obj->list = explode( $delim, $match_obj->string );
		}
		else {

			$match_obj->list = preg_split( '!' . $delim . '!', $match_obj->string );
		}

		if ( true === $trim ) {
			$match_obj->list = array_map( 'trim', $match_obj->list );
		}

		// Filter out empties
		$match_obj->list = array_filter( $match_obj->list );

		if ( $fold_in ) {

			foreach ( $match_obj->list as &$item ) {
				$item = csscrush_util::tokenReplace( $item, $match_obj->matches );
			}
		}
		return $match_obj;
	}


	public static function matchBrackets ( $str, $brackets = array( '(', ')' ), $search_pos = 0, $capture_text = false ) {

		list( $opener, $closer ) = $brackets;
		$openings = array();
		$closings = array();
		$brake = 50; // Set a limit in the case of errors

		$match = new stdclass();

		$start_index = strpos( $str, $opener, $search_pos );
		$close_index = strpos( $str, $closer, $search_pos );

		if ( $start_index === false ) {

			return false;
		}
		if ( substr_count( $str, $opener ) !== substr_count( $str, $closer ) ) {

		 	$sample = substr( $str, 0, 25 );
			trigger_error( __METHOD__ . ": Unmatched token near '$sample'.\n", E_USER_WARNING );
			return false;
		}

		while (
			( $start_index !== false || $close_index !== false ) && $brake--
		) {
			if ( $start_index !== false && $close_index !== false ) {
				$search_pos = min( $start_index, $close_index );
				if ( $start_index < $close_index ) {
					$openings[] = $start_index;
				}
				else {
					$closings[] = $close_index;
				}
			}
			elseif ( $start_index !== false ) {
				$search_pos = $start_index;
				$openings[] = $start_index;
			}
			else {
				$search_pos = $close_index;
				$closings[] = $close_index;
			}
			$search_pos += 1; // Advance

			if ( count( $closings ) === count( $openings ) ) {

				$match->openings = $openings;
				$match->start = $start = $openings[0];
				$match->closings = $closings;
				$match->end = $closings[ count( $closings ) - 1 ] + 1;

				if ( $capture_text ) {
					// Text capturing is optional to avoid using memory when not necessary
					$match->inside = substr( $str, $start + 1, $match->end - $start - 2 );
					$match->after = substr( $str, $match->end );
				}

				return $match;
			}
			$start_index = strpos( $str, $opener, $search_pos );
			$close_index = strpos( $str, $closer, $search_pos );
		}

		trigger_error( __METHOD__ . ": Reached brake limit of '$brake'. Exiting.\n", E_USER_WARNING );
		return false;
	}


	public static function matchAllBrackets ( $str, $pair = '()', $offset = 0 ) {

		$match_obj = new stdclass();
		$match_obj->string = $str;
		$match_obj->raw = $str;
		$match_obj->matches = array();

		list( $opener, $closer ) = str_split( $pair, 1 );

		// Return early if there's no match
		if ( false === ( $first_offset = strpos( $str, $opener, $offset ) ) ) {
			return $match_obj;
		}

		// Step through the string one character at a time storing offsets
		$paren_score = -1;
		$inside_paren = false;
		$match_start = 0;
		$offsets = array();

		for ( $index = $first_offset; $index < strlen( $str ); $index++ ) {
			$char = $str[ $index ];

			if ( $opener === $char ) {
				if ( ! $inside_paren ) {
					$paren_score = 1;
					$match_start = $index;
				}
				else {
					$paren_score++;
				}
				$inside_paren = true;
			}
			elseif ( $closer === $char ) {
				$paren_score--;
			}

			if ( 0 === $paren_score ) {
				$inside_paren = false;
				$paren_score = -1;
				$offsets[] = array( $match_start, $index + 1 );
			}
		}

		// Step backwards through the matches
		while ( $offset = array_pop( $offsets ) ) {

			list( $start, $finish ) = $offset;

			$before = substr( $str, 0, $start );
			$content = substr( $str, $start, $finish - $start );
			$after = substr( $str, $finish );

			$label = csscrush::tokenLabelCreate( 'p' );
			$str = $before . $label . $after;
			$match_obj->matches[] = $label;

			// Parens will be folded in later
			csscrush::$storage->tokens->parens[ $label ] = $content;
		}

		$match_obj->string = $str;

		return $match_obj;
	}
}


/**
 *
 *  String sugar
 *
 */
class csscrush_string {

	public $token;
	public $value;
	public $quoteMark;

	public function __construct ( $token ) {

		$this->token = trim( $token );
		$raw = csscrush::$storage->tokens->strings[ $this->token ];
		$this->value = trim( $raw, '\'"' );
		$this->quoteMark = $raw[0];
	}

	public function update ( $newValue ) {
		csscrush::$storage->tokens->strings[ $this->token ] = $newValue;
	}
}


/**
 *
 *  Version string sugar
 *
 */
class csscrush_version {

	public $major;
	public $minor;
	public $revision;
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
}

