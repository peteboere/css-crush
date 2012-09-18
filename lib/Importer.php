<?php
/**
 *
 * Recursive file importing
 *
 */

class csscrush_importer {


	public static function save ( $data ) {

		$process = csscrush::$process;
		$options = $process->options;

		// No saving if caching is disabled, return early
		if ( ! $options->cache ) {
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
		$options = $process->options;
		$regex = csscrush_regex::$patt;
		$hostfile = $process->input;

		// Keep track of all import file info for later logging
		$mtimes = array();
		$filenames = array();

		$stream = '';
		$prepend_file_contents = '';

		// The prepend file.
		if ( $prepend_file = csscrush_util::find( 'Prepend-local.css', 'Prepend.css' ) ) {
			$prepend_file_contents = file_get_contents( $prepend_file );
			$process->currentFile = 'file://' . $prepend_file;
			// If there's a parsing error inside the prepend file, wipe $prepend_file_contents.
			if ( ! csscrush::prepareStream( $prepend_file_contents ) ) {
				$prepend_file_contents = '';
			}
		}

		// Resolve main input: string or file.
		if ( isset( $hostfile->string ) ) {
			$stream .= $hostfile->string;
			$process->currentFile = 'inline-css';
		}
		else {
			$stream .= file_get_contents( $hostfile->path );
			$process->currentFile = 'file://' . $hostfile->path;
		}

		// If there's a parsing error go no further.
		if ( ! csscrush::prepareStream( $stream ) ) {
			return $stream;
		}

		// Not forgetting to prepend the prepend file contents.
		$stream = $prepend_file_contents . $stream;

		// If rewriting URLs as absolute we need to do some extra work
		if ( $options->rewrite_import_urls === 'absolute' ) {

			// Normalize the @import statements in this case
			foreach ( csscrush_regex::matchAll( $regex->import, $stream ) as $match ) {
				$full_match = $match[0][0];
				$normalized_import_statement = self::normalizeImportStatement( $full_match );
				$stream = str_replace( $full_match, $normalized_import_statement, $stream );
			}

			// Convert URLs to URL tokens by setting an empty prefix
			csscrush::$storage->misc->rewriteUrlPrefix = '';
			$stream = self::rewriteUrls( $stream );
		}

		// This may be set non-zero during the script if an absolute @import URL is encountered
		$search_offset = 0;

		// Recurses until the nesting heirarchy is flattened and all files are combined
		while ( preg_match( $regex->import, $stream, $match, PREG_OFFSET_CAPTURE, $search_offset ) ) {

			$full_match     = $match[0][0]; // Full match
			$match_start    = $match[0][1]; // Full match offset
			$match_end      = $match_start + strlen( $full_match );
			$pre_statement  = substr( $stream, 0, $match_start );
			$post_statement = substr( $stream, $match_end );

			// If just stripping the import statements
			if ( isset( $hostfile->importIgnore ) ) {
				$stream = $pre_statement . $post_statement;
				continue;
			}

			// The media context (if specified) at position 3 in the match
			$media_context = trim( $match[3][0] );

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

				$search_offset = $match_end;
				continue;
			}

			// Create import object
			$import = (object) array();
			$import->url = $url;
			$import->mediaContext = $media_context;
			$import->hostDir = $process->inputDir;

			// Check to see if the url is root relative
			// Flatten import path for convenience
			if ( strpos( $import->url, '/' ) === 0 ) {
				$import->path = realpath( $config->docRoot . $import->url );
			}
			else {
				$import->path = realpath( "$hostfile->dir/$import->url" );
			}

			// Get the import contents, if unsuccessful just continue with the import line removed
			if ( ! ( $import->content = @file_get_contents( $import->path ) ) ) {

				csscrush::log( "Import file '$import->url' not found" );
				$stream = $pre_statement . $post_statement;
				continue;
			}

			// Import file opened successfully so we process it:
			//   - We need to resolve import statement urls in all imported files since
			//     they will be brought inline with the hostfile
			$process->currentFile = 'file://' . $import->path;

			// If there are unmatched brackets inside the import, strip it.
			if ( ! csscrush::prepareStream( $import->content ) ) {
				$stream = $pre_statement . $post_statement;
				continue;
			}

			$import->dir = dirname( $import->url );

			// Store import file info for cache validation
			$mtimes[] = filemtime( $import->path );
			$filenames[] = $import->url;


			// Alter all the url strings to be paths relative to the hostfile:
			//   - Match all @import statements in the import content
			//   - Store the replacements we might find
			$replacements = array();

			foreach ( csscrush_regex::matchAll( $regex->import, $import->content ) as $match ) {

				$full_match = $match[0][0];

				// Url match may be at one of 2 positions
				$url_match = $match[1][1] == -1 ? $match[2][0] : $match[1][0];

				// Url may be a string token
				if ( $url_match_token = preg_match( $regex->stringToken, $url_match ) ) {

					// Store the token
					$url_match_token = new csscrush_string( $url_match );

					// Set $url_match to the actual value
					$url_match = $url_match_token->value;
				}

				// Search and replace on the statement url
				$search = $url_match;
				$replace = "$import->dir/$url_match";

				// Try to resolve absolute paths
				// On failure strip the @import statement
				if ( strpos( $url_match, '/' ) === 0 ) {
					$replace = self::resolveAbsolutePath( $url_match );
					if ( ! $replace ) {
						$search = $full_match;
						$replace = '';
					}
				}

				// The full revised statement for replacement
				$statement = $full_match;

				if ( $url_match_token && ! empty( $replace ) ) {

					// Alter the stored token on internal hash table
					$url_match_token->update( $replace );
				}
				else {

					// Trim the statement and set the resolved path
					$statement = trim( str_replace( $search, $replace, $full_match ) );
				}

				// Normalise import statement to be without url() syntax:
				//  - So relative urls can be targeted later
				$statement = self::normalizeImportStatement( $statement );

				if ( $full_match !== $statement ) {
					$replacements[ $full_match ] = $statement;
				}
			}

			// If we've stored any altered @import strings then we need to apply them
			if ( $replacements ) {
				$import->content = csscrush_util::strReplaceHash( $import->content, $replacements );
			}

			// Optionally rewrite relative url and custom function data-uri references
			if ( $options->rewrite_import_urls ) {
				$import->content = self::rewriteImportUrls( $import );
			}

			// Add media context if it exists
			if ( $import->mediaContext ) {
				$import->content = "@media $import->mediaContext {{$import->content}}";
			}

			$stream = $pre_statement . $import->content . $post_statement;

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


	protected static function normalizeImportStatement ( $import_statement ) {

		if ( preg_match( '!(\s)url\(\s*([^\)]+)\)!', $import_statement, $m ) ) {

			list( $full_match, $the_space, $the_url ) = $m;
			$the_url = rtrim( $the_url );

			if ( preg_match( csscrush_regex::$patt->stringToken, $the_url ) ) {

				//  @import url( ___s34___ ) screen and ( max-width: 500px );
				//  @import url( ___s34___ );
				$import_statement = str_replace( $full_match, $the_space . $the_url, $import_statement );
			}
			else {

				//  @import url( some/path/styles.css );
				$string_label = csscrush::tokenLabelCreate( 's' );
				csscrush::$storage->tokens->strings[ $string_label ] = '"' . $the_url . '"';
				$import_statement = str_replace( $full_match, $the_space . $string_label, $import_statement );
			}
		}

		return $import_statement;
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


	protected static function rewriteImportUrls ( $import ) {

		$stream = $import->content;

		// Normalise the paths
		$host_dir = csscrush_util::normalizePath( $import->hostDir, true );
		$import_dir = csscrush_util::normalizePath( dirname( $import->path ), true );

		csscrush::$storage->misc->rewriteUrlPrefix = '';
		$url_prefix = '';

		if ( $import_dir === $host_dir ) {

			// Do nothing if files are in the same directory
			return $stream;

		}
		elseif ( strpos( $import_dir, $host_dir ) === false ) {

			// Import directory is higher than the host directory
			// Split the directory paths into arrays so we can compare segment by segment
			$host_segs = preg_split( '!/+!', $host_dir, null, PREG_SPLIT_NO_EMPTY );
			$import_segs = preg_split( '!/+!', $import_dir, null, PREG_SPLIT_NO_EMPTY );

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
			$url_prefix = substr( $import_dir, strlen( $host_dir ) + 1 );
		}

		if ( empty( $url_prefix ) ) {
			return $stream;
		}

		// Add the directory seperator ending (if needed)
		if ( substr( $url_prefix, -1 ) !== '/' ) {
			$url_prefix .= '/';
		}

		// Make $url_prefix accessible in callback scope
		csscrush::$storage->misc->rewriteUrlPrefix = $url_prefix;

		// Search for all relative url and data-uri references in the content
		// and prepend $relative_url_prefix
		return self::rewriteUrls( $stream );
	}


	protected static function rewriteUrls ( $stream ) {

		$url_function_patt = '!
			([^a-z-])          # the preceeding character
			(data-uri|url)     # the function name
			\(\s*([^\)]+)\s*\) # the url
		!xi';
		$stream = preg_replace_callback( $url_function_patt,
					array( 'self', 'cb_rewriteUrls' ), $stream );

		return $stream;
	}


	protected static function cb_rewriteUrls ( $match ) {

		$regex = csscrush_regex::$patt;
		$storage = csscrush::$storage;

		// The relative url prefix
		$relative_url_prefix = $storage->misc->rewriteUrlPrefix;

		list( $full_match, $before, $function, $url ) = $match;
		$url = trim( $url );

		// If the url is a string token we'll need to restore it as a string token later
		if ( $url_is_string = preg_match( $regex->stringToken, $url ) ) {

			$url_string = new csscrush_string( $url );
			$url = $url_string->value;
		}

		// Normalise the path
		$url = csscrush_util::normalizePath( $url );

		// No rewrite if:
		//   - $url is an empty string
		//   - $url path is absolute or begins with slash
		//   - $url begins with a variable, e.g '$('
		//   - $url is a data uri
		if (
			$url === '' ||
			strpos( $url, '/' ) === 0 ||
			strpos( $url, '$(' ) === 0 ||
			strpos( $url, 'data:' ) === 0 ||
			preg_match( $regex->absoluteUrl, $url )
		) {

			// Token or not, it's ok to return the full match
			// if $url is a root relative or absolute ref
			return $full_match;
		}

		// Prepend the relative url prefix
		$url = $relative_url_prefix . $url;

		// If the path comes via the css url function convert it to a URL token
		if ( $function == 'url' ) {

			$label = csscrush::tokenLabelCreate( 'u' );
			csscrush::$storage->tokens->urls[ $label ] = $url;
			$url = $label;
		}
		elseif ( $url_is_string ) {

			// Restore quotes if $url was a string token, update the token and return it
			$url_string->update( $url_string->quoteMark . $url . $url_string->quoteMark );
			$url = $url_string->token;
		}

		// Reconstruct the match and return
		return "$before$function($url)";
	}
}

