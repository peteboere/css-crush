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

    public $parent;
    public $previous;
    public $next;

    public $selectors;
    public $declarations;

    // Arugments passed via @extend.
    public $extendArgs = array();
    public $extendSelectors = array();

    public function __construct($selectorString, $declarationsString, $traceToken = null)
    {
        $process = Crush::$process;
        $this->label = $process->tokens->createLabel('r');
        $this->marker = $process->generateMap ? $traceToken : null;
        $this->selectors = new SelectorList($selectorString, $this);
        $this->declarations = new DeclarationList($declarationsString, $this);
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

    public function setExtendSelectors($rawValue)
    {
        // Reset if called earlier, last call wins by intention.
        $this->extendArgs = array();

        foreach (Util::splitDelimList($rawValue) as $arg) {
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
            foreach ($this->extendArgs as $extendArg) {

                if (isset($references[$extendArg->name])) {
                    $parentRule = $references[$extendArg->name];
                    $parentRule->resolveExtendables();
                    $extendArg->pointer = $parentRule;
                    $filtered[$parentRule->label] = $extendArg;
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
        $parentExtendArgs = array();
        foreach ($this->extendArgs as $extendArg) {
            $parentExtendArgs += $extendArg->pointer->extendArgs;
        }

        // Merge this rule's extendArgs with parent extendArgs.
        $this->extendArgs += $parentExtendArgs;

        // Add this rule's selectors to all extendArgs.
        foreach ($this->extendArgs as $extendArg) {

            $ancestor = $extendArg->pointer;

            $extendSelectors = $this->selectors->store;

            // If there is a pseudo class extension create a new set accordingly.
            if ($extendArg->pseudo) {

                $extendSelectors = array();
                foreach ($this->selectors->store as $selector) {
                    $newSelector = clone $selector;
                    $newReadable = $newSelector->appendPseudo($extendArg->pseudo);
                    $extendSelectors[$newReadable] = $newSelector;
                }
            }
            $ancestor->extendSelectors += $extendSelectors;
        }
    }
}
