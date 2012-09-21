<?php
/**
 *
 * CSS rule API
 *
 */

class csscrush_rule implements IteratorAggregate, Countable {

	public $vendorContext;
	public $isNested;
	public $label;

	public $tracingStub = null;

	public $properties = array();

	public $selectorList = array();

	// The comments associated with the rule
	public $comments = array();

	// Arugments passed in via 'extend' property
	public $extendArgs = array();

	public $_declarations = array();

	// A table for storing the declarations as data for this() referencing
	public $localData = array();

	// A table for storing the declarations as data for external query() referencing
	public $data = array();

	public function declarationCheckin ( $prop, $value, &$pairs ) {

		if ( $prop !== '' && $value !== '' ) {

			// First resolve query() calls that reference earlier rules
			if ( preg_match( csscrush_regex::$patt->queryFunction, $value ) ) {

				csscrush_function::executeCustomFunctions( $value,
					csscrush_regex::$patt->queryFunction, array(
						'query' => array( $this, 'cssQueryFunction' ),
					), $prop );
			}

			if ( strpos( $prop, 'data-' ) === 0 ) {

				// If it's with data prefix, we don't want to print it
				// Just remove the prefix
				$prop = substr( $prop, strlen( 'data-' ) );

				// On first pass we want to store data properties on $this->data,
				// as well as on local
				$this->data[ $prop ] = $value;
			}
			else {

				// Add to the stack
				$pairs[] = array( $prop, $value );
			}

			// Set on $this->localData
			$this->localData[ $prop ] = $value;

			// Unset on data tables if the value has a this() call:
			//   - Restriction to avoid circular references
			if ( preg_match( csscrush_regex::$patt->thisFunction, $value ) ) {

				unset( $this->localData[ $prop ] );
				unset( $this->data[ $prop ] );
			}
		}
	}

	public function __construct ( $selector_string = null, $declarations_string ) {

		$regex = csscrush_regex::$patt;
		$options = csscrush::$process->options;
		$this->label = csscrush::tokenLabelCreate( 'r' );

		// If tracing store the last tracing stub, then strip all.
		if ( $options->trace && $trace_tokens = csscrush_regex::matchAll( $regex->traceToken, $selector_string ) ) {
			$trace_token = array_pop( $trace_tokens );
			$this->tracingStub = $trace_token[0][0];
			$selector_string = preg_replace( $regex->traceToken, '', $selector_string );
		}

		// Parse the selectors chunk
		if ( ! empty( $selector_string ) ) {

			$selectors_match = csscrush_util::splitDelimList( $selector_string, ',' );

			// Remove and store comments that sit above the first selector
			// remove all comments between the other selectors
			if ( strpos( $selectors_match->list[0], '___c' ) !== false ) {
				preg_match_all( $regex->commentToken, $selectors_match->list[0], $m );
				$this->comments = $m[0];
			}

			// Strip any other comments then create selector instances
			foreach ( $selectors_match->list as $selector ) {

				$selector = trim( csscrush_util::stripCommentTokens( $selector ) );

				// If the selector matches an absract directive
				if ( preg_match( $regex->abstract, $selector, $m ) ) {

					$abstract_name = $m[1];

					// Link the rule to the abstract name and skip forward to declaration parsing
					csscrush::$process->abstracts[ $abstract_name ] = $this;
					break;
				}

				$this->addSelector( new csscrush_selector( $selector ) );

				// Store selector relationships
				//  - This happens twice; on first pass for mixins, second pass is for inheritance
				$this->indexSelectors();
			}
		}

		// Parse the declarations chunk
		// Need to split safely as there are semi-colons in data-uris
		$declarations_match = csscrush_util::splitDelimList( $declarations_string, ';', true, true );

		// First create a simple array of all properties and value pairs in raw state
		$pairs = array();

		// Split declarations in to property/value pairs
		foreach ( $declarations_match->list as $declaration ) {

			// Strip comments around the property
			$declaration = csscrush_util::stripCommentTokens( $declaration );

			// Accept several different syntaxes for mixin and extends.
			if ( preg_match( $regex->mixinExtend, $declaration, $m ) ) {

				$prop = isset( $m[2] ) ? 'extends' : 'mixin';
				$value = substr( $declaration, strlen( $m[0] ) );
			}
			elseif ( ( $colonPos = strpos( $declaration, ':' ) ) !== false ) {

				$prop = trim( substr( $declaration, 0, $colonPos ) );
				// Extract the value part of the declaration.
				$value = substr( $declaration, $colonPos + 1 );
			}
			else {
				// Must be malformed.
				continue;
			}

			// Some cleanup.
			$value = $value !== false ? trim( $value ) : $value;

			if ( $prop === 'mixin' ) {

				// Mixins are a special case
				if ( $mixin_declarations = csscrush_mixin::parseValue( $value ) ) {

					// Add mixin declarations to the stack
					while ( $mixin_declaration = array_shift( $mixin_declarations ) ) {

						$this->declarationCheckin(
							$mixin_declaration['property'], $mixin_declaration['value'], $pairs );
					}
				}
			}
			elseif ( $prop === 'extends' ) {

				// Extends are also a special case
				$this->setExtendSelectors( $value );
			}
			else {

				$this->declarationCheckin( $prop, $value, $pairs );
			}
		}

		// Bind declaration objects on the rule
		foreach ( $pairs as $index => &$pair ) {

			list( $prop, $value ) = $pair;

			// Resolve self references, aka this()
			csscrush_function::executeCustomFunctions( $value,
					csscrush_regex::$patt->thisFunction, array(
						'this'  => array( $this, 'cssThisFunction' ),
					), $prop );

			if ( trim( $value ) !== '' ) {

				// Add declaration and update the data table
				$this->data[ $prop ] = $value;
				$this->addDeclaration( $prop, $value, $index );
			}
		}

		// localData no longer required
		$this->localData = null;
	}

