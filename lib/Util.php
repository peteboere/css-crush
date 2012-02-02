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


	public static function normalizeSystemPath ( $path, $stripMsDos = false ) {
		$path = rtrim( str_replace( '\\', '/', $path ), '/' );
		
		if ( $stripMsDos ) {
			$path = preg_replace( '!^[a-z]\:!i', '', $path ); 
		}
		return $path;
	}


	public static function find () {

		foreach ( func_get_args() as $file ) {
			$file_path = csscrush::$location . '/' . $file;
			if ( file_exists( $file_path ) ) {
				return $file_path;
			}
		}
		return false;
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


	public static function splitDelimList ( $str, $delim, $fold_in = false, $allow_empty = false ) {

		$match_obj = self::matchAllBrackets( $str );
		
		// If the delimiter is one character do a simple split
		// Otherwise do a regex split 
		if ( 1 === strlen( $delim ) ) {
			$match_obj->list = explode( $delim, $match_obj->string );
		}
		else {
			$match_obj->list = preg_split( '!' . $delim . '!', $match_obj->string );
		}
		
		if ( false === $allow_empty ) {
			$match_obj->list = array_filter( $match_obj->list );
		}
		if ( $fold_in ) {
			$match_keys = array_keys( $match_obj->matches );
			$match_values = array_values( $match_obj->matches );
			foreach ( $match_obj->list as &$item ) {
				$item = str_replace( $match_keys, $match_values, $item );
			}
		}
		return $match_obj;
	}


	public static function matchBrackets ( $str, $brackets = array( '(', ')' ), $search_pos = 0 ) {

		list( $opener, $closer ) = $brackets;
		$openings = array();
		$closings = array();
		$brake = 50; // Set a limit in the case of errors

		$match = new stdClass;

		$start_index = strpos( $str, $opener, $search_pos );
		$close_index = strpos( $str, $closer, $search_pos );

		if ( $start_index === false ) {
			return false;
		}
		if ( substr_count( $str, $opener ) !== substr_count( $str, $closer ) ) {
		 	$sample = substr( $str, 0, 15 );
			trigger_error( __METHOD__ . ": Unmatched token near '$sample'.\n", E_USER_WARNING );
			return false;
		}

		while (
			( $start_index !== false or $close_index !== false ) and $brake--
		) {
			if ( $start_index !== false and $close_index !== false ) {
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
				$match->closings = $closings;
				$match->start = $openings[0];
				$match->end = $closings[ count( $closings ) - 1 ] + 1;
				return $match;
			}
			$start_index = strpos( $str, $opener, $search_pos );
			$close_index = strpos( $str, $closer, $search_pos );
		}

		trigger_error( __METHOD__ . ": Reached brake limit of '$brake'. Exiting.\n", E_USER_WARNING );
		return false;
	}


	public static function matchAllBrackets ( $str, $pair = '()', $offset = 0 ) {

		$match_obj = new stdClass;
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
				if ( !$inside_paren ) {
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

			$label = csscrush::createTokenLabel( 'p' );
			$str = $before . $label . $after;
			$match_obj->matches[ $label ] = $content;

			// Parens will be folded in later
			csscrush::$storage->tokens->parens[ $label ] = $content;
		}

		$match_obj->string = $str;

		return $match_obj;
	}


}


class csscrush_string {

	public $token;

	public $value;

	public $raw;

	public $quoteMark;

	public function __construct ( $token ) {
		
		$this->token = trim( $token );
		$this->raw = csscrush::$storage->tokens->strings[ $token ];
		$this->value = trim( $this->raw, '\'"' );
		$this->quoteMark = $this->raw[0];
	}
	
	public function update ( $newValue ) {
		csscrush::$storage->tokens->strings = $newValue;
	}
}


