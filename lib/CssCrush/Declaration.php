<?php
/**
 *
 * Declaration objects.
 *
 */
class CssCrush_Declaration
{
    public $property;
    public $canonicalProperty;
    public $vendor;
    public $functions;
    public $value;
    public $index;
    public $skip;
    public $important;

    public function __construct ($prop, $value, $contextIndex = 0)
    {
        $regex = CssCrush_Regex::$patt;

        // Normalize input. Lowercase the property name.
        $prop = strtolower(trim($prop));
        $value = trim($value);

        // Check the input.
        if ($prop === '' || $value === '' || $value === null) {
            $this->inValid = true;

            return;
        }

        // Test for escape tilde.
        if ($skip = strpos($prop, '~') === 0) {
            $prop = substr($prop, 1);
        }

        // Store the canonical property name.
        // Store the vendor mark if one is present.
        if (preg_match($regex->vendorPrefix, $prop, $vendor)) {
            $canonical_property = $vendor[2];
            $vendor = $vendor[1];
        }
        else {
            $vendor = null;
            $canonical_property = $prop;
        }

        // Check for !important.
        if (($important = stripos($value, '!important')) !== false) {
            $value = rtrim(substr($value, 0, $important));
            $important = true;
        }

        // Reject declarations with empty CSS values.
        if ($value === false || $value === '') {
            $this->inValid = true;

            return;
        }

        $this->property          = $prop;
        $this->canonicalProperty = $canonical_property;
        $this->vendor            = $vendor;
        $this->index             = $contextIndex;
        $this->value             = $value;
        $this->skip              = $skip;
        $this->important         = $important;
    }

    public function __toString ()
    {
        if (CssCrush::$process->minifyOutput) {
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
    public function process ($parent_rule)
    {
        // Apply custom functions.
        if (! $this->skip) {

            // this() function needs to be called exclusively because
            // it's self referencing.
            $context = (object) array(
                'rule' => $parent_rule,
            );
            CssCrush_Function::executeOnString(
                $this->value,
                CssCrush_Regex::$patt->thisFunction,
                array(
                    'this' => 'csscrush_fn__this',
                ),
                $context);

            // Add result to $rule->selfData.
            $parent_rule->selfData += array($this->property => $this->value);

            $context = (object) array(
                'rule' => $parent_rule,
                'property' => $this->property
            );
            CssCrush_Function::executeOnString(
                $this->value,
                null,
                null,
                $context);
        }

        // Trim whitespace that may have been introduced by functions.
        $this->value = trim($this->value);

        // After functions have applied value may be empty.
        if ($this->value === '') {

            $this->inValid = true;
            return;
        }

        // Store raw value as data on the parent rule.
        $parent_rule->queryData[$this->property] = $this->value;

        // Capture top-level paren pairs.
        CssCrush::$process->captureParens($this->value);

        $this->indexFunctions();
    }

    public function indexFunctions ()
    {
        // Create an index of all regular functions in the value.
        $functions = array();
        if (preg_match_all(CssCrush_Regex::$patt->function, $this->value, $m)) {
            foreach ($m[1] as $index => $fn_name) {
                $functions[strtolower($fn_name)] = true;
            }
        }
        $this->functions = $functions;
    }

    public function getFullValue ()
    {
        return CssCrush::$process->restoreTokens($this->value, 'p');
    }
}
