<?php
/**
 * Customizable property sorting
 *
 * Examples use the predefined sorting table.
 *
 * To define a custom sorting order pass an array to csscrush_set_property_sort_order():
 *
 *     csscrush_set_property_sort_order( array( 'color', ... ) );
 *
 *
 * @before
 *     color: red;
 *     background: #000;
 *     opacity: .5;
 *     display: block;
 *
 * @after
 *     display: block;
 *     opacity: .5;
 *     color: red;
 *     background: #000;
 *
 */

CssCrush_Plugin::register('property-sorter', array(
    'enable' => 'csscrush__enable_property_sorter',
    'disable' => 'csscrush__disable_property_sorter',
));

function csscrush__enable_property_sorter () {
    CssCrush_Hook::add('rule_prealias', 'csscrush__property_sorter');
}

function csscrush__disable_property_sorter () {
    CssCrush_Hook::remove('rule_prealias', 'csscrush__property_sorter');
}

function csscrush__property_sorter (CssCrush_Rule $rule) {

    $new_set = array();

    // Create plain array of rule declarations.
    foreach ($rule as $declaration) {
        $new_set[] = $declaration;
    }

    usort($new_set, '_csscrush__property_sorter_callback');

    $rule->setDeclarations($new_set);
}


/*
    Callback for sorting.
*/
function _csscrush__property_sorter_callback ($a, $b) {

    $map =& _csscrush__property_sorter_get_table();
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
function &_csscrush__property_sorter_get_table () {

    // Check for cached table.
    if (isset($GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'])) {
        return $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'];
    }

    $table = array();

    // Nothing cached, check for a user-defined table.
    if (isset($GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'])) {
        $table = (array) $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'];
    }

    // No user-defined table, use pre-defined.
    else {

        // Load from property-sorting.ini.
        $sorting_file_contents =
            file_get_contents(CssCrush::$config->location . '/misc/property-sorting.ini');
        if ($sorting_file_contents !== false) {

            $sorting_file_contents = preg_replace('~;[^\r\n]*~', '', $sorting_file_contents);
            $table = preg_split('~\s+~', trim($sorting_file_contents));
        }
        else {
            trigger_error(__METHOD__ . ": Property sorting file not found.\n", E_USER_NOTICE);
        }

        // Store to the global variable.
        $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'] = $table;
    }

    // Cache the table (and flip it).
    $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'] = array_flip($table);

    return $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE'];
}


/*
    Get the current sorting table.
*/
function csscrush_get_property_sort_order () {
    _csscrush__property_sorter_get_table();
    return $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'];
}


/*
    Set a custom sorting table.
*/
function csscrush_set_property_sort_order (array $new_order) {
    unset($GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER_CACHE']);
    $GLOBALS['CSSCRUSH_PROPERTY_SORT_ORDER'] = $new_order;
}
