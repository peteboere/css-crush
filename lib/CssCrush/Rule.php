<?php
/**
 *
 * CSS rule API.
 *
 */
namespace CssCrush;

class Rule
{
    public $vendorContext;
    public $label;
    public $marker;
    public $name;
    public $isAbstract;
    public $resolvedExtendables;

    public $selectors;
    public $declarations;

    // Arugments passed via @extend.
    public $extendArgs = array();
    public $extendSelectors = array();

    public function __construct($selector_string, $declarations_string, $trace_token = null)
    {
        $process = Crush::$process;
        $this->label = $process->tokens->createLabel('r');
        $this->marker = $process->addTracingStubs || $process->generateMap ? $trace_token : null;
        $this->selectors = new SelectorList();
        $this->declarations = new DeclarationList();

        // Parse selectors.
        // Strip any other comments then create selector instances.
        $selector_string = trim(Util::stripCommentTokens($selector_string));

        foreach (Util::splitDelimList($selector_string) as $selector) {

            if (preg_match(Regex::$patt->abstract, $selector, $m)) {
                $this->name = strtolower($m['name']);
                $this->isAbstract = true;
            }
            else {
                $this->selectors->add(new Selector($selector));
            }
        }

        $pairs = DeclarationList::parse($declarations_string);

        foreach ($pairs as $index => $pair) {

            list($prop, $value) = $pair;

            if ($prop === 'extends') {

                // Extends are also a special case.
                $this->setExtendSelectors($value);
                unset($pairs[$index]);
            }
            elseif ($prop === 'name') {

                if (! $this->name) {
                    $this->name = $value;
                }
                unset($pairs[$index]);
            }
        }

        // Build declaration list.
        foreach ($pairs as $index => &$pair) {

            list($prop, $value) = $pair;

            if (trim($value) !== '') {

                if ($prop === 'mixin') {
                    $this->declarations->flattened = false;
                    $this->declarations->store[] = $pair;
                }
                else {
                    // Only store to $this->data if the value does not itself make a
                    // this() call to avoid circular references.
                    if (! preg_match(Regex::$patt->thisFunction, $value)) {
                        $this->declarations->data[strtolower($prop)] = $value;
                    }
                    $this->declarations->add($prop, $value, $index);
                }
            }
        }
    }

    public function __toString()
    {
        $process = Crush::$process;

        // Merge the extend selectors.
        $this->selectors->store += $this->extendSelectors;

        // Dereference and return empty string if there are no selectors or declarations.
        if (empty($this->selectors->store) || empty($this->declarations->store)) {
            $process->tokens->pop($this->label);

            return '';
        }

        $stub = $this->marker;

        if ($process->minifyOutput) {
            return "$stub{$this->selectors->join()}{{$this->declarations->join()}}";
        }
        else {
            $formatter = $process->ruleFormatter;
            return "$stub{$formatter($this)}";
        }
    }

    public function __clone()
    {
        $this->selectors = clone $this->selectors;
        $this->declarations = clone $this->declarations;
    }


    #############################
    #  Rule inheritance.

    public function setExtendSelectors($raw_value)
    {
        // Reset if called earlier, last call wins by intention.
        $this->extendArgs = array();

        foreach (Util::splitDelimList($raw_value) as $arg) {
            $this->extendArgs[] = new ExtendArg($arg);
        }
    }

    public function resolveExtendables()
    {
        if (! $this->extendArgs) {

            return false;
        }
        elseif (! $this->resolvedExtendables) {

            $references =& Crush::$process->references;

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

    public function applyExtendables()
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

            $extend_selectors = $this->selectors->store;

            // If there is a pseudo class extension create a new set accordingly.
            if ($extend_arg->pseudo) {

                $extend_selectors = array();
                foreach ($this->selectors->store as $readable => $selector) {
                    $new_selector = clone $selector;
                    $new_readable = $new_selector->appendPseudo($extend_arg->pseudo);
                    $extend_selectors[$new_readable] = $new_selector;
                }
            }
            $ancestor->extendSelectors += $extend_selectors;
        }
    }
}
