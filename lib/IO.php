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
		$input = new stdclass();
		$input->name = $file ? basename( $file ) : null;
		$input->dir = $file ? $process->inputDir : null;
		$input->path = $file ? "$process->inputDir/$input->name" : null;

		if ( $file ) {

			if ( ! file_exists( $input->path ) ) {
				// On failure return false with a message
				trigger_error( __METHOD__ . ": File '$input->name' not found.\n", E_USER_WARNING );
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

		if ( ! file_exists( $output_dir ) ) {
			trigger_error( __METHOD__ . ": directory '$output_dir' doesn't exist.\n", E_USER_WARNING );
			$pathtest = false;
		}
		else if ( $write_test and ! is_writable( $output_dir ) ) {
			csscrush::log( 'Attempting to change permissions' );
			if ( ! @chmod( $output_dir, 0755 ) ) {
				trigger_error( __METHOD__ . ": directory '$output_dir' is unwritable.\n", E_USER_WARNING );
				csscrush::log( 'Unable to update permissions' );
				$pathtest = false;
			}
			else {
				csscrush::log( 'Permissions updated' );
			}
		}
		return $pathtest;
	}


	public static function getOutputFileName () {

		$process = csscrush::$process;
		$options = csscrush::$options;
		$input = $process->input;

		$output_basename = basename( $input->name, '.css' );

		if ( ! empty( $options[ 'output_file' ] ) ) {
			$output_basename = basename( $options[ 'output_file' ], '.css' );
		}

		return "$output_basename.crush.css";
	}


	public static function validateExistingOutput () {

		$process = csscrush::$process;
		$config = csscrush::$config;
		$input = $process->input;

		// Search base directory for an existing compiled file
		foreach ( scandir( $process->outputDir ) as $filename ) {

			if ( $process->outputFileName != $filename ) {
				continue;
			}
			// Cached file exists
			csscrush::log( 'Cached file exists' );

			$existingfile = new stdclass();
			$existingfile->name = $filename;
			$existingfile->path = "$process->outputDir/$existingfile->name";
			$existingfile->URL = "$process->outputDirUrl/$existingfile->name";

			// Start off with the input file then add imported files
			$all_files = array( $input->mtime );

			if ( file_exists( $existingfile->path ) and isset( $process->cacheData[ $process->outputFileName ] ) ) {

				// File exists and has config
				csscrush::log( 'has config' );

				foreach ( $process->cacheData[ $existingfile->name ][ 'imports' ] as $import_file ) {

					// Check if this is docroot relative or input dir relative
					$root = strpos( $import_file, '/' ) === 0 ? $config->docRoot : $process->inputDir;
					$import_filepath = realpath( $root ) . "/$import_file";

					if ( file_exists( $import_filepath ) ) {
						$all_files[] = filemtime( $import_filepath );
					}
					else {
						// File has been moved, remove old file and skip to compile
						csscrush::log( 'Import file has been moved, removing existing file' );
						unlink( $existingfile->path );
						return false;
					}
				}

				$existing_options = $process->cacheData[ $existingfile->name ][ 'options' ];
				$existing_datesum = $process->cacheData[ $existingfile->name ][ 'datem_sum' ];

				$options_unchanged = $existing_options == csscrush::$options;
				$files_unchanged = $existing_datesum == array_sum( $all_files );
				
				if ( $options_unchanged and $files_unchanged ) {

					// Files have not been modified and config is the same: return the old file
					csscrush::log( "Files and options have not been modified, returning existing
						 file '$existingfile->URL'" );
					return $existingfile->URL . ( csscrush::$options[ 'versioning' ] !== false  ? "?$existing_datesum" : '' );
				}
				else {
					// Remove old file and continue making a new one...
					! $options_unchanged && csscrush::log( 'Options have been modified' );
					! $files_unchanged && csscrush::log( 'Files have been modified' );
					csscrush::log( 'Removing existing file' );
					
					unlink( $existingfile->path );
				}
			}
			else if ( file_exists( $existingfile->path ) ) {
				// File exists but has no config
				csscrush::log( 'File exists but no config, removing existing file' );
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
			file_exists( $process->cacheFilePath ) and
			$process->cacheData  and
			$process->cacheData[ 'originPath' ] == $process->cacheFilePath
		) {
			// Already loaded and config file exists in the current directory
			return;
		}

		$cache_data_exists = file_exists( $process->cacheFilePath );
		$cache_data_file_is_writable = $cache_data_exists ? is_writable( $process->cacheFilePath ) : false;

		$cache_data = array();

		if ( $cache_data_exists and $cache_data_file_is_writable ) {
			// Load from file
			$cache_data = unserialize( file_get_contents( $process->cacheFilePath ) );
		}
		else {
			// Config file may exist but not be writable (may not be visible in some ftp situations?)
			if ( $cache_data_exists ) {
				if ( ! @unlink( $process->cacheFilePath ) ) {
					trigger_error( __METHOD__ . ": Could not delete config data file.\n", E_USER_NOTICE );
				}
			}
			// Create
			csscrush::log( 'Creating cache data file' );
			file_put_contents( $process->cacheFilePath, serialize( array() ) );
		}

		return $cache_data;
	}


	public static function saveCacheData () {

		$process = csscrush::$process;
		
		// Need to store the current path so we can check we're using the right config path later
		$process->cacheData[ 'originPath' ] = $process->cacheFilePath;
		
		csscrush::log( 'Saving config' );
		file_put_contents( $process->cacheFilePath, serialize( $process->cacheData ) );
	}

}


