<?php
/**
 * 
 *  Utilities
 * 
 */
class CssCrush_Util {

	// Create html attribute string from array
	static public function attributes ( array $attributes ) {
		$attr_string = '';
		foreach ( $attributes as $name => $value ) {
			$value = htmlspecialchars( $value, ENT_COMPAT, 'UTF-8', false );
			$attr_string .= " $name=\"$value\"";
		}
		return $attr_string;
	}

}