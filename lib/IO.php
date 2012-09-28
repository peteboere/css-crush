<?php
/**
 *
 * Interface for writing files, retrieving files and checking caches
 *
 */

class csscrush_io {


	// Any setup that needs to be done
	public static function init () {

		$process = csscrush::$process;

		$process->cacheFileName = '.csscrush';
		$process->cacheFilePath = "$process->inputDir/$process->cacheFileName";
	}


	public static function getInput ( $file = false ) {

		// May return a hostfile object associated with a real file
		// Alternatively it may return a hostfile object with string input

		$process = csscrush::$process;

		// Make basic information about the input object accessible
		$input = (object) array();
		$input->dir = ! empty( $process->inputDir ) ? $process->inputDir : null;
		$input->name = $file ? basename( $file ) : null;
		$input->path = $file ? "$process->inputDir/$input->name" : null;

		if ( $file ) {

			if ( ! file_exists( $input->path ) ) {

				// On failure return false
				$error = "Input file '$input->name' not found.";
				csscrush::logError( $error );
				trigger_error( __METHOD__ . ": $error\n", E_USER_WARNING );
				return false;
			}
			else {
				// Capture the modified time
				$input->mtime = filemtime( $input->path );
			}
		}
		return $input;
	}


	public static function getOutputDir () {
		return csscrush::$process->inputDir;
	}


	public static function testOutputDir ( $write_test = true ) {

		$output_dir = csscrush::$process->outputDir;
		$pathtest = true;
		$error = false;

		if ( ! file_exists( $output_dir ) ) {

			$error = "Output directory '$output_dir' doesn't exist.";
			$pathtest = false;
		}
		else if ( $write_test && ! is_writable( $output_dir ) ) {

			csscrush::log( 'Attempting to change permissions' );

			if ( ! @chmod( $output_dir, 0755 ) ) {

				$error = "Output directory '$output_dir' is unwritable.";
				$pathtest = false;
			}
			else {
				csscrush::log( 'Permissions updated' );
			}
		}

		if ( $error ) {
			csscrush::logError( $error );
			trigger_error( __METHOD__ . ": $error\n", E_USER_WARNING );
		}

		return $pathtest;
	}


	public static function getOutputFileName () {

		$process = csscrush::$process;
		$options = $process->options;
		$input = $process->input;

		$output_basename = basename( $input->name, '.css' );

		if ( ! empty( $options->output_file ) ) {
			$output_basename = basename( $options->output_file, '.css' );
		}

		return "$output_basename.crush.css";
	}


