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
        $this->selectors = new SelectorList($selector_string, $this);
        $this->declarations = new DeclarationList($declarations_string, $this);
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
            foreach ($this->extendArgs as $extend_arg) {

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
                foreach ($this->selectors->store as $selector) {
                    $new_selector = clone $selector;
                    $new_readable = $new_selector->appendPseudo($extend_arg->pseudo);
                    $extend_selectors[$new_readable] = $new_selector;
                }
            }
            $ancestor->extendSelectors += $extend_selectors;
        }
    }
}
