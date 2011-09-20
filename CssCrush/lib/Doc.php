<?php

class CssCrush_Doc {
	
	public static function parseDocComment ( $docComment ) {

		$doc = new stdClass;
		$doc->desc = '';
		$doc->return = 'void';

		// Early exit of there is no supporting doc comment
		if ( empty( $docComment ) ) {
			return false;
		}

		// Trim the doc comment and split into lines 
		$docComment = ltrim( substr( $docComment, 3, -2 ), '*' );
		$docComment = preg_replace( '!\n(\s*)[*]!', "\n$1", $docComment );
		$docComment = preg_split( '!\n!', $docComment, null );

		// Store the description
		while ( count( $docComment ) and strpos( ltrim( $docComment[0] ), '@' ) !== 0 ) {
			$doc->desc .= array_shift( $docComment ) . ' ';
		}
		$doc->desc = trim( $doc->desc );

		// Loop through the attributes
		// storing them as objects
		while ( count( $docComment ) and strpos( ltrim( $docComment[0] ), '@' ) === 0 ) {

			$attr = new stdClass;
			$first = ltrim( array_shift( $docComment ), ' ' );
			$tokens = preg_split( '!\s+!', $first, null, PREG_SPLIT_NO_EMPTY );

			// First argument will always be present
			$attr->kind = substr( array_shift( $tokens ), 1 );

			// Cat any multiline examples or descriptions
			$attr->body = '';
			while ( count( $docComment ) and strpos( ltrim( $docComment[0] ), '@' ) !== 0 ) {
				$attr->body .= array_shift( $docComment ) . "\n";
			}

			if ( $attr->kind == 'param' ) {
				$attr->value = array_shift( $tokens );
				$attr->arg = array_shift( $tokens );
				$attr->argClean = ltrim( $attr->arg, '&$' );
				$attr->desc = implode( ' ', $tokens );
				$doc->param[ $attr->argClean ] = $attr;
			}
			elseif ( $attr->kind == 'return' ) {
				$attr->value = array_shift( $tokens );
				$attr->desc = implode( ' ', $tokens );
				$doc->return = $attr;
			}
			else {
				$doc->{ $attr->kind }[] = $attr;
			}
		}
		return $doc;
	}

	public static function createMethodSignature ( $methodName, $reflectionObj, $docObj ) {

		$required = array();
		$optionals = array();
		$return = isset( $docObj->return->value ) ? $docObj->return->value : 'void';

		// Loop parameters
		foreach ( $reflectionObj->getParameters() as $param ) {
			$referenced = $param->isPassedByReference() ? '&' : '';
			$name = $param->getName();
			$value = 'void';

			if ( isset( $docObj->param[ $name ]->value ) ) {
				$value = $docObj->param[ $name ]->value;
			}
			$varName = $referenced . '$' . $name;
			if ( $param->isOptional() ) {
				$optionals[] = "$value <i>$varName</i>";
			}
			else {
				$required[] = "$value <i>$varName</i>";
			}
		}

		if ( count( $optionals ) ) {
			$count = count( $optionals );
			$optionals = 
				implode( ' [, ', $optionals ) . 
				' ' . 
				str_repeat( ']', $count );
			$optionals = ( count( $required ) > 0 ? '[, ' : '[ ' ) . $optionals;
		}
		else {
			$optionals = '';
		}

		$required = implode( ', ', $required );

		$arguments = implode( ' ', array_filter( array( $required, $optionals ) ) );
		$arguments = empty( $arguments ) ? $arguments : " $arguments ";
		$signature = "$return <b>$methodName</b> ($arguments)\n";

		return $signature;
	}

}

