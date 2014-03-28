<?php
/**
 *
 * Selector lists.
 *
 */
namespace CssCrush;

class SelectorList extends Iterator
{
    public function __construct($selector_string, Rule $rule)
    {
        parent::__construct();

        $selector_string = trim(Util::stripCommentTokens($selector_string));

        foreach (Util::splitDelimList($selector_string) as $selector) {

            if (preg_match(Regex::$patt->abstract, $selector, $m)) {
                $rule->name = strtolower($m['name']);
                $rule->isAbstract = true;
            }
            else {
                $this->add(new Selector($selector));
            }
        }
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
        static $grouping_patt, $expand, $expandSelector;
        if (! $grouping_patt) {

            $grouping_patt = Regex::make('~\:any{{ parens }}~iS');

            $expand = function ($selector_string) use ($grouping_patt)
            {
                if (preg_match($grouping_patt, $selector_string, $m, PREG_OFFSET_CAPTURE)) {

                    list($full_match, $full_match_offset) = $m[0];
                    $before = substr($selector_string, 0, $full_match_offset);
                    $after = substr($selector_string, strlen($full_match) + $full_match_offset);
                    $selectors = array();

                    // Allowing empty strings for more expansion possibilities.
                    foreach (Util::splitDelimList($m['parens_content'][0], array('allow_empty_strings' => true)) as $segment) {
                        if ($selector = trim("$before$segment$after")) {
                            $selectors[$selector] = true;
                        }
                    }

                    return $selectors;
                }

                return false;
            };

            $expandSelector = function ($selector_string) use ($expand)
            {
                if ($running_stack = $expand($selector_string))  {

                    $flattened_stack = array();
                    do {
                        $loop_stack = array();
                        foreach ($running_stack as $selector => $bool) {
                            $selectors = $expand($selector);
                            if (! $selectors) {
                                $flattened_stack += array($selector => true);
                            }
                            else {
                                $loop_stack += $selectors;
                            }
                        }
                        $running_stack = $loop_stack;

                    } while ($loop_stack);

                    return $flattened_stack;
                }

                return array($selector_string => true);
            };
        }

        $expanded_set = array();

        foreach ($this->store as $original_selector) {
            if (stripos($original_selector->value, ':any(') !== false) {
                foreach ($expandSelector($original_selector->value) as $selector_string => $bool) {
                    $new = new Selector($selector_string);
                    $expanded_set[$new->readableValue] = $new;
                }
            }
            else {
                $expanded_set[$original_selector->readableValue] = $original_selector;
            }
        }

        $this->store = $expanded_set;
    }
}
