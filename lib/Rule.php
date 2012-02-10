<?php
/**
 * 
 * CSS rule API
 * 
 */

class csscrush_rule implements IteratorAggregate {

	public $vendorContext = null;
	public $properties = array();
	public $selectors = null;
	public $parens = array();
	public $declarations = array();
	public $comments = array();

	public function __construct ( $selector_string = null, $declarations_string ) {

		$regex = csscrush::$regex;

		// Parse the selectors chunk
		if ( !empty( $selector_string ) ) {

			$selectors_match = csscrush_util::splitDelimList( $selector_string, ',' );
			$this->parens += $selectors_match->matches;

			// Remove and store comments that sit above the first selector
			// remove all comments between the other selectors
			preg_match_all( $regex->token->comment, $selectors_match->list[0], $m );
			$this->comments = $m[0];
			foreach ( $selectors_match->list as &$selector ) {
				$selector = preg_replace( $regex->token->comment, '', $selector );
				$selector = trim( $selector );
			}
			$this->selectors = $selectors_match->list;
		}

		// Apply any custom functions
		$declarations_string = csscrush_function::parseAndExecuteValue( $declarations_string );

		// Parse the declarations chunk
		// Need to split safely as there are semi-colons in data-uris
		$declarations_match = csscrush_util::splitDelimList( $declarations_string, ';' );
		$this->parens += $declarations_match->matches;

		// Parse declarations in to property/value pairs
		foreach ( $declarations_match->list as $declaration ) {
			// Strip comments around the property
			$declaration = preg_replace( $regex->token->comment, '', $declaration );

			// Store the property
			$colonPos = strpos( $declaration, ':' );
			if ( $colonPos === false ) {
				// If there is no colon it's malformed
				continue;
			}

			// The property name
			$prop = trim( substr( $declaration, 0, $colonPos ) );

			// Test for escape tilde
			if ( $skip = strpos( $prop, '~' ) === 0 ) {
				$prop = substr( $prop, 1 );
			}
			// Store the property name
			$this->addProperty( $prop );

			// Store the property family
			// Store the vendor id, if one is present
			if ( preg_match( $regex->vendorPrefix, $prop, $vendor ) ) {
				$family = $vendor[2];
				$vendor = $vendor[1];
			}
			else {
				$vendor = null;
				$family = $prop;
			}

			// Extract the value part of the declaration
			$value = substr( $declaration, $colonPos + 1 );
			$value = $value !== false ? trim( $value ) : $value;
			if ( $value === false or $value === '' ) {
				// We'll ignore declarations with empty values
				continue;
			}

			// Create an index of all functions in the current declaration
			if ( preg_match_all( $regex->function->match, $value, $functions ) > 0 ) {
				// csscrush::log( $functions );
				$out = array();
				foreach ( $functions[2] as $index => $fn_name ) {
					$out[] = $fn_name;
				}
				$functions = array_unique( $out );
			}
			else {
				$functions = array();
			}

			// Store the declaration
			$_declaration = (object) array(
				'property'  => $prop,
				'family'    => $family,
				'vendor'    => $vendor,
				'functions' => $functions,
				'value'     => $value,
				'skip'      => $skip,
			);
			$this->declarations[] = $_declaration;
		}
	}

	public function addPropertyAliases () {

		$regex = csscrush::$regex;
		$aliasedProperties =& csscrush::$aliases[ 'properties' ];

		// First test for the existence of any aliased properties
		$intersect = array_intersect( array_keys( $aliasedProperties ), array_keys( $this->properties ) );
		if ( empty( $intersect ) ) {
			return;
		}

		// Shim in aliased properties
		$new_set = array();
		foreach ( $this->declarations as $declaration ) {
			$prop = $declaration->property;
			if (
				!$declaration->skip and
				isset( $aliasedProperties[ $prop ] ) 
			) {
				// There are aliases for the current property
				foreach ( $aliasedProperties[ $prop ] as $prop_alias ) {
					if ( $this->propertyCount( $prop_alias ) ) {
						continue;
					}
					// If the aliased property hasn't been set manually, we create it
					$copy = clone $declaration;
					$copy->family = $copy->property;
					$copy->property = $prop_alias;
					// Remembering to set the vendor property
					$copy->vendor = null;
					// Increment the property count
					$this->addProperty( $prop_alias );
					if ( preg_match( $regex->vendorPrefix, $prop_alias, $vendor ) ) {
						$copy->vendor = $vendor[1];
					}
					$new_set[] = $copy;
				}
			}
			// Un-aliased property or a property alias that has been manually set
			$new_set[] = $declaration;
		}
		// Re-assign
		$this->declarations = $new_set;
	}