	public function __set ( $name, $value ) {

		if ( $name === 'declarations' ) {
			$this->_declarations = $value;

			// Update the table of properties
			$this->updatePropertyTable();
		}
	}

	public function __get ( $name ) {

		if ( $name === 'declarations' ) {
			return $this->_declarations;
		}
	}

	public function cssThisFunction ( $input, $fn_name ) {

		$args = csscrush_function::parseArgsSimple( $input );

		if ( isset( $this->localData[ $args[0] ] ) ) {

			return $this->localData[ $args[0] ];
		}
		elseif ( isset( $args[1] ) ) {

			return $args[1];
		}
		else {

			return '';
		}
	}

	public function cssQueryFunction ( $input, $fn_name, $call_property ) {

		$result = '';
		$args = csscrush_function::parseArgs( $input );

		if ( count( $args ) < 1 ) {
			return $result;
		}

		$abstracts =& csscrush::$process->abstracts;
		$mixins =& csscrush::$process->mixins;
		$selectorRelationships =& csscrush::$process->selectorRelationships;

		// Resolve arguments
		$name = array_shift( $args );
		$property = $call_property;
		if ( isset( $args[0] ) ) {
			if ( $args[0] !== 'default' ) {
				$property = array_shift( $args );
			}
			else {
				array_shift( $args );
			}
		}
		$default = isset( $args[0] ) ? $args[0] : null;

		// Try to match a abstract rule first
		if ( preg_match( csscrush_regex::$patt->ident, $name ) ) {

			// Search order: abstracts, mixins, rules
			if ( isset( $abstracts[ $name ]->data[ $property ] ) ) {

				$result = $abstracts[ $name ]->data[ $property ];
			}
			elseif ( isset( $mixins[ $name ]->data[ $property ] ) ) {

				$result = $mixins[ $name ]->data[ $property ];
			}
			elseif ( isset( $selectorRelationships[ $name ]->data[ $property ] ) ) {

				$result = $selectorRelationships[ $name ]->data[ $property ];
			}
		}
		else {

			// Look for a rule match
			$name = csscrush_selector::makeReadableSelector( $name );
			if ( isset( $selectorRelationships[ $name ]->data[ $property ] ) ) {

				$result = $selectorRelationships[ $name ]->data[ $property ];
			}
		}

		if ( $result === '' && ! is_null( $default ) ) {
			$result = $default;
		}
		return $result;
	}

	public function updatePropertyTable () {

		// Create a new table of properties
		$new_properties_table = array();

		foreach ( $this as $declaration ) {

			$name = $declaration->property;

			if ( isset( $new_properties_table[ $name ] ) ) {
				$new_properties_table[ $name ]++;
			}
			else {
				$new_properties_table[ $name ] = 1;
			}
		}

		$this->properties = $new_properties_table;
	}

