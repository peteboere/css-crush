<?php
/**
 *
 * Recursive file importing
 *
 */

class csscrush_importer {


	public static function save ( $data ) {

		$config = csscrush::$config;
		$options = csscrush::$options;

		// No saving if caching is disabled, return early
		if ( ! $options[ 'cache' ] ) {
			return;
		}

		// Write to config
		$config->data[ csscrush::$compileName ] = $data;

		// Need to store the current path so we can check we're using the right config path later
		$config->data[ 'originPath' ] = $config->path;

		// Save config changes
		file_put_contents( $config->path, serialize( $config->data ) );
	}


	public static function hostfile ( $hostfile ) {

		$config = csscrush::$config;
		$options = csscrush::$options;
		$regex = csscrush::$regex;

		// Keep track of all import file info for later logging
		$mtimes = array();
		$filenames = array();

		// Determine input; string or file
		// Extract the comments then strings
		$stream = isset( $hostfile->string ) ? $hostfile->string : file_get_contents( $hostfile->path );

		// If there's a prepend file, prepend it
		if ( $prependFile = csscrush_util::find( 'Prepend-local.css', 'Prepend.css' ) ) {
			$stream = file_get_contents( $prependFile ) . $stream;
		}
		
		$stream = csscrush::extractComments( $stream );
		$stream = csscrush::extractStrings( $stream );

		// This may be set non-zero during the script if an absolute URL is encountered
		$searchOffset = 0;

		// Recurses until the nesting heirarchy is flattened and all files are combined
		while ( preg_match( $regex->import, $stream, $match, PREG_OFFSET_CAPTURE, $searchOffset ) ) {

			$fullMatch     = $match[0][0];         // Full match
			$matchStart    = $match[0][1];         // Full match offset
			$matchEnd      = $matchStart + strlen( $fullMatch );
			$mediaContext  = trim( $match[2][0] ); // The media context if specified
			$preStatement  = substr( $stream, 0, $matchStart );
			$postStatement = substr( $stream, $matchEnd );

			// If just stripping the import statements
			if ( isset( $hostfile->importIgnore ) ) {
				$stream = $preStatement . $postStatement;
				continue;
			}

			$url = trim( $match[1][0] );

			// Url may be a string token
			if ( preg_match( $regex->token->string, $url ) ) {
				$import_url_token = new csscrush_string( $url );
				$url = $import_url_token->value;
				// $import_url_token = csscrush::$storage->tokens->strings[ $url ];
				// $url = trim( $import_url_token, '\'"' );
			}

			// csscrush::log( $url );

			// Pass over absolute urls
			// Move the search pointer forward
			if ( preg_match( $regex->absoluteUrl, $url ) ) {
				$searchOffset = $matchEnd;
				continue;
			}

			// Create import object
			$import = new stdClass;
			$import->url = $url;
			$import->mediaContext = $mediaContext;
			$import->hostDir = $hostfile->dir;

			// Check to see if the url is root relative
			// Flatten import path for convenience
			if ( strpos( $import->url, '/' ) === 0 ) {
				$import->path = realpath( $config->docRoot . $import->url );
			}
			else {
				$import->path = realpath( "$hostfile->dir/$import->url" );
			}

			$import->content = @file_get_contents( $import->path );

			// Failed to open import, just continue with the import line removed
			if ( ! $import->content ) {
				csscrush::log( "Import file '$import->url' not found" );
				$stream = $preStatement . $postStatement;
				continue;

			}
			// Import file opened successfully so we process it:
			//   We need to resolve import statement urls in all imported files since
			//   they will be brought inline with the hostfile
			else {

				// Start with extracting comments in the import
				$import->content = csscrush::extractComments( $import->content );

				$import->dir = dirname( $import->url );

				// Store import file info for cache validation
				$mtimes[] = filemtime( $import->path );
				$filenames[] = $import->url;

				// Alter all the url strings to be paths relative to the hostfile:
				//   Match all @import statements in the import content
				//   Store the replacements we might find
				$matchCount = preg_match_all( $regex->import, $import->content, $matchAll,
								PREG_OFFSET_CAPTURE );
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
						if ( ! $replace ) {
							$search = $fullMatch;
							$replace = '';
						}
					}

					// Trim the statement and set the resolved path
					$statement = trim( str_replace( $search, $replace, $fullMatch ) );

					// Normalise import statement to be without url() syntax:
					//   This is so relative urls can easily be targeted later
					$statement = self::normalizeImportStatement( $statement );

					$replacements[ $fullMatch ] = $statement;
				}

				// If we've stored any altered @import strings then we need to apply them
				if ( count( $replacements ) ) {
					$import->content = str_replace(
						array_keys( $replacements ),
						array_values( $replacements ),
						$import->content );
				}

				// Now @import urls have been adjusted extract strings
				$import->content = csscrush::extractStrings( $import->content );

				// Optionally rewrite relative url and custom function data-uri references
				if ( $options[ 'rewrite_import_urls' ] ) {
					$import->content = self::rewriteImportRelativeUrls( $import );
				}

				// Add media context if it exists
				if ( $import->mediaContext ) {
					$import->content = "@media $import->mediaContext {" . $import->content . '}';
				}

				$stream = $preStatement . $import->content . $postStatement;
			}

		} // End while

		// Save only if the hostfile object is associated with a real file
		if ( $hostfile->path ) {
			self::save( array(
				'imports'      => $filenames,
				'datem_sum'    => array_sum( $mtimes ) + $hostfile->mtime,
				'options'      => $options,
			));
		}

		return $stream;
	}


	protected static function normalizeImportStatement ( $statement ) {

		$url_import_patt = '!^@import\s+url\(\s*!';
		if ( preg_match( $url_import_patt, $statement ) ) {
			// Example matches:
			//   @import url( "some_path_with_(parens).css") screen and ( max-width: 500px );
			//   @import url( some_path.css );

			// Trim the first part
			$statement = preg_replace( $url_import_patt, '', $statement );

			// 'some_path_with_(parens).css') screen and ( max-width: 500px );
			if ( preg_match( '!^([\'"])!', $statement, $m ) ) {
				$statement = preg_replace( '!' . $m[1] . '\s*\)!', $m[1], $statement, 1 );
			}
			// some_path.css) screen and ( max-width: 500px );
			else {
				$statement = '"' . preg_replace( '!\s*\)!', '"', $statement, 1 );
			}
			// Pull back together
			$statement = '@import ' . $statement;
		}
		return $statement;
	}


	protected static function resolveAbsolutePath ( $url ) {

		$config = csscrush::$config;

		if ( ! file_exists ( $config->docRoot . $url ) ) {
			return false;
		}
		// Move upwards '..' by the number of slashes in baseURL to get a relative path
		$url = str_repeat( '../', substr_count( $config->baseURL, '/' ) ) . substr( $url, 1 );

		return $url;
	}


	protected static function rewriteImportRelativeUrls ( $import ) {

		$stream = $import->content;

		// We're comparing file system position so we'll
		$hostDir = csscrush_util::normalizeSystemPath( $import->hostDir, true );
		$importDir = csscrush_util::normalizeSystemPath( dirname( $import->path ), true );

		csscrush::$storage->tmp->relativeUrlPrefix = '';
		$url_prefix = '';

		if ( $importDir === $hostDir ) {
			// Do nothing if files are in the same directory
			return $stream;

		}
		elseif ( strpos( $importDir, $hostDir ) === false ) {
			// Import directory is higher than the host directory

			// Split the directory paths into arrays so we can compare segment by segment
			$host_segs = preg_split( '!/+!', $hostDir, null, PREG_SPLIT_NO_EMPTY );
			$import_segs = preg_split( '!/+!', $importDir, null, PREG_SPLIT_NO_EMPTY );

			// Shift the segments until they are on different branches
			while ( @( $host_segs[0] == $import_segs[0] ) ) {
				array_shift( $host_segs );
				array_shift( $import_segs );
				// csscrush::log( array( $host_segs, $import_segs ) );
			}

			// Count the remaining $host_segs to get the offset
			$level_diff = count( $host_segs );

			$url_prefix = str_repeat( '../', $level_diff ) . implode( '/', $import_segs );

		}
		else {
			// Import directory is lower than host directory

			// easy, url_prefix is the difference
			$url_prefix = substr( $importDir, strlen( $hostDir ) + 1 );
		}

		if ( empty( $url_prefix ) ) {
			return $stream;
		}

		// Add the directory seperator ending (if needed)
		if ( $url_prefix[ strlen( $url_prefix ) - 1 ] !== '/' ) {
			$url_prefix .= '/';
		}

		csscrush::log( 'relative_url_prefix: ' . $url_prefix );

		// Search for all relative url and data-uri references in the content
		// and prepend $relative_url_prefix

		// Make $url_prefix accessible in callback scope
		csscrush::$storage->tmp->relativeUrlPrefix = $url_prefix;

		$url_function_patt = '!
			([^a-z-])         # the preceeding character
			(data-uri|url)    # the function name
			\(\s*([^\)]+)\s*\) # the url
		!xi';
		$stream = preg_replace_callback( $url_function_patt,
					array( 'self', 'cb_rewriteImportRelativeUrl' ), $stream );

		return $stream;
	}


	protected static function cb_rewriteImportRelativeUrl ( $match ) {

		$regex = csscrush::$regex;
		$storage = csscrush::$storage;

		// The relative url prefix
		$relative_url_prefix = $storage->tmp->relativeUrlPrefix;

		list( $fullMatch, $before, $function, $url ) = $match;
		$url = trim( $url );

		// If the url is a string token we'll need to restore it as a string token later
		if ( $url_is_token = preg_match( $regex->token->string, $url ) ) {

			$url_token = new csscrush_string( $url );
			$url = $url_token->value;
		}

		// No rewrite if:
		//   $url begins with a variable, e.g '$('
		//   $url path is absolute or begins with slash
		//   $url is an empty string
		if (
			empty( $url ) or
			strpos( $url, '/' ) === 0 or
			strpos( $url, '$(' ) === 0 or
			preg_match( $regex->absoluteUrl, $url )
		) {
			// Token or not, it's ok to return the full match if $url is a root relative or absolute ref
			return $fullMatch;
		}

		// Prepend the relative url prefix
		$url = $relative_url_prefix . $url;

		// Restore quotes if $url was a string token
		if ( $url_is_token ) {
			$url = $url_token->quoteMark . $url . $url_token->quoteMark;
		}

		// Reconstruct the match and return
		return "$before$function($url)";
	}

}
