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
    public $extendSelectors = array();
    public $declarations = array();

    // The comments associated with the rule.
    public $comments = array();

    // Index of properties used in the rule for fast lookup.
    public $properties = array();
    public $canonicalProperties = array();

    // Arugments passed via @extend.
    public $extendArgs = array();

    // Declarations hash table for inter-rule this() referencing.
    public $selfData = array();

    // Declarations hash table for external query() referencing.
    public $queryData = array();

    public function __construct ($selector_string = null, $declarations_string)
    {
        $regex = CssCrush_Regex::$patt;
        $process = CssCrush::$process;
        $this->label = $process->createTokenLabel('r');

        // If tracing store the last tracing stub, then strip all.
        if (
            $process->addTracingStubs &&
            preg_match_all($regex->t_token, $selector_string, $trace_tokens)
        ) {
            $trace_token = array_pop($trace_tokens);
            $this->tracingStub = $process->fetchToken($trace_token[0]);
            foreach ($trace_tokens as $trace_token) {
                $process->releaseToken($trace_token[0]);
            }

            $selector_string = preg_replace($regex->t_token, '', $selector_string);
        }

        // Parse the selectors chunk
        if (! empty($selector_string)) {

            $selectors = CssCrush_Util::splitDelimList($selector_string);

            // Remove and store comments that sit above the first selector
            // remove all comments between the other selectors
            if (strpos($selectors[0], '?c') !== false) {
                preg_match_all($regex->c_token, $selectors[0], $m);
                $this->comments = $m[0];
            }

            // Strip any other comments then create selector instances
            foreach ($selectors as $selector) {

                $selector = trim(CssCrush_Util::stripCommentTokens($selector));

                // If the selector matches an absract directive
                if (preg_match($regex->abstract, $selector, $m)) {

                    $abstract_name = $m[1];

                    // Link the rule to the abstract name and skip forward to declaration parsing.
                    $process->references[$abstract_name] = $this;
                }
                else {
                    $this->addSelector(new CssCrush_Selector($selector));
                }
            }
        }

        // Parse the declarations chunk.
        $declarations_string = trim(CssCrush_Util::stripCommentTokens($declarations_string));
        $declarations = preg_split('~\s*;\s*~', $declarations_string, null, PREG_SPLIT_NO_EMPTY);

        // First create a simple array of all properties and value pairs in raw state
        $pairs = array();

        // Split declarations in to property/value pairs
        foreach ($declarations as $declaration) {

            // Rule directives. Accept several different syntaxes for mixin and extends.
            if (preg_match($regex->ruleDirective, $declaration, $m)) {

                if (! empty($m[1])) {
                    $prop = 'mixin';
                }
                elseif (! empty($m[2])) {
                    $prop = 'extends';
                }
                else {
                    $prop = 'name';
                }
                $value = trim(substr($declaration, strlen($m[0])));
            }
            elseif (($colonPos = strpos($declaration, ':')) !== false) {

                $prop = trim(substr($declaration, 0, $colonPos));

                // Extract the value part of the declaration.
                $value = trim(substr($declaration, $colonPos + 1));
            }
            else {
                // Must be malformed.
                continue;
            }

            // Reject empty values.
            if (empty($prop) || ! strlen($value)) {
                continue;
            }

            if ($prop === 'mixin') {

                // Mixins are a special case.
                if ($mixin_declarations = CssCrush_Mixin::parseValue($value)) {

                    // Add mixin declarations to the stack.
                    while ($mixin_declaration = array_shift($mixin_declarations)) {

                        $pairs[] = array($mixin_declaration['property'], $mixin_declaration['value']);
                    }
                }
            }
            elseif ($prop === 'extends') {

                // Extends are also a special case.
                $this->setExtendSelectors($value);
            }
            elseif ($prop === 'name') {

                // Link the rule as a reference.
                $process->references[$value] = $this;
            }
            else {

                $pairs[] = array($prop, $value);
            }
        }

        // Bind declaration objects on the rule.
        foreach ($pairs as $index => &$pair) {

            list($prop, $value) = $pair;

            if (trim($value) !== '') {

                // Only store to $this->selfData if the value does not itself make a
                // this() call to avoid circular references.
                if (! preg_match(CssCrush_Regex::$patt->thisFunction, $value)) {
                    $this->selfData[strtolower($prop)] = $value;
                }

                // Add declaration.
                $this->addDeclaration($prop, $value, $index);
            }
        }
    }

    public function __toString ()
    {
        $process = CssCrush::$process;

        // Merge the extend selectors.
        $this->selectors += $this->extendSelectors;

        // If there are no selectors or declarations associated with the rule
        // return empty string.
        if (empty($this->selectors) || empty($this->declarations)) {

            // De-reference this instance.
            unset($process->tokens->r[$this->label]);
            return '';
        }

        // Concat and return.
        if ($process->minifyOutput) {
            $selectors = implode(',', $this->selectors);
            $block = implode(';', $this->declarations);
            return "$this->tracingStub$selectors{{$block}}";
        }
        else {

            if ($formatter = $process->ruleFormatter) {
                return $formatter($this);
            }

            // Default block formatter.
            return csscrush__fmtr_block($this);
        }
    }

    public $declarationsProcessed = false;
    public function processDeclarations ()
    {
        if ($this->declarationsProcessed) {
            return;
        }

        foreach ($this->declarations as $index => $declaration) {

            // Execute functions, store as data etc.
            $declaration->process($this);

            // Drop declaration if value is now empty.
            if (! empty($declaration->inValid)) {
                unset($this->declarations[$index]);
            }
        }

        // selfData is done with, reclaim memory.
        unset($this->selfData);

        $this->declarationsProcessed = true;
    }

    public function expandDataSet ($dataset, $property)
    {
        // Expand shorthand properties to make them available
        // as data for this() and query().
        static $expandables = array(
            'margin-top' => 'margin',
            'margin-right' => 'margin',
            'margin-bottom' => 'margin',
            'margin-left' => 'margin',
            'padding-top' => 'padding',
            'padding-right' => 'padding',
            'padding-bottom' => 'padding',
            'padding-left' => 'padding',
            'border-top-width' => 'border-width',
            'border-right-width' => 'border-width',
            'border-bottom-width' => 'border-width',
            'border-left-width' => 'border-width',
            'border-top-left-radius' => 'border-radius',
            'border-top-right-radius' => 'border-radius',
            'border-bottom-right-radius' => 'border-radius',
            'border-bottom-left-radius' => 'border-radius',
            'border-top-color' => 'border-color',
            'border-right-color' => 'border-color',
            'border-bottom-color' => 'border-color',
            'border-left-color' => 'border-color',
        );

        $dataset =& $this->{$dataset};
        $property_group = isset($expandables[$property]) ? $expandables[$property] : null;

        // Bail if property non-expandable or already set.
        if (! $property_group || isset($dataset[$property]) || ! isset($dataset[$property_group])) {
            return;
        }

        // Get the expandable property value.
        $value = $dataset[$property_group];

        // Top-Right-Bottom-Left "trbl" expandable properties.
        $trbl_fmt = null;
        switch ($property_group) {
            case 'margin':
                $trbl_fmt = 'margin-%s';
                break;
            case 'padding':
                $trbl_fmt = 'padding-%s';
                break;
            case 'border-width':
                $trbl_fmt = 'border-%s-width';
                break;
            case 'border-radius':
                $trbl_fmt = 'border-%s-radius';
                break;
            case 'border-color':
                $trbl_fmt = 'border-%s-color';
                break;
        }
        if ($trbl_fmt) {
            $parts = explode(' ', $value);
            $placeholders = array();

            // 4 values.
            if (isset($parts[3])) {
                $placeholders = $parts;
            }
            // 3 values.
            elseif (isset($parts[2])) {
                $placeholders = array($parts[0], $parts[1], $parts[2], $parts[1]);
            }
            // 2 values.
            elseif (isset($parts[1])) {
                $placeholders = array($parts[0], $parts[1], $parts[0], $parts[1]);
            }
            // 1 value.
            else {
                $placeholders = array_pad($placeholders, 4, $parts[0]);
            }

            // Set positional variants.
            if ($property_group === 'border-radius') {
                $positions = array(
                    'top-left',
                    'top-right',
                    'bottom-right',
                    'bottom-left',
                );
            }
            else {
                $positions = array(
                    'top',
                    'right',
                    'bottom',
                    'left',
               );
            }

            foreach ($positions as $index => $position) {
                $prop = sprintf($trbl_fmt, $position);
                $dataset += array($prop => $placeholders[$index]);
            }
        }
    }


    #############################
    #  Rule inheritance.

    public function setExtendSelectors ($raw_value)
    {
        // Reset if called earlier, last call wins by intention.
        $this->extendArgs = array();

        foreach (CssCrush_Util::splitDelimList($raw_value) as $arg) {
            $this->extendArgs[] = new CssCrush_ExtendArg($arg);
        }
    }

    public $resolvedExtendables = false;
    public function resolveExtendables ()
    {
        if (! $this->extendArgs) {

            return false;
        }
        elseif (! $this->resolvedExtendables) {

            $references =& CssCrush::$process->references;

            // Filter the extendArgs list to usable references.
            $filtered = array();
            foreach ($this->extendArgs as $key => $extend_arg) {

                $name = $extend_arg->name;

                if (isset($references[$name])) {

                    $parent_rule = $references[$name];
                    $parent_rule->resolveExtendables();
                    $extend_arg->pointer = $parent_rule;
                    $filtered[$parent_rule->label] = $extend_arg;
                }
            }

            $this->resolvedExtendables = true;
            $this->extendArgs = $filtered;
        }

        return true;
    }

    public function applyExtendables ()
    {
        if (! $this->resolveExtendables()) {

            return;
        }

        // Create a stack of all parent rule args.
        $parent_extend_args = array();
        foreach ($this->extendArgs as $extend_arg) {
            $parent_extend_args += $extend_arg->pointer->extendArgs;
        }

        // Merge this rule's extendArgs with parent extendArgs.
        $this->extendArgs += $parent_extend_args;

        // Add this rule's selectors to all extendArgs.
        foreach ($this->extendArgs as $extend_arg) {

            $ancestor = $extend_arg->pointer;

            $extend_selectors = $this->selectors;

            // If there is a pseudo class extension create a new set accordingly.
            if ($extend_arg->pseudo) {

                $extend_selectors = array();
                foreach ($this->selectors as $readable => $selector) {
                    $new_selector = clone $selector;
                    $new_readable = $new_selector->appendPseudo($extend_arg->pseudo);
                    $extend_selectors[$new_readable] = $new_selector;
                }
            }
            $ancestor->extendSelectors += $extend_selectors;
        }
    }


    #############################
    #  Selectors.

    public function expandSelectors ()
    {
        $new_set = array();

        static $any_patt, $reg_comma;
        if (! $any_patt) {
            $any_patt = CssCrush_Regex::create(':any(<p-token>)', 'i');
            $reg_comma = '~\s*,\s*~';
        }

        foreach ($this->selectors as $readableValue => $selector) {

            $pos = stripos($selector->value, ':any?');

            if ($pos !== false) {

                // Contains an :any statement so expand.
                $chain = array('');
                do {
                    if ($pos === 0) {
                        preg_match($any_patt, $selector->value, $m);

                        // Parse the arguments
                        $expression = CssCrush::$process->tokens->p[$m[1]];

                        // Remove outer parens.
                        $expression = substr($expression, 1, strlen($expression) - 2);

                        // Test for nested :any() expressions.
                        $has_nesting = stripos($expression, ':any(') !== false;

                        $parts = preg_split($reg_comma, $expression, null, PREG_SPLIT_NO_EMPTY);

                        $tmp = array();
                        foreach ($chain as $rowCopy) {
                            foreach ($parts as $part) {

                                // Flatten nested :any() expressions in a hacky kind of way.
                                if ($has_nesting) {
                                    $part = str_ireplace(':any(', '', $part);

                                    // If $part has unbalanced parens trim closing parens to match.
                                    $diff = substr_count($part, ')') - substr_count($part, '(');
                                    if ($diff > 0) {
                                        $part = preg_replace('~\){1,'. $diff .'}$~', '', $part);
                                    }
                                }
                                $tmp[] = $rowCopy . $part;
                            }
                        }
                        $chain = $tmp;
                        $selector->value = substr($selector->value, strlen($m[0]));
                    }
                    else {
                        foreach ($chain as &$row) {
                            $row .= substr($selector->value, 0, $pos);
                        }
                        $selector->value = substr($selector->value, $pos);
                    }
                } while (($pos = stripos($selector->value, ':any?')) !== false);

                // Finish off.
                foreach ($chain as &$row) {

                    $new = new CssCrush_Selector($row . $selector->value);
                    $new_set[$new->readableValue] = $new;
                }
            }
            else {

                // Nothing to expand.
                $new_set[$readableValue] = $selector;
            }

        } // foreach

        $this->selectors = $new_set;
    }

    public function addSelector ($selector)
    {
        $this->selectors[$selector->readableValue] = $selector;

        // Link reference.
        CssCrush::$process->references[$selector->readableValue] = $this;
    }

    public function addSelectors ($list, $extend_selectors = false)
    {
        $this->selectors += $list;
    }


    #############################
    #  Aliasing.

    public function addPropertyAliases ()
    {
        $aliased_properties =& CssCrush::$process->aliases['properties'];

        // Bail early if nothing doing.
        if (! array_intersect_key($aliased_properties, $this->properties)) {
            return;
        }

        $stack = array();
        $rule_updated = false;
        $vendor_context = $this->vendorContext;
        $regex = CssCrush_Regex::$patt;

        foreach ($this->declarations as $declaration) {

            // Check declaration against vendor context.
            if ($vendor_context && $declaration->vendor && $declaration->vendor !== $vendor_context) {
                continue;
            }

            if ($declaration->skip) {
                $stack[] = $declaration;
                continue;
            }

            // Shim in aliased properties.
            if (isset($aliased_properties[$declaration->property])) {

                foreach ($aliased_properties[$declaration->property] as $prop_alias) {

                    // If an aliased version already exists do not create one.
                    if ($this->propertyCount($prop_alias)) {
                        continue;
                    }

                    // Get property alias vendor.
                    preg_match($regex->vendorPrefix, $prop_alias, $alias_vendor);

                    // Check against vendor context.
                    if ($vendor_context && $alias_vendor && $alias_vendor[1] !== $vendor_context) {
                        continue;
                    }

                    // Create the aliased declaration.
                    $copy = clone $declaration;
                    $copy->property = $prop_alias;

                    // Set the aliased declaration vendor property.
                    $copy->vendor = null;
                    if ($alias_vendor) {
                        $copy->vendor = $alias_vendor[1];
                    }

                    $stack[] = $copy;
                    $rule_updated = true;
                }
            }

            // Un-aliased property or a property alias that has been manually set.
            $stack[] = $declaration;
        }

        // Re-assign if any updates have been made.
        if ($rule_updated) {
            $this->setDeclarations($stack);
        }
    }

    public function addFunctionAliases ()
    {
        $function_aliases =& CssCrush::$process->aliases['functions'];
        $function_alias_groups =& CssCrush::$process->aliases['function_groups'];
        $vendor_context = $this->vendorContext;

        // The new modified set of declarations.
        $new_set = array();
        $rule_updated = false;

        // Shim in aliased functions.
        foreach ($this->declarations as $declaration) {

            // No functions, bail.
            if (! $declaration->functions || $declaration->skip) {
                $new_set[] = $declaration;
                continue;
            }

            // Get list of functions used in declaration that are alias-able, bail if none.
            $intersect = array_intersect_key($declaration->functions, $function_aliases);
            if (! $intersect) {
                $new_set[] = $declaration;
                continue;
            }

            // Keep record of which groups have been applied.
            $processed_groups = array();

            foreach (array_keys($intersect) as $fn_name) {

                // Store for all the duplicated declarations.
                $prefixed_copies = array();

                // Grouped function aliases.
                if ($function_aliases[$fn_name][0] === ':') {

                    $group_id = $function_aliases[$fn_name];

                    // If this group has been applied we can skip over.
                    if (isset($processed_groups[$group_id])) {
                        continue;
                    }

                    // Mark group as applied.
                    $processed_groups[$group_id] = true;

                    $groups =& $function_alias_groups[$group_id];

                    foreach ($groups as $group_key => $replacements) {

                        // If the declaration is vendor specific only create aliases for the same vendor.
                        if (
                            ($declaration->vendor && $group_key !== $declaration->vendor) ||
                            ($vendor_context && $group_key !== $vendor_context)
                        ) {
                            continue;
                        }

                        $copy = clone $declaration;

                        // Make swaps.
                        $copy->value = preg_replace(
                            $replacements['find'],
                            $replacements['replace'],
                            $copy->value
                        );
                        $prefixed_copies[] = $copy;
                        $rule_updated = true;
                    }

                    // Post fixes.
                    if (isset(CssCrush_PostAliasFix::$functions[$group_id])) {
                        call_user_func(CssCrush_PostAliasFix::$functions[$group_id], $prefixed_copies, $group_id);
                    }
                }

                // Single function aliases.
                else {

                    foreach ($function_aliases[$fn_name] as $fn_alias) {

                        // If the declaration is vendor specific only create aliases for the same vendor.
                        if ($declaration->vendor) {
                            preg_match(CssCrush_Regex::$patt->vendorPrefix, $fn_alias, $m);
                            if (
                                $m[1] !== $declaration->vendor ||
                                ($vendor_context && $m[1] !== $vendor_context)
                            ) {
                                continue;
                            }
                        }

                        $copy = clone $declaration;

                        // Make swaps.
                        $copy->value = preg_replace(
                            '~(?<![\w-])' . $fn_name . '(?=\?)~',
                            $fn_alias,
                            $copy->value
                        );
                        $prefixed_copies[] = $copy;
                        $rule_updated = true;
                    }

                    // Post fixes.
                    if (isset(CssCrush_PostAliasFix::$functions[$fn_name])) {
                        call_user_func(CssCrush_PostAliasFix::$functions[$fn_name], $prefixed_copies, $fn_name);
                    }
                }

                $new_set = array_merge($new_set, $prefixed_copies);
            }
            $new_set[] = $declaration;
        }

        // Re-assign if any updates have been made.
        if ($rule_updated) {
            $this->setDeclarations($new_set);
        }
    }

    public function addDeclarationAliases ()
    {
        $declaration_aliases =& CssCrush::$process->aliases['declarations'];

        // First test for the existence of any aliased properties.
        if (! ($intersect = array_intersect_key($declaration_aliases, $this->properties))) {

            return;
        }

        // Table lookups are faster.
        $intersect = array_flip(array_keys($intersect));

        $vendor_context = $this->vendorContext;
        $new_set = array();
        $rule_updated = false;

        foreach ($this->declarations as $declaration) {

            // Check the current declaration property is actually aliased.
            if (isset($intersect[$declaration->property]) && ! $declaration->skip) {

                // Iterate on the current declaration property for value matches.
                foreach ($declaration_aliases[$declaration->property] as $value_match => $replacements) {

                    // Create new alias declaration if the property and value match.
                    if ($declaration->value === $value_match) {

                        foreach ($replacements as $values) {

                            // Check the vendor against context.
                            if ($vendor_context && $vendor_context !== $values[2]) {
                                continue;
                            }

                            // If the replacement property is null use the original declaration property.
                            $new = new CssCrush_Declaration(
                                ! empty($values[0]) ? $values[0] : $declaration->property,
                                $values[1]
                                );
                            $new->important = $declaration->important;
                            $new_set[] = $new;
                            $rule_updated = true;
                        }
                    }
                }
            }
            $new_set[] = $declaration;
        }

        // Re-assign if any updates have been made.
        if ($rule_updated) {
            $this->setDeclarations($new_set);
        }
    }


    #############################
    #  IteratorAggregate interface.

    public function getIterator ()
    {
        return new ArrayIterator($this->declarations);
    }


    #############################
    #  Property indexing.

    public function indexProperty ($declaration)
    {
        $prop = $declaration->property;

        if (isset($this->properties[$prop])) {
            $this->properties[$prop]++;
        }
        else {
            $this->properties[$prop] = 1;
        }
        $this->canonicalProperties[$declaration->canonicalProperty] = true;
    }

    public function updatePropertyIndex ()
    {
        // Reset tables.
        $this->properties = array();
        $this->canonicalProperties = array();

        foreach ($this->declarations as $declaration) {
            $this->indexProperty($declaration);
        }
    }


    #############################
    #  Rule API.

    public function propertyCount ($prop)
    {
        return isset($this->properties[$prop]) ? $this->properties[$prop] : 0;
    }

    public function addDeclaration ($prop, $value, $contextIndex = 0)
    {
        // Create declaration, add to the stack if it's valid
        $declaration = new CssCrush_Declaration($prop, $value, $contextIndex);

        if (empty($declaration->inValid)) {

            $this->indexProperty($declaration);
            $this->declarations[] = $declaration;
            return $declaration;
        }

        return false;
    }

    public function setDeclarations (array $declaration_stack)
    {
        $this->declarations = $declaration_stack;

        // Update the property index.
        $this->updatePropertyIndex();
    }

    static public function get ($token)
    {
        if (isset(CssCrush::$process->tokens->r[$token])) {
            return CssCrush::$process->tokens->r[$token];
        }
        return null;
    }
}