	public function addPropertyAliases () {

		$regex = csscrush_regex::$patt;
		$aliasedProperties =& csscrush::$config->aliases[ 'properties' ];

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
				! $declaration->skip &&
				isset( $aliasedProperties[ $prop ] )
			) {
				// There are aliases for the current property
				foreach ( $aliasedProperties[ $prop ] as $prop_alias ) {

					// If an aliased version already exists to not create one.
					if ( $this->propertyCount( $prop_alias ) ) {
						continue;
					}

					// Create the aliased declaration.
					$copy = clone $declaration;
					$copy->property = $prop_alias;

					// Set the aliased declaration vendor property.
					$copy->vendor = null;
					if ( preg_match( $regex->vendorPrefix, $prop_alias, $vendor ) ) {
						$copy->vendor = $vendor[1];
					}
					$new_set[] = $copy;
				}
			}
			// Un-aliased property or a property alias that has been manually set.
			$new_set[] = $declaration;
		}
		// Re-assign.
		$this->declarations = $new_set;
	}

	public function addFunctionAliases () {

		$function_aliases =& csscrush::$config->aliases[ 'functions' ];
		$aliased_functions = array_keys( $function_aliases );

		if ( empty( $aliased_functions ) ) {
			return;
		}

		$new_set = array();

		// Keep track of the function aliases we apply and to which property
		// they belong, so we can avoid un-unecessary duplications.
		$used_fn_aliases = array();

		// Shim in aliased functions
		foreach ( $this->declarations as $declaration ) {

			// No functions, skip
			if (
				$declaration->skip ||
				empty( $declaration->functions )
			) {
				$new_set[] = $declaration;
				continue;
			}

			// Get list of functions used in declaration that are alias-able, if none skip.
			$intersect = array_intersect( $declaration->functions, $aliased_functions );
			if ( empty( $intersect ) ) {
				$new_set[] = $declaration;
				continue;
			}

			// Loop the aliasable functions.
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
						$used_fn_aliases[ $declaration->canonicalProperty ][] = $fn_search;
					}
				}
				else {

					// Duplicate the rule for each alias
					foreach ( $function_aliases[ $fn_name ] as $fn_alias ) {

						if (
							isset( $used_fn_aliases[ $declaration->canonicalProperty ] ) &&
							in_array( $fn_alias, $used_fn_aliases[ $declaration->canonicalProperty ] )
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
					}
				}
			}
			$new_set[] = $declaration;
		}

		// Re-assign
		$this->declarations = $new_set;
	}

	public function addValueAliases () {

		$aliasedValues =& csscrush::$config->aliases[ 'values' ];

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

		foreach ( $this->selectorList as $readableValue => $selector ) {

			$pos = strpos( $selector->value, ':any___' );

			if ( $pos !== false ) {

				// Contains an :any statement so we expand
				$chain = array( '' );
				do {
					if ( $pos === 0 ) {
						preg_match( '!:any(___p\d+___)!', $selector->value, $m );

						// Parse the arguments
						$expression = trim( csscrush::$storage->tokens->parens[ $m[1] ], '()' );
						$parts = preg_split( $reg_comma, $expression, null, PREG_SPLIT_NO_EMPTY );

						$tmp = array();
						foreach ( $chain as $rowCopy ) {
							foreach ( $parts as $part ) {
								$tmp[] = $rowCopy . $part;
							}
						}
						$chain = $tmp;
						$selector->value = substr( $selector->value, strlen( $m[0] ) );
					}
					else {
						foreach ( $chain as &$row ) {
							$row .= substr( $selector->value, 0, $pos );
						}
						$selector->value = substr( $selector->value, $pos );
					}
				} while ( ( $pos = strpos( $selector->value, ':any___' ) ) !== false );

				// Finish off
				foreach ( $chain as &$row ) {

					// Not creating a named rule association with this expanded selector
					$new_set[] = new csscrush_selector( $row . $selector->value );
				}

				// Store the unexpanded selector to selectorRelationships
				csscrush::$process->selectorRelationships[ $readableValue ] = $this;
			}
			else {

				// Nothing to expand
				$new_set[ $readableValue ] = $selector;
			}

		} // foreach

		$this->selectorList = $new_set;
	}

	public function indexSelectors () {

		foreach ( $this->selectorList as $selector ) {
			csscrush::$process->selectorRelationships[ $selector->readableValue ] = $this;
		}
	}

	public function setExtendSelectors ( $raw_value ) {

		$abstracts =& csscrush::$process->abstracts;
		$selectorRelationships =& csscrush::$process->selectorRelationships;

		// Pass extra argument to trim the returned list
		$args = csscrush_util::splitDelimList( $raw_value, ',', true, true );

		// Reset if called earlier, last call wins by intention
		$this->extendArgs = array();

		foreach ( $args->list as $arg ) {

			$this->extendArgs[] = new csscrush_extendArg( $arg );
		}
	}

	public function applyExtendables () {

		if ( ! $this->extendArgs ) {
			return;
		}

		$abstracts =& csscrush::$process->abstracts;
		$selectorRelationships =& csscrush::$process->selectorRelationships;

		// Filter the extendArgs list to usable references
		foreach ( $this->extendArgs as $key => $extend_arg ) {

			$name = $extend_arg->name;

			if ( isset( $abstracts[ $name ] ) ) {

				$parent_rule = $abstracts[ $name ];
				$extend_arg->pointer = $parent_rule;

			}
			elseif ( isset( $selectorRelationships[ $name ] ) ) {

				$parent_rule = $selectorRelationships[ $name ];
				$extend_arg->pointer = $parent_rule;

			}
			else {

				// Unusable, so unset it
				unset( $this->extendArgs[ $key ] );
			}
		}

		// Create a stack of all parent rule args
		$parent_extend_args = array();
		foreach ( $this->extendArgs as $extend_arg ) {
			$parent_extend_args = array_merge( $parent_extend_args, $extend_arg->pointer->extendArgs );
		}

		// Merge this rule's extendArgs with parent extendArgs
		$this->extendArgs = array_merge( $this->extendArgs, $parent_extend_args );

		// Filter now?

		// Add this rule's selectors to all extendArgs
		foreach ( $this->extendArgs as $extend_arg ) {

			$ancestor = $extend_arg->pointer;

			$extend_selectors = $this->selectorList;

			// If there is a pseudo class extension create a new set accordingly
			if ( $extend_arg->pseudo ) {

				$extend_selectors = array();
				foreach ( $this->selectorList as $readable => $selector ) {
					$new_selector = clone $selector;
					$new_readable = $new_selector->appendPseudo( $extend_arg->pseudo );
					$extend_selectors[ $new_readable ] = $new_selector;
				}
			}

			$ancestor->addSelectors( $extend_selectors );
		}
	}

	public function addSelector ( $selector ) {

		$this->selectorList[ $selector->readableValue ] = $selector;
	}

	public function addSelectors ( $list ) {

		$this->selectorList = array_merge( $this->selectorList, $list );
	}


	############
	#  IteratorAggregate

	public function getIterator () {
		return new ArrayIterator( $this->declarations );
	}


	############
	#  Countable

	public function count() {

		return count( $this->_declarations );
	}

	############
	#  Rule API

	public function propertyCount ( $prop ) {

		if ( array_key_exists( $prop, $this->properties ) ) {
			return $this->properties[ $prop ];
		}
		return 0;
	}

	public function addProperty ( $prop ) {

		if ( isset( $this->properties[ $prop ] ) ) {
			$this->properties[ $prop ]++;
		}
		else {
			$this->properties[ $prop ] = 1;
		}
	}

	public function addDeclaration ( $prop, $value, $contextIndex = 0 ) {

		// Create declaration, add to the stack if it's valid
		$declaration = new csscrush_declaration( $prop, $value, $contextIndex );

		if ( $declaration->isValid ) {

			// Manually increment the property name since we're directly updating the _declarations list
			$this->addProperty( $prop );
			$this->_declarations[] = $declaration;
			return $declaration;
		}

		return false;
	}

	public static function get ( $token ) {

		if ( isset( csscrush::$storage->tokens->rules[ $token ] ) ) {
			return csscrush::$storage->tokens->rules[ $token ];
		}
		return null;
	}
}

