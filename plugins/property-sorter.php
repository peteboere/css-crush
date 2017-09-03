<?php
/**
 * Customizable property sorting
 *
 * @see docs/plugins/property-sorter.md
 */
namespace CssCrush {

    \csscrush_plugin('property-sorter', function ($process) {
        $process->on('rule_prealias', 'CssCrush\property_sorter');
    });

    function property_sorter(Rule $rule) {

        usort($rule->declarations->store, 'CssCrush\property_sorter_callback');
    }


    /*
        Callback for sorting.
    */
    function property_sorter_callback($a, $b) {

        $map =& property_sorter_get_table();
        $a_prop =& $a->canonicalProperty;
        $b_prop =& $b->canonicalProperty;
        $a_listed = isset($map[$a_prop]);
        $b_listed = isset($map[$b_prop]);

        // If the properties are identical we need to flag for an index comparison.
        $compare_indexes = false;

        // If the 'canonical' properties are identical we need to flag for a vendor comparison.
        $compare_vendor = false;

        // If both properties are listed.
        if ($a_listed && $b_listed) {

            if ($a_prop === $b_prop) {
                if ($a->vendor || $b->vendor) {
                    $compare_vendor = true;
                }
                else {
                    $compare_indexes = true;
                }
            }
            else {
                // Table comparison.
                return $map[$a_prop] > $map[$b_prop] ? 1 : -1;
            }
        }

        // If one property is listed it always takes higher priority.
        elseif ($a_listed && ! $b_listed) {
            return -1;
        }
        elseif ($b_listed && ! $a_listed) {
            return 1;
        }

        // If neither property is listed.
        else {

            if ($a_prop === $b_prop) {
                if ($a->vendor || $b->vendor) {
                    $compare_vendor = true;
                }
                else {
                    $compare_indexes = true;
                }
            }
            else {
                // Regular sort.
                return $a_prop > $b_prop ? 1 : -1;
            }
        }

        // Comparing by index.
        if ($compare_indexes ) {
            return $a->index > $b->index ? 1 : -1;
        }

        // Comparing by vendor mark.
        if ($compare_vendor) {
            if (! $a->vendor && $b->vendor) {
                return 1;
            }
            elseif ($a->vendor && ! $b->vendor) {
                return -1;
            }
            else {
                // If both have a vendor mark compare vendor name length.
                return strlen($b->vendor) > strlen($a->vendor) ? 1 : -1;
            }
        }
    }


    /*
        Cache for the table of values to compare against.
    */
    function &property_sorter_get_table () {

        // Check for cached table.
        if (isset($GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'])) {
            return $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'];
        }

        $table = [];

        // Nothing cached, check for a user-defined table.
        if (isset($GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'])) {
            $table = (array) $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'];
        }

        // No user-defined table, use pre-defined.
        else {

            // Load from property-sorting.ini.
            $sorting_file_contents = file_get_contents(Crush::$dir . '/misc/property-sorting.ini');
            if ($sorting_file_contents !== false) {

                $sorting_file_contents = preg_replace('~;[^\r\n]*~', '', $sorting_file_contents);
                $table = preg_split('~\s+~', trim($sorting_file_contents));
            }
            else {
                notice("Property sorting file not found.");
            }

            // Store to the global variable.
            $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'] = $table;
        }

        // Cache the table (and flip it).
        $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'] = array_flip($table);

        return $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'];
    }

}

namespace {

    /*
        Get the current sorting table.
    */
    function csscrush_get_property_sort_order() {
        CssCrush\property_sorter_get_table();
        return $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'];
    }


    /*
        Set a custom sorting table.
    */
    function csscrush_set_property_sort_order(array $new_order) {
        unset($GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE']);
        $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'] = $new_order;
    }
}
