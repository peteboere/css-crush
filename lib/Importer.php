<?php
/**
 *
 * Recursive file importing
 *
 */

class CssCrush_Importer {

	public static function save ( $data ) {

		$config = CssCrush::$config;

		// Write to config
		$config->data[ CssCrush::$compileName ] = $data;

		// Need to store the current path so we can check we're using the right config path later
		$config->data[ 'originPath' ] = $config->path;

		// Save config changes
		file_put_contents( $config->path, serialize( $config->data ) );
	}

	public static function hostfile ( $hostfile ) {

		$config = CssCrush::$config;
		$regex = CssCrush::$regex;

		// Keep track of all import file info for later logging
		$mtimes = array();
		$filenames = array();

		// Get the hostfile with comments extracted
		$str = CssCrush::extractComments( file_get_contents( $hostfile->path ) );

		// This may be set non-zero if an absolute URL is encountered
		$searchOffset = 0;

		// Recurses until the nesting heirarchy is flattened and all files are combined
		while ( preg_match( $regex->import, $str, $match, PREG_OFFSET_CAPTURE, $searchOffset ) ) {

			$fullMatch     = $match[0][0];         // Full match
			$matchStart    = $match[0][1];         // Full match offset
			$matchEnd      = $matchStart + strlen( $fullMatch );
			$url           = trim( $match[1][0] ); // The url
			$mediaContext  = trim( $match[2][0] ); // The media context if specified
			$preStatement  = substr( $str, 0, $matchStart );
			$postStatement = substr( $str, $matchEnd );

			// Pass over absolute urls
			// Move the search pointer forward
			if ( preg_match( '!^https?://!', $url ) ) {
				$searchOffset = $matchEnd;
				continue;
			}

			$import = new stdClass;
			$import->name = $url;
			$import->mediaContext = $mediaContext;

			// Check to see if the url is root relative
			if ( strpos( $import->name, '/' ) === 0 ) {
				$import->path = $config->docRoot . $import->name;
			}
			else {
				$import->path = "$hostfile->dir/$import->name";
			}

			$import->content = @file_get_contents( $import->path );

			// Failed to open import, just continue with the import line removed
			if ( !$import->content ) {
				CssCrush::log( "Import file '$import->name' not found" );
				$str = $preStatement . $postStatement;
				continue;

			}
			// Import file opened successfully so we process it
			// We need to resolve relative urls in all imported files since they will be brought inline with the hostfile
			else {

				// Start with extracting comments in the import
				$import->content = CssCrush::extractComments( $import->content );

				$import->dir = dirname( $import->name );

				// Store import file info
				$mtimes[] = filemtime( $import->path );
				$filenames[] = $import->name;

				// Match all @import statements in the import content
				// Alter all the url strings to be paths relative to the hostfile
				$matchCount = preg_match_all( $regex->import, $import->content, $matchAll, PREG_OFFSET_CAPTURE );
				// Store the replacements we might find
				$replacements = array();
				for ( $index = 0; $index < $matchCount; $index++ ) {

					$fullMatch = $matchAll[0][ $index ][0];
					$urlMatch  = $matchAll[1][ $index ][0];

					$search = $urlMatch;
					$replace = "$import->dir/$urlMatch";

					// Try to resolve absolute paths
					// On failure strip the @import statement
					if ( strpos( $urlMatch, '/' ) === 0 ) {
						$replace = self::resolveAbsolutePath( $urlMatch );
						if ( !$replace ) {
							$search = $fullMatch;
							$replace = '';
						}
					}

					$statement = trim( str_replace( $search, $replace, $fullMatch ) );

					// TODO: Normalise import statement to be without url() syntax
					// 
					// $patt = '!^@import\s+url\(\s*[\'"]?!';
					// if ( preg_match( $patt, $statement ) ) {
					// 
					// 	// @import url( "some_path_with_(parens).css") screen and ( max-width: 500px );
					// 	// @import url( some_path_with_(parens).css );
					// 
					// 	$statement = preg_replace( '!^@import\s+url\(\s*!', '', $statement );
					// 
					// 	// 'some_path_with_(parens).css') screen and ( max-width: 500px );
					// 	// some_path_with_(parens).css) screen and ( max-width: 500px );
					// 
					// 	// A url surrounded in quotes
					// 	// if ( preg_match( '!^([\'"])!', $statement, $m ) ) {
					// 	// 			if ( ( $closing_quote_index = strpos( $statement, $m[1], 1 ) ) === false ) {
					// 	// 				// Mismatched quote
					// 	// 			}
					// 	// 			$closing_quote_index;
					// 	//
					// 	// 		}
					// 	// 		else {
					// 	//
					// 	// 		}
					// }
					
					$replacements[ $fullMatch ] = $statement;
				}
				// If we've stored any altered @import strings then we need to apply them
				if ( count( $replacements ) ) {
					$import->content = str_replace(
						array_keys( $replacements ),
						array_values( $replacements ),
						$import->content );
				}

				// TODO: Optionally resolve relative url and custom function data-uri references

				// Add media context if it exists
				if ( $import->mediaContext ) {
					$import->content = "@media $import->mediaContext {" . $import->content . '}';
				}

				$str = $preStatement . $import->content . $postStatement;
			}

		} // End while

		self::save( array(
			'imports'      => $filenames,
			'datem_sum'    => array_sum( $mtimes ) + $hostfile->mtime,
			'options'      => CssCrush::$options,
		));

		return $str;
	}

	protected static function resolveAbsolutePath ( $url ) {
		$config = CssCrush::$config;

		if ( !file_exists ( $config->docRoot . $url ) ) {
			return false;
		}
		// Move upwards '..' by the number of slashes in baseURL to get a relative path
		$url = str_repeat( '../', substr_count( $config->baseURL, '/' ) ) . substr( $url, 1 );
		return $url;
	}

}
