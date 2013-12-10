<?php
/**
 *
 * Selector lists.
 *
 */
namespace CssCrush;

class SelectorList extends Iterator
{
    public $store;

    public function __construct()
    {
        parent::__construct();
    }

    public function add(Selector $selector)
    {
        $this->store[$selector->readableValue] = $selector;
    }

    public function join($glue = ',')
    {
        return implode($glue, $this->store);
    }

    public function expand()
    {
        $new_set = array();

        static $any_patt, $reg_comma;
        if (! $any_patt) {
            $any_patt = Regex::make('~:any({{p-token}})~i');
            $reg_comma = '~\s*,\s*~';
        }

        foreach ($this->store as $readableValue => $selector) {

            $pos = stripos($selector->value, ':any?');
            if ($pos !== false) {

                // Contains an :any statement so expand.
                $chain = array('');
                do {
                    if ($pos === 0) {
                        preg_match($any_patt, $selector->value, $m);

                        // Parse the arguments
                        $expression = CssCrush::$process->tokens->get($m[1]);

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

                    $new = new Selector($row . $selector->value);
                    $new_set[$new->readableValue] = $new;
                }
            }
            else {

                // Nothing to expand.
                $new_set[$readableValue] = $selector;
            }

        } // foreach

        $this->store = $new_set;
    }
}
