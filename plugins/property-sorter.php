<?php
/**
 * Property sorter.
 * Order CSS properties according to a sorting table.
 * 
 * Examples use the predefined sorting table.
 * 
 * To define a custom sorting table globally define $CSSCRUSH_PROPERTY_SORT_ORDER.
 * Assign an empty array to create an alphabetical sort:
 * 
 *     $CSSCRUSH_PROPERTY_SORT_ORDER = array( 'color', ... );
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

csscrush_hook::add( 'rule_prealias', 'csscrush__property_sorter' );

function csscrush__property_sorter ( csscrush_rule $rule ) {

	$new_set = array();

	// Create plain array of rule declarations.
	foreach ( $rule as $declaration ) {
		$new_set[] = $declaration;
	}

	usort( $new_set, '_csscrush__property_sorter_callback' );

	$rule->declarations = $new_set;
}


/*
	Callback for sorting
*/
function _csscrush__property_sorter_callback ( $a, $b ) {

	$map =& _csscrush__property_sorter_get_table();
	$a_prop =& $a->property;
	$b_prop =& $b->property;
	$a_listed = isset( $map[ $a_prop ] );
	$b_listed = isset( $map[ $b_prop ] );

	// If both properties are listed do a table comparison.
	if ( $a_listed && $b_listed ) {

		if ( $a_prop === $b_prop ) {
			return $a->index > $b->index ? 1 : -1;
		}
		return $map[ $a_prop ] > $map[ $b_prop ] ? 1 : -1;
	}

	// Listed properties always come before un-listed.
	if ( $a_listed && ! $b_listed ) {
		return -1;
	}
	if ( $b_listed && ! $a_listed ) {
		return 1;
	}

	// If propertes are the same compare declaration indexes.
	if ( $a_prop === $b_prop ) {
		return $a->index > $b->index ? 1 : -1;
	}

	// If neither property is listed do a regular sort.
	return $a_prop > $b_prop ? 1 : -1;
}


/*
	Cache for the table of values to compare against.

	If you need to re-define the sort table during runtime unset the cache first:
	unset( $GLOBALS[ 'CSSCRUSH_PROPERTY_SORT_ORDER_CACHE' ] );
*/
function &_csscrush__property_sorter_get_table () {

	// Check for cached table.
	if ( isset( $GLOBALS[ 'CSSCRUSH_PROPERTY_SORT_ORDER_CACHE' ] ) ) {
		return $GLOBALS[ 'CSSCRUSH_PROPERTY_SORT_ORDER_CACHE' ];
	}

	$table = array();

	// Nothing cached, check for a user-defined table.
	if ( isset( $GLOBALS[ 'CSSCRUSH_PROPERTY_SORT_ORDER' ] ) ) {
		$table = (array) $GLOBALS[ 'CSSCRUSH_PROPERTY_SORT_ORDER' ];
	}

	// No user-defined table, use pre-defined.
	else {

		// Load from property-sorting.ini
		if ( $sorting_file_contents
			= file_get_contents( csscrush::$config->location . '/misc/property-sorting.ini' ) ) {

			$sorting_file_contents = preg_replace( '!;[^\r\n]*!', '', $sorting_file_contents );
			$table = preg_split( '!\s+!', trim( $sorting_file_contents ) );
		}
		else {
			trigger_error( __METHOD__ . ": Property sorting file not found.\n", E_USER_NOTICE );
		}
	}

	// Add in prefixed properties based on the aliases file
	$collated_table = array();
	$property_aliases =& csscrush::$config->aliases[ 'properties' ];
	$priority = 0;

	foreach ( $table as $property ) {
		if ( isset( $property_aliases[ $property ] ) ) {
			foreach ( $property_aliases[ $property ] as &$property_alias ) {
				$collated_table[ $property_alias ] = ++$priority;
			}
		}
		$collated_table[ $property ] = ++$priority;
	}

	// Cache the collated table
	$GLOBALS[ 'CSSCRUSH_PROPERTY_SORT_ORDER_CACHE' ] = $collated_table;

	return $GLOBALS[ 'CSSCRUSH_PROPERTY_SORT_ORDER_CACHE' ];
}