	public function addFunctionAliases () {

		$function_aliases =& csscrush::$aliases[ 'functions' ];
		$aliased_functions = array_keys( $function_aliases );

		if ( empty( $aliased_functions ) ) {
			return;
		}

		$new_set = array();

		// Keep track of the function aliases we apply and to which property 'family'
		// they belong, so we can avoid un-unecessary duplications
		$used_fn_aliases = array();

		// Shim in aliased functions
		foreach ( $this->declarations as $declaration ) {

			// No functions, skip
			if (
				$declaration->skip or
				empty( $declaration->functions ) 
			) {
				$new_set[] = $declaration;
				continue;
			}
			// Get list of functions used in declaration that are alias-able, if none skip
			$intersect = array_intersect( $declaration->functions, $aliased_functions );
			if ( empty( $intersect ) ) {
				$new_set[] = $declaration;
				continue;
			}
			// csscrush::log($intersect);
			// Loop the aliasable functions
			foreach ( $intersect as $fn_name ) {
				
				if ( $declaration->vendor ) {
					// If the property is vendor prefixed we use the vendor prefixed version
					// of the function if it exists.
					// Else we just skip and use the unprefixed version
					$fn_search = "-{$declaration->vendor}-$fn_name";
					if ( in_array( $fn_search, $function_aliases[ $fn_name ] ) ) {
						$declaration->value = preg_replace(
							'!(^| |,)' . $fn_name . '!',
							'${1}' . $fn_search,
							$declaration->value
						);
						$used_fn_aliases[ $declaration->family ][] = $fn_search;
					}
				}
				else {

					// Duplicate the rule for each alias
					foreach ( $function_aliases[ $fn_name ] as $fn_alias ) {

						if (
							isset( $used_fn_aliases[ $declaration->family ] ) and
							in_array( $fn_alias, $used_fn_aliases[ $declaration->family ] )
						) {
							// If the function alias has already been applied in a vendor property
							// for the same declaration property assume all is good
							continue;
						}
						$copy = clone $declaration;
						$copy->value = preg_replace(
							'!(^| |,)' . $fn_name . '!',
							'${1}' . $fn_alias,
							$copy->value
						);
						$new_set[] = $copy;
						// Increment the property count
						$this->addProperty( $copy->property );
					}
				}
			}
			$new_set[] = $declaration;
		}

		// Re-assign
		$this->declarations = $new_set;
	}

	public function addValueAliases () {

		$aliasedValues =& csscrush::$aliases[ 'values' ];

		// First test for the existence of any aliased properties
		$intersect = array_intersect( array_keys( $aliasedValues ), array_keys( $this->properties ) );

		if ( empty( $intersect ) ) {
			return;
		}

		$new_set = array();
		foreach ( $this->declarations as $declaration ) {
			if ( !$declaration->skip ) {
				foreach ( $aliasedValues as $value_prop => $value_aliases ) {
					if ( $this->propertyCount( $value_prop ) < 1 ) {
						continue;
					}
					foreach ( $value_aliases as $value => $aliases ) {
						if ( $declaration->value === $value ) {
							foreach ( $aliases as $alias ) {
								$copy = clone $declaration;
								$copy->value = $alias;
								$new_set[] = $copy;
							}
						}
					}
				}
			}
			$new_set[] = $declaration;
		}
		// Re-assign
		$this->declarations = $new_set;
	}

	public function expandSelectors () {

		$new_set = array();
		$reg_comma = '!\s*,\s*!';

		foreach ( $this->selectors as $selector ) {
			$pos = strpos( $selector, ':any___' );
			if ( $pos !== false ) {
				// Contains an :any statement so we expand
				$chain = array( '' );
				do {
					if ( $pos === 0 ) {
						preg_match( '!:any(___p\d+___)!', $selector, $m );

						// Parse the arguments
						$expression = trim( $this->parens[ $m[1] ], '()' );
						$parts = preg_split( $reg_comma, $expression, null, PREG_SPLIT_NO_EMPTY );

						$tmp = array();
						foreach ( $chain as $rowCopy ) {
							foreach ( $parts as $part ) {
								$tmp[] = $rowCopy . $part;
							}
						}
						$chain = $tmp;
						$selector = substr( $selector, strlen( $m[0] ) );
					}
					else {
						foreach ( $chain as &$row ) {
							$row .= substr( $selector, 0, $pos );
						}
						$selector = substr( $selector, $pos );
					}
				} while ( ( $pos = strpos( $selector, ':any___' ) ) !== false );

				// Finish off
				foreach ( $chain as &$row ) {
					$new_set[] = $row . $selector;
				}
			}
			else {
				// Nothing special
				$new_set[] = $selector;
			}
		}
		$this->selectors = $new_set;
	}


	############
	#  IteratorAggregate

	public function getIterator () {
		return new ArrayIterator( $this->declarations );
	}


	############
	#  Rule API

	public function propertyCount ( $prop ) {
		if ( array_key_exists( $prop, $this->properties ) ) {
			return $this->properties[ $prop ];
		}
		return 0;
	}

	// Add property to the rule index keeping track of the count
	public function addProperty ( $prop ) {
		if ( isset( $this->properties[ $prop ] ) ) {
			$this->properties[ $prop ]++;
		}
		else {
			$this->properties[ $prop ] = 1;
		}
	}

	public function createDeclaration ( $property, $value, $options = array() ) {
		// Test for escape tilde
		if ( $skip = strpos( $property, '~' ) === 0 ) {
			$property = substr( $property, 1 );
		}
		$_declaration = array(
			'property'  => $property,
			'family'    => null,
			'vendor'    => null,
			'value'     => $value,
			'skip'      => $skip,
		);
		$this->addProperty( $property );
		return (object) array_merge( $_declaration, $options );
	}

	// Get a declaration value without paren tokens
	public function getDeclarationValue ( $declaration ) {
		$paren_keys = array_keys( $this->parens );
		$paren_values = array_values( $this->parens );
		return str_replace( $paren_keys, $paren_values, $declaration->value );
	}

}