<?php
/**
 *
 * Declaration objects.
 *
 */
namespace CssCrush;

class Declaration
{
    public $property;
    public $canonicalProperty;
    public $vendor;
    public $functions;
    public $value;
    public $index;
    public $skip = false;
    public $important = false;
    public $custom = false;
    public $valid = true;

    public function __construct($property, $value, $contextIndex = 0)
    {
        // Normalize, but preserve case if a custom property.
        if (strpos($property, '--') === 0) {
            $this->custom = true;
            $this->skip = true;
        }
        else {
            $property = strtolower($property);
        }

        if ($this->skip = strpos($property, '~') === 0) {
            $property = substr($property, 1);
        }

        // Store the canonical property name.
        // Store the vendor mark if one is present.
        if (preg_match(Regex::$patt->vendorPrefix, $property, $vendor)) {
            $canonical_property = $vendor[2];
            $vendor = $vendor[1];
        }
        else {
            $vendor = null;
            $canonical_property = $property;
        }

        // Check for !important.
        if (($important = stripos($value, '!important')) !== false) {
            $value = rtrim(substr($value, 0, $important));
            $this->important = true;
        }

        Crush::$process->emit('declaration_preprocess', ['property' => &$property, 'value' => &$value]);

        // Reject declarations with empty CSS values.
        if ($value === false || $value === '') {
            $this->valid = false;
        }

        $this->property = $property;
        $this->canonicalProperty = $canonical_property;
        $this->vendor = $vendor;
        $this->index = $contextIndex;
        $this->value = $value;
    }

    public function __toString()
    {
        if (Crush::$process->minifyOutput) {
            $whitespace = '';
        }
        else {
            $whitespace = ' ';
        }
        $important = $this->important ? "$whitespace!important" : '';

        return "$this->property:$whitespace$this->value$important";
    }

    /*
        Execute functions on value.
        Index functions.
    */
    public function process($parentRule)
    {
        static $thisFunction;
        if (! $thisFunction) {
            $thisFunction = new Functions(['this' => 'CssCrush\fn__this']);
        }

        if (! $this->skip) {

            // this() function needs to be called exclusively because it is self referencing.
            $context = (object) [
                'rule' => $parentRule
            ];
            $this->value = $thisFunction->apply($this->value, $context);

            if (isset($parentRule->declarations->data)) {
                $parentRule->declarations->data += [$this->property => $this->value];
            }

            $context = (object) [
                'rule' => $parentRule,
                'property' => $this->property
            ];
            $this->value = Crush::$process->functions->apply($this->value, $context);
        }

        // Whitespace may have been introduced by functions.
        $this->value = trim($this->value);

        if ($this->value === '') {
            $this->valid = false;
            return;
        }

        $parentRule->declarations->queryData[$this->property] = $this->value;

        $this->indexFunctions();
    }

    public function indexFunctions()
    {
        // Create an index of all regular functions in the value.
        $functions = [];
        if (preg_match_all(Regex::$patt->functionTest, $this->value, $m)) {
            foreach ($m['func_name'] as $fn_name) {
                $functions[strtolower($fn_name)] = true;
            }
        }
        $this->functions = $functions;
    }
}
