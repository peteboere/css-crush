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
    public $valid = true;

    public function __construct($property, $value, $contextIndex = 0)
    {
        // Normalize the property name.
        $property = strtolower($property);

        // Test for escape tilde.
        if ($skip = strpos($property, '~') === 0) {
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
            $important = true;
        }

        Crush::$process->hooks->run('declaration_preprocess', array('property' => &$property, 'value' => &$value));

        // Reject declarations with empty CSS values.
        if ($value === false || $value === '') {
            $this->valid = false;
        }

        $this->property = $property;
        $this->canonicalProperty = $canonical_property;
        $this->vendor = $vendor;
        $this->index = $contextIndex;
        $this->value = $value;
        $this->skip = $skip;
        $this->important = $important;
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
        Capture parens.
        Index functions.
    */
    public function process($parent_rule)
    {
        // Apply custom functions.
        if (! $this->skip) {

            // this() function needs to be called exclusively because
            // it's self referencing.
            $context = (object) array(
                'rule' => $parent_rule,
            );
            $this->value = Functions::executeOnString(
                $this->value,
                Regex::$patt->thisFunction,
                array(
                    'this' => 'CssCrush\fn__this',
                ),
                $context);

            $parent_rule->declarations->data += array($this->property => $this->value);

            $context = (object) array(
                'rule' => $parent_rule,
                'property' => $this->property
            );
            $this->value = Functions::executeOnString(
                $this->value,
                null,
                null,
                $context);
        }

        // Trim whitespace that may have been introduced by functions.
        $this->value = trim($this->value);

        // After functions have applied value may be empty.
        if ($this->value === '') {

            $this->valid = false;
            return;
        }

        // Store value as data on the parent rule.
        $parent_rule->declarations->queryData[$this->property] = $this->value;

        $this->indexFunctions();
    }

    public function indexFunctions()
    {
        // Create an index of all regular functions in the value.
        $functions = array();
        if (preg_match_all(Regex::$patt->functionTest, $this->value, $m)) {
            foreach ($m['func_name'] as $index => $fn_name) {
                $functions[strtolower($fn_name)] = true;
            }
        }
        $this->functions = $functions;
    }
}