	public static function validateExistingOutput () {

		$process = csscrush::$process;
		$options = $process->options;
		$config = csscrush::$config;
		$input = $process->input;

		// Search base directory for an existing compiled file
		foreach ( scandir( $process->outputDir ) as $filename ) {

			if ( $process->outputFileName != $filename ) {
				continue;
			}
			// Cached file exists
			csscrush::log( 'Cached file exists.' );

			$existingfile = (object) array();
			$existingfile->name = $filename;
			$existingfile->path = "$process->outputDir/$existingfile->name";
			$existingfile->URL = "$process->outputDirUrl/$existingfile->name";

			// Start off with the input file then add imported files
			$all_files = array( $input->mtime );

			if ( file_exists( $existingfile->path ) && isset( $process->cacheData[ $process->outputFileName ] ) ) {

				// File exists and has config
				csscrush::log( 'Cached file is registered.' );

				foreach ( $process->cacheData[ $existingfile->name ][ 'imports' ] as $import_file ) {

					// Check if this is docroot relative or input dir relative
					$root = strpos( $import_file, '/' ) === 0 ? $config->docRoot : $process->inputDir;
					$import_filepath = realpath( $root ) . "/$import_file";

					if ( file_exists( $import_filepath ) ) {
						$all_files[] = filemtime( $import_filepath );
					}
					else {
						// File has been moved, remove old file and skip to compile
						csscrush::log( 'Import file has been moved, removing existing file.' );
						unlink( $existingfile->path );
						return false;
					}
				}

				// Cast because the cached options may be a stdClass if an IO adapter has been used.
				$existing_options = (array) $process->cacheData[ $existingfile->name ][ 'options' ];
				$existing_datesum = $process->cacheData[ $existingfile->name ][ 'datem_sum' ];

				$options_unchanged = true;
				foreach ( $existing_options as $key => &$value ) {
					if ( $existing_options[ $key ] !== $options->{ $key } ) {
						$options_unchanged = false;
						break;
					}
				}
				$files_unchanged = $existing_datesum == array_sum( $all_files );

				if ( $options_unchanged && $files_unchanged ) {

					// Files have not been modified and config is the same: return the old file
					csscrush::log( "Files and options have not been modified, returning existing
						 file '$existingfile->URL'." );
					return $existingfile->URL . ( $options->versioning !== false  ? "?$existing_datesum" : '' );
				}
				else {

					// Remove old file and continue making a new one...
					! $options_unchanged && csscrush::log( 'Options have been modified.' );
					! $files_unchanged && csscrush::log( 'Files have been modified.' );
					csscrush::log( 'Removing existing file.' );

					unlink( $existingfile->path );
				}
			}
			else if ( file_exists( $existingfile->path ) ) {
				// File exists but has no config
				csscrush::log( 'File exists but no config, removing existing file.' );
				unlink( $existingfile->path );
			}
			return false;

		} // foreach

		return false;
	}


	public static function clearCache ( $dir ) {

		if ( empty( $dir ) ) {
			$dir = dirname( __FILE__ );
		}
		else if ( ! file_exists( $dir ) ) {
			return;
		}

		$configPath = $dir . '/' . csscrush::$process->cacheFilePath;
		if ( file_exists( $configPath ) ) {
			unlink( $configPath );
		}

		// Remove any compiled files
		$suffix = '.crush.css';
		$suffixLength = strlen( $suffix );

		foreach ( scandir( $dir ) as $file ) {
			if (
				strpos( $file, $suffix ) === strlen( $file ) - $suffixLength
			) {
				unlink( $dir . "/{$file}" );
			}
		}
	}


	public static function getCacheData () {

		$config = csscrush::$config;
		$process = csscrush::$process;

		if (
			file_exists( $process->cacheFilePath ) &&
			$process->cacheData &&
			$process->cacheData[ 'originPath' ] == $process->cacheFilePath
		) {
			// Already loaded and config file exists in the current directory
			return;
		}

		$cache_data_exists = file_exists( $process->cacheFilePath );
		$cache_data_file_is_writable = $cache_data_exists ? is_writable( $process->cacheFilePath ) : false;
		$cache_data = array();

		if (
			$cache_data_exists &&
			$cache_data_file_is_writable &&
			$cache_data = json_decode( file_get_contents( $process->cacheFilePath ), true )
		) {
			// Successfully loaded config file.
			csscrush::log( 'Cache data loaded.' );
		}
		else {
			// Config file may exist but not be writable (may not be visible in some ftp situations?)
			if ( $cache_data_exists ) {
				if ( ! @unlink( $process->cacheFilePath ) ) {

					$error = "Could not delete config data file.";
					csscrush::logError( $error );
					trigger_error( __METHOD__ . ": $error\n", E_USER_NOTICE );
				}
			}
			else {
				// Create config file.
				csscrush::log( 'Creating cache data file.' );
			}
			file_put_contents( $process->cacheFilePath, json_encode( array() ) );
		}

		return $cache_data;
	}


	public static function saveCacheData () {

		$process = csscrush::$process;

		// Need to store the current path so we can check we're using the right config path later
		$process->cacheData[ 'originPath' ] = $process->cacheFilePath;

		csscrush::log( 'Saving config.' );
		file_put_contents( $process->cacheFilePath, json_encode( $process->cacheData ) );
	}

}