/**
 *
 * Declaration objects
 *
 */

class csscrush_declaration {

	public $property;
	public $canonicalProperty;
	public $vendor;
	public $functions;
	public $value;
	public $index;
	public $skip;
	public $important;
	public $parenTokens;
	public $isValid = true;

	public function __construct ( $prop, $value, $contextIndex = 0 ) {

		$regex = csscrush_regex::$patt;

		// Normalize input. Lowercase the property name
		$prop = strtolower( trim( $prop ) );
		$value = trim( $value );

		// Check the input
		if ( $prop === '' || $value === '' || $value === null ) {
			$this->isValid = false;
			return;
		}

		// Test for escape tilde
		if ( $skip = strpos( $prop, '~' ) === 0 ) {
			$prop = substr( $prop, 1 );
		}

		// Store the canonical property name.
		// Store the vendor mark if one is present.
		if ( preg_match( $regex->vendorPrefix, $prop, $vendor ) ) {
			$canonical_property = $vendor[2];
			$vendor = $vendor[1];
		}
		else {
			$vendor = null;
			$canonical_property = $prop;
		}

		// Check for !important keywords
		if ( ( $important = strpos( $value, '!important' ) ) !== false ) {
			$value = substr( $value, 0, $important );
			$important = true;
		}

		// Ignore declarations with null css values
		if ( $value === false || $value === '' ) {
			$this->isValid = false;
			return;
		}

		// Apply custom functions
		if ( ! $skip ) {
			csscrush_function::executeCustomFunctions( $value );
		}

		// Tokenize all remaining paren pairs
		$match_obj = csscrush_util::matchAllBrackets( $value );
		$this->parenTokens = $match_obj->matches;
		$value = $match_obj->string;

		// Create an index of all regular functions in the value
		if ( preg_match_all( $regex->function, $value, $functions ) > 0 ) {
			$out = array();
			foreach ( $functions[2] as $index => $fn_name ) {
				$out[] = $fn_name;
			}
			$functions = array_unique( $out );
		}
		else {
			$functions = array();
		}

		$this->property          = $prop;
		$this->canonicalProperty = $canonical_property;
		$this->vendor            = $vendor;
		$this->functions         = $functions;
		$this->index             = $contextIndex;
		$this->value             = $value;
		$this->skip              = $skip;
		$this->important         = $important;
	}

