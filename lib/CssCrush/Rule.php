<?php
/**
 *
 * CSS rule API.
 *
 */
class CssCrush_Rule implements IteratorAggregate
{
    public $vendorContext;
    public $label;
    public $tracingStub;

    public $selectors = array();
    public $declarations = array();

    // The comments associated with the rule.
    public $comments = array();

    // Index of properties used in the rule for fast lookup.
    public $properties = array();

    // Arugments passed via @extend.
    public $extendArgs = array();

    // A table for storing the declarations as data for this() referencing.
    public $localData = array();

    // A table for storing the declarations as data for external query() referencing.
    public $data = array();

    public function __construct ( $selector_string = null, $declarations_string )
    {
        $regex = CssCrush_Regex::$patt;
        $process = CssCrush::$process;
        $this->label = $process->createTokenLabel( 'r' );

        // If tracing store the last tracing stub, then strip all.
        if (
            $process->addTracingStubs &&
            preg_match_all( $regex->tToken, $selector_string, $trace_tokens )
        ) {
            $trace_token = array_pop( $trace_tokens );
            $this->tracingStub = $process->fetchToken( $trace_token[0] );
            foreach ( $trace_tokens as $trace_token ) {
                $process->releaseToken( $trace_token[0] );
            }

            $selector_string = preg_replace( $regex->tToken, '', $selector_string );
        }

        // Parse the selectors chunk
        if ( ! empty( $selector_string ) ) {

            $process->captureParens( $selector_string );
            $selectors = CssCrush_Util::splitDelimList( $selector_string );

            // Remove and store comments that sit above the first selector
            // remove all comments between the other selectors
            if ( strpos( $selectors[0], '?c' ) !== false ) {
                preg_match_all( $regex->cToken, $selectors[0], $m );
                $this->comments = $m[0];
            }

            // Strip any other comments then create selector instances
            foreach ( $selectors as $selector ) {

                $selector = trim( CssCrush_Util::stripCommentTokens( $selector ) );

                // If the selector matches an absract directive
                if ( preg_match( $regex->abstract, $selector, $m ) ) {

                    $abstract_name = $m[1];

                    // Link the rule to the abstract name and skip forward to declaration parsing
                    $process->abstracts[ $abstract_name ] = $this;
                    break;
                }

                $this->addSelector( new CssCrush_Selector( $selector ) );

                // Store selector relationships
                //  - This happens twice; on first pass for mixins, second pass is for inheritance
                $this->indexSelectors();
            }
        }

        // Parse the declarations chunk.
        $declarations = preg_split( '!\s*;\s*!', trim( $declarations_string ), null, PREG_SPLIT_NO_EMPTY );

        // First create a simple array of all properties and value pairs in raw state
        $pairs = array();

        // Split declarations in to property/value pairs
        foreach ( $declarations as $declaration ) {

            // Strip comments around the property
            $declaration = CssCrush_Util::stripCommentTokens( $declaration );

            // Accept several different syntaxes for mixin and extends.
            if ( preg_match( $regex->mixinExtend, $declaration, $m ) ) {

                $prop = isset( $m[2] ) ? 'extends' : 'mixin';
                $value = trim( substr( $declaration, strlen( $m[0] ) ) );
            }
            elseif ( ( $colonPos = strpos( $declaration, ':' ) ) !== false ) {

                $prop = trim( substr( $declaration, 0, $colonPos ) );
                // Extract the value part of the declaration.
                $value = trim( substr( $declaration, $colonPos + 1 ) );
            }
            else {
                // Must be malformed.
                continue;
            }

            // Reject empty values.
            if ( empty( $prop ) || ( empty( $value ) && $value != '0' ) ) {
                continue;
            }

            if ( $prop === 'mixin' ) {

                // Mixins are a special case
                if ( $mixin_declarations = CssCrush_Mixin::parseValue( $value ) ) {

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

                // Lowercase property values.
                $prop = strtolower( $prop );
                $this->declarationCheckin( $prop, $value, $pairs );
            }
        }

        // Bind declaration objects on the rule
        foreach ( $pairs as $index => &$pair ) {

            list( $prop, $value ) = $pair;

            // Resolve self references, aka this()
            CssCrush_Function::executeOnString( $value,
                    CssCrush_Regex::$patt->thisFunction, array(
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

    public function __toString ()
    {
        $process = CssCrush::$process;

        // If there are no selectors or declarations associated with the rule return empty string.
        if ( empty( $this->selectors ) || empty( $this->declarations ) ) {

            // De-reference this instance.
            unset( $process->tokens->r[ $this->label ] );

            return '';
        }

        // Concat and return.
        if ( $process->minifyOutput ) {
            $selectors = implode( ',', $this->selectors );
            $block = implode( ';', $this->declarations );
            return "$this->tracingStub$selectors{{$block}}";
        }
        else {

            if ( $formatter = $process->ruleFormatter ) {
                return $formatter( $this );
            }

            // Default block formatter.
            return csscrush__fmtr_block( $this );
        }
    }

    public function declarationCheckin ( $prop, $value, &$pairs )
    {
        // First resolve query() calls that reference earlier rules.
        if ( preg_match( CssCrush_Regex::$patt->queryFunction, $value ) ) {

            CssCrush_Function::executeOnString( $value,
                CssCrush_Regex::$patt->queryFunction, array(
                    'query' => array( $this, 'cssQueryFunction' ),
                ), $prop );
        }

        // Now all referencing is done convert *most* values to lowercase.
        // Internet Explorer JavaScript expressions need case preserved.
        if ( strpos( $prop, 'expression' ) !== false ) {
            $value = strtolower( $value );
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
        if ( preg_match( CssCrush_Regex::$patt->thisFunction, $value ) ) {

            unset( $this->localData[ $prop ], $this->data[ $prop ] );
        }
    }

    public function updatePropertyIndex ()
    {
        // Create a new table of properties.
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


    #############################
    #  Rule inheritance.

    public function setExtendSelectors ( $raw_value )
    {
        $abstracts =& CssCrush::$process->abstracts;
        $selectorRelationships =& CssCrush::$process->selectorRelationships;

        // Reset if called earlier, last call wins by intention.
        $this->extendArgs = array();

        foreach ( CssCrush_Util::splitDelimList( $raw_value ) as $arg ) {
            $this->extendArgs[] = new CssCrush_ExtendArg( $arg );
        }
    }

    public function applyExtendables ()
    {
        if ( ! $this->extendArgs ) {
            return;
        }

        $abstracts =& CssCrush::$process->abstracts;
        $selectorRelationships =& CssCrush::$process->selectorRelationships;

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

            $extend_selectors = $this->selectors;

            // If there is a pseudo class extension create a new set accordingly
            if ( $extend_arg->pseudo ) {

                $extend_selectors = array();
                foreach ( $this->selectors as $readable => $selector ) {
                    $new_selector = clone $selector;
                    $new_readable = $new_selector->appendPseudo( $extend_arg->pseudo );
                    $extend_selectors[ $new_readable ] = $new_selector;
                }
            }

            $ancestor->addSelectors( $extend_selectors );
        }
    }


    #############################
    #  Referencing.

    public function cssThisFunction ( $input, $fn_name )
    {
        $args = CssCrush_Function::parseArgsSimple( $input );

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

    public function cssQueryFunction ( $input, $fn_name, $call_property )
    {
        $result = '';
        $args = CssCrush_Function::parseArgs( $input );

        if ( count( $args ) < 1 ) {
            return $result;
        }

        $abstracts =& CssCrush::$process->abstracts;
        $mixins =& CssCrush::$process->mixins;
        $selectorRelationships =& CssCrush::$process->selectorRelationships;

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
        if ( preg_match( CssCrush_Regex::$patt->ident, $name ) ) {

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
            $name = CssCrush_Selector::makeReadableSelector( $name );
            if ( isset( $selectorRelationships[ $name ]->data[ $property ] ) ) {

                $result = $selectorRelationships[ $name ]->data[ $property ];
            }
        }

        if ( $result === '' && ! is_null( $default ) ) {
            $result = $default;
        }
        return $result;
    }


    #############################
    #  Selectors.

    public function expandSelectors ()
    {
        $new_set = array();
        $reg_comma = '!\s*,\s*!';

        foreach ( $this->selectors as $readableValue => $selector ) {

            $pos = strpos( $selector->value, ':any?' );

            if ( $pos !== false ) {

                // Contains an :any statement so we expand
                $chain = array( '' );
                do {
                    if ( $pos === 0 ) {
                        preg_match( '!:any(\?p\d+\?)!', $selector->value, $m );

                        // Parse the arguments
                        $expression = trim( CssCrush::$process->tokens->p[ $m[1] ], '()' );
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
                } while ( ( $pos = strpos( $selector->value, ':any?' ) ) !== false );

                // Finish off
                foreach ( $chain as &$row ) {

                    // Not creating a named rule association with this expanded selector
                    $new_set[] = new CssCrush_Selector( $row . $selector->value );
                }

                // Store the unexpanded selector to selectorRelationships
                CssCrush::$process->selectorRelationships[ $readableValue ] = $this;
            }
            else {

                // Nothing to expand
                $new_set[ $readableValue ] = $selector;
            }

        } // foreach

        $this->selectors = $new_set;
    }

    public function indexSelectors ()
    {
        foreach ( $this->selectors as $selector ) {
            CssCrush::$process->selectorRelationships[ $selector->readableValue ] = $this;
        }
    }

    public function addSelector ( $selector )
    {
        $this->selectors[ $selector->readableValue ] = $selector;
    }

    public function addSelectors ( $list )
    {
        $this->selectors = array_merge( $this->selectors, $list );
    }


    #############################
    #  Aliasing.

    public function addPropertyAliases ()
    {
        $aliased_properties =& CssCrush::$process->aliases[ 'properties' ];

        // Bail early if nothing doing.
        if ( ! array_intersect_key( $aliased_properties, $this->properties ) ) {
            return;
        }

        $stack = array();
        $rule_updated = false;

        foreach ( $this->declarations as $declaration ) {

            if ( $declaration->skip ) {
                $stack[] = $declaration;
                continue;
            }

            // Shim in aliased properties.
            if ( isset( $aliased_properties[ $declaration->property ] ) ) {

                foreach ( $aliased_properties[ $declaration->property ] as $prop_alias ) {

                    // If an aliased version already exists to not create one.
                    if ( $this->propertyCount( $prop_alias ) ) {
                        continue;
                    }

                    // Create the aliased declaration.
                    $copy = clone $declaration;
                    $copy->property = $prop_alias;

                    // Set the aliased declaration vendor property.
                    $copy->vendor = null;
                    if ( preg_match( CssCrush_Regex::$patt->vendorPrefix, $prop_alias, $vendor ) ) {
                        $copy->vendor = $vendor[1];
                    }

                    $stack[] = $copy;
                    $rule_updated = true;
                }
            }

            // Un-aliased property or a property alias that has been manually set.
            $stack[] = $declaration;
        }

        // Re-assign if any updates have been made.
        if ( $rule_updated ) {
            $this->setDeclarations( $stack );
        }
    }

    public function addFunctionAliases ()
    {
        $function_aliases =& CssCrush::$process->aliases[ 'functions' ];

        // The new modified set of declarations.
        $new_set = array();
        $rule_updated = false;

        // Shim in aliased functions.
        foreach ( $this->declarations as $declaration ) {

            // No functions, bail.
            if ( ! $declaration->functions || $declaration->skip ) {
                $new_set[] = $declaration;
                continue;
            }

            // Get list of functions used in declaration that are alias-able, bail if none.
            $intersect = array_intersect_key( $declaration->functions, $function_aliases );
            if ( ! $intersect ) {
                $new_set[] = $declaration;
                continue;
            }

            // Loop the aliasable functions.
            foreach ( array_keys( $intersect ) as $fn_name ) {

                // For each aliased function dupe the declaration.
                // This pretty much limits the aliasing to one function per declaration.
                $prefixed_copies = array();
                foreach ( $function_aliases[ $fn_name ] as $fn_alias ) {

                    // If the declaration is vendor specific only create aliases for the same vendor.
                    if ( $declaration->vendor ) {
                        preg_match( CssCrush_Regex::$patt->vendorPrefix, $fn_alias, $m );
                        if ( $m[1] !== $declaration->vendor ) {
                            continue;
                        }
                    }

                    $copy = clone $declaration;

                    // Swap in the aliased function name.
                    $copy->value = preg_replace(
                        '~(?<![\w-])' . $fn_name . '(?=\?)~',
                        $fn_alias,
                        $copy->value
                    );
                    $prefixed_copies[] = $copy;
                    $rule_updated = true;
                }

                // Aliased function may require some additional fiddling.
                if ( isset( CssCrush_PostAliasFix::$functions[ $fn_name ] ) ) {
                    call_user_func( CssCrush_PostAliasFix::$functions[ $fn_name ], $prefixed_copies, $fn_name );
                }

                $new_set = array_merge( $new_set, $prefixed_copies );
            }
            $new_set[] = $declaration;
        }

        // Re-assign if any updates have been made.
        if ( $rule_updated ) {
            $this->setDeclarations( $new_set );
        }
    }

    public function addDeclarationAliases ()
    {
        $declaration_aliases =& CssCrush::$process->aliases[ 'declarations' ];

        // First test for the existence of any aliased properties.
        if ( ! ( $intersect = array_intersect_key( $declaration_aliases, $this->properties ) ) ) {

            return;
        }

        // Table lookups are faster.
        $intersect = array_flip( array_keys( $intersect ) );

        $new_set = array();
        $rule_updated = false;

        foreach ( $this->declarations as $declaration ) {

            // Check the current declaration property is actually aliased.
            if ( isset( $intersect[ $declaration->property ] ) && ! $declaration->skip ) {

                // Iterate on the current declaration property for value matches.
                foreach ( $declaration_aliases[ $declaration->property ] as $value_match => $replacements ) {

                    // Create new alias declaration if the property and value match.
                    if ( $declaration->value === $value_match ) {

                        foreach ( $replacements as $pair ) {

                            // If the replacement property is null use the original declaration property.
                            $new_set[] = new CssCrush_Declaration(
                                ! empty( $pair[0] ) ? $pair[0] : $declaration->property,
                                $pair[1]
                                );
                            $rule_updated = true;
                        }
                    }
                }
            }
            $new_set[] = $declaration;
        }

        // Re-assign if any updates have been made.
        if ( $rule_updated ) {
            $this->setDeclarations( $new_set );
        }
    }


    #############################
    #  IteratorAggregate interface.

    public function getIterator ()
    {
        return new ArrayIterator( $this->declarations );
    }


    #############################
    #  Rule API.

    public function propertyCount ( $prop )
    {
        return isset( $this->properties[ $prop ] ) ? $this->properties[ $prop ] : 0;
    }

    public function indexProperty ( $prop )
    {
        if ( isset( $this->properties[ $prop ] ) ) {
            $this->properties[ $prop ]++;
        }
        else {
            $this->properties[ $prop ] = 1;
        }
    }

    public function addDeclaration ( $prop, $value, $contextIndex = 0 )
    {
        // Create declaration, add to the stack if it's valid
        $declaration = new CssCrush_Declaration( $prop, $value, $contextIndex );

        if ( $declaration->isValid ) {

            // Manually increment the property name since we're directly updating the _declarations list
            $this->indexProperty( $prop );
            $this->declarations[] = $declaration;
            return $declaration;
        }

        return false;
    }

    public function setDeclarations ( array $declaration_stack )
    {
        $this->declarations = $declaration_stack;

        // Update the property index.
        $this->updatePropertyIndex();
    }

    static public function get ( $token )
    {
        if ( isset( CssCrush::$process->tokens->r[ $token ] ) ) {
            return CssCrush::$process->tokens->r[ $token ];
        }
        return null;
    }
}
