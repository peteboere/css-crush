<?php
/**
 *
 *  Mixin objects
 *
 */

class csscrush_mixin {

	public $declarationsTemplate = array();

	public $arguments;

	public $data = array();

	public function __construct ( $block ) {

		// Strip comment markers
		$block = csscrush_util::stripCommentTokens( $block );

		// Prepare the arguments object
		$this->arguments = new csscrush_arglist( $block );

		// Re-assign with the parsed arguments string
		$block = $this->arguments->string;

		// Need to split safely as there are semi-colons in data-uris
		$declarations_match = csscrush_util::splitDelimList( $block, ';', true );

		foreach ( $declarations_match->list as $raw_declaration ) {

			$colon = strpos( $raw_declaration, ':' );
			if ( $colon === -1 ) {
				continue;
			}

			// Store template declarations as arrays as they are copied by value not reference
			$declaration = array();

			$declaration['property'] = trim( substr( $raw_declaration, 0, $colon ) );
			$declaration['value'] = trim( substr( $raw_declaration, $colon + 1 ) );

			if ( $declaration['property'] === 'mixin' ) {

				// Mixin can contain other mixins if they are available
				if ( $mixin_declarations = csscrush_mixin::parseValue( $declaration['value'] ) ) {

					// Add mixin result to the stack
					$this->declarationsTemplate = array_merge( $this->declarationsTemplate, $mixin_declarations );
				}
			}
			elseif ( ! empty( $declaration['value'] ) ) {
				$this->declarationsTemplate[] = $declaration;
			}
		}

		// Create data table for the mixin.
		// Values that use arg() are excluded
		foreach ( $this->declarationsTemplate as &$declaration ) {
			if ( ! preg_match( csscrush_regex::$patt->argToken, $declaration['value'] ) ) {
				$this->data[ $declaration['property'] ] = $declaration['value'];
			}
		}
		return '';
	}

	public function call ( array $args ) {

		// Copy the template
		$declarations = $this->declarationsTemplate;

		if ( count( $this->arguments ) ) {

			list( $find, $replace ) = $this->arguments->getSubstitutions( $args );

			// Place the arguments
			foreach ( $declarations as &$declaration ) {
				$declaration['value'] = str_replace( $find, $replace, $declaration['value'] );
			}
		}

		// Return mixin declarations
		return $declarations;
	}

	public static function parseSingleValue ( $message ) {

		$message = ltrim( $message );
		$mixin = null;
		$non_mixin = null;

		// e.g.
		//   - mymixin( 50px, rgba(0,0,0,0), left 100% )
		//   - abstract-rule
		//   - #selector

		// Test for leading name
		if ( preg_match( '!^[\w-]+!', $message, $name_match ) ) {

			$name = $name_match[0];

			if ( isset( csscrush::$process->mixins[ $name ] ) ) {

				// Mixin match
				$mixin = csscrush::$process->mixins[ $name ];
			}
			elseif ( isset( csscrush::$process->abstracts[ $name ] ) ) {

				// Abstract rule match
				$non_mixin = csscrush::$process->abstracts[ $name ];
			}
		}

		// If no mixin or abstract rule matched, look for matching selector
		if ( ! $mixin && ! $non_mixin ) {

			$selector_test = csscrush_selector::makeReadableSelector( $message );
			// csscrush::log( array_keys( csscrush::$process->selectorRelationships ) );

			if ( isset( csscrush::$process->selectorRelationships[ $selector_test ] ) ) {
				$non_mixin = csscrush::$process->selectorRelationships[ $selector_test ];
			}
		}

		// If no mixin matched, but matched alternative, use alternative
		if ( ! $mixin ) {

			if ( $non_mixin ) {

				// Return expected format
				$result = array();
				foreach ( $non_mixin as $declaration ) {
					$result[] = array(
						'property' => $declaration->property,
						'value'    => $declaration->value,
					);
				}
				return $result;
			}
			else {

				// Nothing matches
				return false;
			}
		}

		// We have a valid mixin.
		// Discard the name part and any wrapping parens and whitespace
		$message = substr( $message, strlen( $name ) );
		$message = preg_replace( '!^\s*\(?\s*|\s*\)?\s*$!', '', $message );

		// e.g. "value, rgba(0,0,0,0), left 100%"

		// Determine what raw arguments there are to pass to the mixin
		$args = array();
		if ( $message !== '' ) {
			$args = csscrush_util::splitDelimList( $message, ',', true, true );
			$args = $args->list;
		}

		return $mixin->call( $args );
	}

	public static function parseValue ( $message ) {

		// Call the mixin and return the list of declarations
		$values = csscrush_util::splitDelimList( $message, ',', true );

		$declarations = array();

		foreach ( $values->list as $item ) {

			if ( $result = self::parseSingleValue( $item ) ) {

				$declarations = array_merge( $declarations, $result );
			}
		}
		return $declarations;
	}
}


/**
 *
 *  Fragment objects
 *
 */

class csscrush_fragment {

	public $template = array();

	public $arguments;

	public function __construct ( $block ) {

		// Prepare the arguments object
		$this->arguments = new csscrush_arglist( $block );

		// Re-assign with the parsed arguments string
		$this->template = $this->arguments->string;
	}

	public function call ( array $args ) {

		// Copy the template
		$template = $this->template;

		if ( count( $this->arguments ) ) {

			list( $find, $replace ) = $this->arguments->getSubstitutions( $args );
			$template = str_replace( $find, $replace, $template );
		}

		// Return fragment css
		return $template;
	}
}




/**
 *
 *  Argument list management for mixins and fragments
 *
 */

class csscrush_arglist implements Countable {

	// Positional argument default values
	public $defaults = array();

	// The number of expected arguments
	public $argCount = 0;

	// The string passed in with arg calls replaced by tokens
	public $string;

	function __construct ( $str ) {

		// Parse all arg function calls in the passed string, callback creates default values
		csscrush_function::executeCustomFunctions( $str, 
				csscrush_regex::$patt->argFunction, array( 'arg' => array( $this, 'store' ) ) );
		$this->string = $str;
	}

	public function store ( $raw_argument ) {

		$args = csscrush_function::parseArgsSimple( $raw_argument );

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
		return "___arg{$position_match}___";
	}

	public function getArgValue ( $index, &$args ) {

		// First lookup a passed value
		if ( isset( $args[ $index ] ) && $args[ $index ] !== 'default' ) {
			return $args[ $index ];
		}

		// Get a default value
		$default = isset( $this->defaults[ $index ] ) ? $this->defaults[ $index ] : '';

		// Recurse for nested arg() calls
		if ( preg_match( csscrush_regex::$patt->argToken, $default, $m ) ) {

			$default = $this->getArgValue( (int) $m[1], $args );
		}
		return $default;
	}

	public function getSubstitutions ( $args ) {

		$argIndexes = range( 0, $this->argCount-1 );

		// Create table of substitutions
		$find = array();
		$replace = array();

		foreach ( $argIndexes as $index ) {

			$find[] = "___arg{$index}___";
			$replace[] = $this->getArgValue( $index, $args );
		}

		return array( $find, $replace );
	}

	public function count () {
		return $this->argCount;
	}
}