	public function getFullValue () {

		return csscrush_util::tokenReplace( $this->value, $this->parenTokens );
	}

}



/**
 *
 * Selector objects
 *
 */
class csscrush_selector {

	public $value;
	public $readableValue;
	public $allowPrefix = true;

	public static function makeReadableSelector ( $selector_string ) {

		// Quick test for paren tokens
		if ( strpos( $selector_string, '___p' ) !== false ) {
			$selector_string = csscrush_util::tokenReplaceAll( $selector_string, 'parens' );
		}

		// Create space around combinators, then normalize whitespace
		$selector_string = preg_replace( '!([>+~])!', ' $1 ', $selector_string );
		$selector_string = csscrush_util::normalizeWhiteSpace( $selector_string );

		// Quick test for string tokens
		if ( strpos( $selector_string, '___s' ) !== false ) {
			$selector_string = csscrush_util::tokenReplaceAll( $selector_string, 'strings' );
		}

		// Quick test for double-colons for backwards compat
		if ( strpos( $selector_string, '::' ) !== false ) {
			$selector_string = preg_replace( '!::(after|before|first-(?:letter|line))!', ':$1', $selector_string );
		}

		return $selector_string;
	}

	public function __construct ( $raw_selector, $associated_rule = null ) {

		if ( strpos( $raw_selector, '^' ) === 0 ) {

			$raw_selector = ltrim( $raw_selector, "^ \n\r\t" );
			$this->allowPrefix = false;
		}

		$this->readableValue = self::makeReadableSelector( $raw_selector );
		$this->value = $raw_selector;
	}

	public function __toString () {

		return $this->readableValue;
	}

	public function appendPseudo ( $pseudo ) {

		// Check to avoid doubling-up
		if ( ! csscrush_util::strEndsWith( $this->readableValue, $pseudo ) ) {

			$this->readableValue .= $pseudo;
			$this->value .= $pseudo;
		}
		return $this->readableValue;
	}
}



/**
 *
 * Extend argument objects
 *
 */
class csscrush_extendArg {

	public $pointer;
	public $name;
	public $pseudo;

	public function __construct ( $name ) {

		$this->name = $name;

		if ( ! preg_match( csscrush_regex::$patt->ident, $this->name ) ) {

			// Not a regular name: Some kind of selector so normalize it for later comparison
			$this->name = csscrush_selector::makeReadableSelector( $this->name );

			// If applying the pseudo on output store
			if ( substr( $this->name, -1 ) === '!' ) {

				$this->name = rtrim( $this->name, ' !' );
				if ( preg_match( '!\:\:?[\w-]+$!', $this->name, $m ) ) {
					$this->pseudo = $m[0];
				}
			}
		}
	}
}


