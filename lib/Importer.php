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
		$process->cacheData[ $process->output->filename ] = $data;

		// Save config changes
		csscrush::io_call( 'saveCacheData' );
	}


	public static function hostfile () {

		$config = csscrush::$config;
		$process = csscrush::$process;
		$options = $process->options;
		$regex = csscrush_regex::$patt;
		$hostfile = $process->input;

		// Keep track of all import file info for cache data.
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

		// Resolve main input; Is it a bare string or a file.
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

		// Prepend any prepend file contents here.
		$stream = $prepend_file_contents . $stream;

		// This may be set non-zero during the script if an absolute @import URL is encountered.
		$search_offset = 0;

		// Recurses until the nesting heirarchy is flattened and all import files are inlined.
		while ( preg_match( $regex->import, $stream, $match, PREG_OFFSET_CAPTURE, $search_offset ) ) {

			$match_len = strlen( $match[0][0] );
			$match_start = $match[0][1];
			$match_end = $match_start + $match_len;

			// If just stripping the import statements
			if ( isset( $hostfile->importIgnore ) ) {
				$stream = substr_replace( $stream, '', $match_start, $match_len );
				continue;
			}

			// Fetch the URL object.
			$url = csscrush_url::get( $match[1][0] );

			// Pass over protocoled import urls.
			if ( $url->protocol ) {
				$search_offset = $match_end;
				continue;
			}

			// The media context (if specified).
			$media_context = trim( $match[2][0] );

			// Create import object.
			$import = (object) array();
			$import->url = $url;
			$import->mediaContext = $media_context;

			// Resolve import realpath.
			if ( $url->isRooted ) {
				$import->path = realpath( $config->docRoot . $import->url->value );
			}
			else {
				$import->path = realpath( "$hostfile->dir/{$import->url->value}" );
			}

			// Get the import contents, if unsuccessful just continue with the import line removed.
			if ( ! ( $import->content = @file_get_contents( $import->path ) ) ) {
				csscrush::log( "Import file '{$import->url->value}' not found" );
				$stream = substr_replace( $stream, '', $match_start, $match_len );
				continue;
			}

			// Import file opened successfully so we process it:
			//   - We need to resolve import statement urls in all imported files since
			//     they will be brought inline with the hostfile
			$process->currentFile = 'file://' . $import->path;

			// If there are unmatched brackets inside the import, strip it.
			if ( ! csscrush::prepareStream( $import->content ) ) {
				$stream = substr_replace( $stream, '', $match_start, $match_len );
				continue;
			}

			$import->dir = dirname( $import->url->value );

			// Store import file info for cache validation.
			$mtimes[] = filemtime( $import->path );
			$filenames[] = $import->url->value;

			// Alter all the @import urls to be paths relative to the hostfile.
			foreach ( csscrush_regex::matchAll( $regex->import, $import->content ) as $m ) {

				// Fetch the matched URL.
				$url2 = csscrush_url::get( $m[1][0] );

				// Try to resolve absolute paths.
				// On failure strip the @import statement.
				if ( $url2->isRooted ) {
					$url2->resolveRootedPath();
				}
				else {
					$url2->prepend( "$import->dir/" );
				}
			}

			// Optionally rewrite relative url and custom function data-uri references.
			if ( $options->rewrite_import_urls ) {
				self::rewriteImportedUrls( $import );
			}

			// Add media context if it exists
			if ( $import->mediaContext ) {
				$import->content = "@media $import->mediaContext {{$import->content}}";
			}

			$stream = substr_replace( $stream, $import->content, $match_start, $match_len );
		}

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


	protected static function rewriteImportedUrls ( $import ) {

		$link = csscrush_util::getLinkBetweenDirs(
			csscrush::$process->input->dir, dirname( $import->path ) );

		if ( empty( $link ) ) {
			return;
		}

		// Match all urls that are not imports.
		preg_match_all( '#(?<!@import )\?u\d+\?#iS', $import->content, $matches );

		foreach ( $matches[0] as $token ) {

			// Fetch the matched URL.
			$url = csscrush_url::get( $token );

			if ( $url->isRelative ) {
				// Prepend the relative url prefix.
				$url->prepend( $link );
			}
		}
	}
}
