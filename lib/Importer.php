<?php
/**
 *
 * Recursive file importing
 *
 */

class csscrush_importer {


	public static function save ( $data ) {

		$process = csscrush::$process;
		$options = csscrush::$options;

		// No saving if caching is disabled, return early
		if ( ! $options[ 'cache' ] ) {
			return;
		}

		// Write to config
		$process->cacheData[ $process->outputFileName ] = $data;

		// Save config changes
		csscrush::io_call( 'saveCacheData' );
	}


	public static function hostfile () {

		$config = csscrush::$config;
		$process = csscrush::$process;
		$options = csscrush::$options;
		$regex = csscrush_regex::$patt;
		$hostfile = $process->input;

		// Keep track of all import file info for later logging
		$mtimes = array();
		$filenames = array();

		// Determine input; string or file
		// Extract the comments then strings
		if ( isset( $hostfile->string ) ) {
			$stream = $hostfile->string;
		}
		else {
			$stream = file_get_contents( $hostfile->path );
		}

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

			$fullMatch     = $match[0][0]; // Full match
			$matchStart    = $match[0][1]; // Full match offset
			$matchEnd      = $matchStart + strlen( $fullMatch );
			$preStatement  = substr( $stream, 0, $matchStart );
			$postStatement = substr( $stream, $matchEnd );

			// If just stripping the import statements
			if ( isset( $hostfile->importIgnore ) ) {
				$stream = $preStatement . $postStatement;
				continue;
			}

			// The media context (if specified) at position 3 in the match
			$mediaContext = trim( $match[3][0] );

			// The url may be at position 1 or 2 in the match depending on the syntax used
			$url = trim( $match[1][0] );
			if ( ! $url ) {
				$url = trim( $match[2][0] );
			}

			// Url may be a string token
			if ( preg_match( $regex->stringToken, $url ) ) {
				$import_url_token = new csscrush_string( $url );
				$url = $import_url_token->value;
			}

			// Pass over absolute urls
			// Move the search pointer forward
			if ( preg_match( $regex->absoluteUrl, $url ) ) {
				$searchOffset = $matchEnd;
				continue;
			}

			// Create import object
			$import = new stdclass();
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
				csscrush::log( "Import file '$import->url' not found at '$import->path'" );
				$stream = $preStatement . $postStatement;
				continue;

			}
			else {
				// Import file opened successfully so we process it:
				//   We need to resolve import statement urls in all imported files since
				//   they will be brought inline with the hostfile

				// Start with extracting strings and comments in the import
				$import->content = csscrush::extractComments( $import->content );
				$import->content = csscrush::extractStrings( $import->content );

				$import->dir = dirname( $import->url );

				// Store import file info for cache validation
				$mtimes[] = filemtime( $import->path );
				$filenames[] = $import->url;

				// Alter all the url strings to be paths relative to the hostfile:
				//   Match all @import statements in the import content
				//   Store the replacements we might find
				$matchCount = preg_match_all( $regex->import, $import->content, $matchAll,
								PREG_OFFSET_CAPTURE | PREG_SET_ORDER );
				$replacements = array();

				for ( $index = 0; $index < $matchCount; $index++ ) {

					$fullMatch = $matchAll[ $index ][0][0];
					
					// Url match may be at one of 2 positions
					if ( $matchAll[ $index ][1][1] == -1 ) {
						$urlMatch = $matchAll[ $index ][2][0];
					}
					else {
						$urlMatch = $matchAll[ $index ][1][0];
					}

					// Url may be a string token
					if ( $urlMatchToken = preg_match( $regex->stringToken, $urlMatch ) ) {
						// Store the token
						$urlMatchToken = new csscrush_string( $urlMatch );
						// Set $urlMatch to the actual value
						$urlMatch = $urlMatchToken->value;
					}

					// Search and replace on the statement url
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

					// The full revised statement for replacement
					$statement = $fullMatch;

					if ( $urlMatchToken && ! empty( $replace ) ) {
						// Alter the stored token on internal hash table
						$urlMatchToken->update( $replace );
					}
					else {
						// Trim the statement and set the resolved path
						$statement = trim( str_replace( $search, $replace, $fullMatch ) );
					}

					// Normalise import statement to be without url() syntax:
					//   This is so relative urls can easily be targeted later
					$statement = self::normalizeImportStatement( $statement );

					if ( $fullMatch !== $statement ) {
						$replacements[ $fullMatch ] = $statement;
					}
				}

				// If we've stored any altered @import strings then we need to apply them
				if ( $replacements ) {
					$import->content = str_replace(
						array_keys( $replacements ),
						array_values( $replacements ),
						$import->content );
				}

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
		$process = csscrush::$process;

		if ( ! file_exists ( $config->docRoot . $url ) ) {
			return false;
		}
		// Move upwards '..' by the number of slashes in baseURL to get a relative path
		$url = str_repeat( '../', substr_count( $process->inputDirUrl, '/' ) ) . substr( $url, 1 );

		return $url;
	}


	protected static function rewriteImportRelativeUrls ( $import ) {

		$stream = $import->content;

		// We're comparing file system position so we'll
		$hostDir = csscrush_util::normalizeSystemPath( $import->hostDir, true );
		$importDir = csscrush_util::normalizeSystemPath( dirname( $import->path ), true );

		csscrush::$storage->misc->relativeUrlPrefix = '';
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
		csscrush::$storage->misc->relativeUrlPrefix = $url_prefix;

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

		$regex = csscrush_regex::$patt;
		$storage = csscrush::$storage;

		// The relative url prefix
		$relative_url_prefix = $storage->tmp->relativeUrlPrefix;

		list( $fullMatch, $before, $function, $url ) = $match;
		$url = trim( $url );

		// If the url is a string token we'll need to restore it as a string token later
		if ( $url_is_token = preg_match( $regex->stringToken, $url ) ) {

			$url_token = new csscrush_string( $url );
			$url = $url_token->value;
		}

		// No rewrite if:
		//   $url begins with a variable, e.g '$('
		//   $url path is absolute or begins with slash
		//   $url is an empty string
		if (
			empty( $url ) ||
			strpos( $url, '/' ) === 0 ||
			strpos( $url, '$(' ) === 0 ||
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

