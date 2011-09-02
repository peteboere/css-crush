<?php
/**
 *
 * CSS Crush
 *
 * MIT License (http://www.opensource.org/licenses/mit-license.php)
 * Copyright 2010-2011 Pete Boere
 *
 * Example use:
 *
 * <?php
 * 
 * require_once 'CssCrush.php';
 * $global_css = CssCrush::file( '/css/global.css' );
 * 
 * ?>
 *
 * <link rel="stylesheet" href="<?php echo $global_css; ?>" />
 *
 */
class CssCrush {

	// Path information, global settings
	public static $config;

	// The path of this script
	public static $location;

	// Aliases from the aliases file
	public static $aliases = array();

	// Macro function names
	public static $macros = array();

	public static $COMPILE_SUFFIX = '.crush.css';

	// Global variable storage
	protected static $globalVars = array();

	protected static $assetsLoaded = false;

	// Properties available to each 'file' process
	public static $storage;
	protected static $options;
	protected static $compileName;
	protected static $tokenUID;

	// Regular expressions
	public static $regex = array(
		'import'      => '!
			@import\s+    # import at-rule
			(?:url)?\s*\(?\s*[\'"]?([^\'"\);]+)[\'"]?\s*\)?  # url or quoted string
			\s*([^;]*);?  # media argument
		!x',
		'variables'   => '!@variables\s*([^\{]*)\{\s*(.*?)\s*\};?!s',
		'atRule'      => '!@([-a-z_]+)\s*([^\{]*)\{\s*(.*?)\s*\};?!s',
		'comment'     => '!/\*(.*?)\*/!s',
		'string'      => '!(\'|"|`)(?:\\1|[^\1])*?\1!',
		// As an exception we treat @font-face and @page rules like standard rules
		'rule'        => '!
			(\n(?:[^@{}]+|@(?:font-face|page)[^{]*)) # The selector
			\{([^{}]*)\}  # The declaration block
		!x',
		'token'       => array(
			'comment' => '!___c\d+___!',
			'string'  => '!___s\d+___!',
			'rule'    => '!___r\d+___!',
			'paren'   => '!___p\d+___!',
		),
		'function'    => array(
			'var'     => '!(?:
				([^a-z0-9_-])
				var\(\s*([a-z0-9_-]+)\s*\)
				|
				\{?\$([a-z0-9_-]+)\}? # Dollar syntax, optional curly braces
			)!ix',
			'custom'  => '!(^|[^a-z0-9_-])(math|floor|round|ceil|percent|pc|data-uri|-)?(___p\d+___)!i',
			'match'   => '!(^|[^a-z0-9_-])([a-z_-]+)(___p\d+___)!i',
		),
		'vendorPrefix' => '!^-([a-z]+)-([a-z-]+)!',
	);

	// Init called once manually post class definition
	public static function init () {

		self::$location = dirname( __FILE__ );

		self::$config = $config = new stdClass;
		$config->file = '.' . __CLASS__;
		$config->data = null;
		$config->path = null;
		$config->baseDir = null;
		$config->baseURL = null;

		// Get normalized document root reference: no symlink, forward slashes, no trailing slashes
		$docRoot = null;
		if ( isset( $_SERVER[ 'DOCUMENT_ROOT' ] ) ) {
			$docRoot = realpath( $_SERVER[ 'DOCUMENT_ROOT' ] );
		}
		else {
			// Probably IIS
			$scriptname = $_SERVER[ 'SCRIPT_NAME' ];
			$fullpath = realpath( basename( $scriptname ) );
			$docRoot = substr( $fullpath, 0, stripos( $fullpath, $scriptname ) );
		}
		$config->docRoot = rtrim( str_replace( '\\', '/', $docRoot ), '/' );

		// Casting to objects for ease of use
		self::$regex = (object) self::$regex;
		self::$regex->token = (object) self::$regex->token;
		self::$regex->function = (object) self::$regex->function;
	}

	// Aliases and macros loader
	protected static function loadAssets () {

		// Load aliases file if it exists
		$aliases_file = self::$location . '/' . __CLASS__ . '.aliases';
		if ( file_exists( $aliases_file ) ) {
			if ( $result = parse_ini_file( $aliases_file, true ) ) {
				self::$aliases = $result;

				// Value aliases require a little preprocessing
				if ( isset( self::$aliases[ 'values' ] ) ) {
					$store = array();
					foreach ( self::$aliases[ 'values' ] as $prop_val => $aliases ) {
						list( $prop, $value ) = array_map( 'trim', explode( ':', $prop_val ) );
						$store[ $prop ][ $value ] = $aliases;
					}
					self::$aliases[ 'values' ] = $store;
					self::log( $store );
				}
			}
			else {
				trigger_error( __METHOD__ . ": Aliases file was not parsed correctly (syntax error).\n", E_USER_NOTICE );
			}
		}
		else {
			trigger_error( __METHOD__ . ": Aliases file not found.\n", E_USER_NOTICE );
		}

		// Load macros file if it exists
		$macros_file = self::$location . '/' . __CLASS__ . '.macros.php';
		if ( file_exists( $macros_file ) ) {
			require_once $macros_file;
		}
	}

	// Initialize config data, create config file if needed
	protected static function loadConfig () {
		$config = self::$config;
		if (
			file_exists( $config->path ) and
			$config->data  and
			$config->data[ 'originPath' ] == $config->path
		) {
			// Already loaded and config file exists in the current directory
			return;
		}
		else if ( file_exists( $config->path ) ) {
			// Load from file
			$config->data = unserialize( file_get_contents( $config->path ) );
		}
		else {
			// Create
			self::log( 'Creating config file' );
			file_put_contents( $config->path, serialize( array() ) );
			$config->data = array();
		}
	}

	// Establish the host file directory and ensure it's writable
	protected static function setPath ( $new_dir ) {
		$config = self::$config;
		$docRoot = $config->docRoot;
		if ( strpos( $new_dir, $docRoot ) !== 0 ) {
			// Not a system path
			$new_dir = realpath( "$docRoot/$new_dir" );
		}

		$pathtest = true;
		if ( !file_exists( $new_dir ) ) {
			trigger_error( __METHOD__ . ": directory '$new_dir' doesn't exist.\n", E_USER_WARNING );
			$pathtest = false;
		}
		else if ( !is_writable( $new_dir ) ) {
			self::log( 'Attempting to change permissions' );
			if ( !chmod( $new_dir, 0777 ) ) {
				trigger_error( __METHOD__ . ": directory '$new_dir' is unwritable.\n", E_USER_WARNING );
				self::log( 'Unable to update permissions' );
				$pathtest = false;
			}
			else {
				self::log( 'Permissions updated' );
			}
		}

		$config->path = "$new_dir/$config->file";
		$config->baseDir = $new_dir;
		$config->baseURL = substr( $new_dir, strlen( $docRoot ) );
		return $pathtest;
	}


	#############
	#  Public API

	/**
	 * Process host CSS file and return a new compiled file
	 *
	 * @param string $file  Absolute or relative path to the host CSS file
	 * @param mixed $options  An array of options or null
	 * @return string  The public path to the compiled file or an empty string
	 */
	public static function file ( $file, $options = null ) {

		$config = self::$config;

		// Reset properties for current process
		self::$tokenUID = 0;
		self::$storage = new stdClass;
		self::$storage->tokens = (object) array(
			'strings'  => array(),
			'comments' => array(),
			'rules'    => array(),
			'parens'   => array(),
		);
		self::$storage->variables = array();

		// Since we're comparing strings, we need to iron out OS differences
		$file = str_replace( '\\', '/', $file );
		$docRoot = $config->docRoot;

		$pathtest = true;
		if ( strpos( $file, $docRoot ) === 0 ) {
			// System path
			$pathtest = self::setPath( dirname( $file ) );
		}
		else if ( strpos( $file, '/' ) === 0 ) {
			// WWW root path
			$pathtest = self::setPath( dirname( $docRoot . $file ) );
		}
		else {
			// Relative path
			$pathtest = self::setPath( dirname( dirname( __FILE__ ) . '/' . $file ) );
		}

		if ( !$pathtest ) {
			// Main directory not found or is not writable
			// Return an empty string
			return '';
		}

		self::loadConfig();

		// Make basic information about the hostfile accessible
		$hostfile = new stdClass;
		$hostfile->name = basename( $file );
		$hostfile->path = "$config->baseDir/$hostfile->name";

		if ( !file_exists( $hostfile->path ) ) {
			// If host file is not found return an empty string
			trigger_error( __METHOD__ . ": File '$hostfile->name' not found.\n", E_USER_WARNING );
			return '';
		}
		else {
			// Capture the modified time
			$hostfile->mtime = filemtime( $hostfile->path );
		}

		self::parseOptions( $options );

		// Compiled filename we're searching for
		self::$compileName = basename( $hostfile->name, '.css' ) . self::$COMPILE_SUFFIX;

		// Check for a valid compiled file
		$validCompliledFile = self::validateCache( $hostfile );
		if ( is_string( $validCompliledFile ) ) {
			return $validCompliledFile;
		}

		// Load in aliases and macros
		if ( !self::$assetsLoaded ) {
			self::loadAssets();
			self::$assetsLoaded = true;
		}
		// Compile
		$output = self::compile( $hostfile );

		// Add in boilerplate
		if ( self::$options[ 'boilerplate' ] ) {
			$output = self::getBoilerplate() . "\n$output";
		}

		// Create file and return path. Return empty string on failure
		if ( file_put_contents( "$config->baseDir/" . self::$compileName, $output ) ) {
			return "$config->baseURL/" . self::$compileName . ( self::$options[ 'versioning' ] ? '?' . time() : '' );
		}
		else {
			return '';
		}
	}

	/**
	 * Process host CSS file and return an HTML link tag with populated href
	 *
	 * @param string $file  Absolute or relative path to the host CSS file
	 * @param mixed $options  An array of options or null
	 * @return string  HTML link tag or error message inside HTML comment
	 */
	public static function tag ( $file, $options = null, $attributes = array() ) {
		$file = self::file( $file, $options );
		if ( !empty( $file ) ) {
			// On success return the tag with any custom attributes
			$attr_string = '';
			foreach ( $attributes as $name => $value ) {
				$value = htmlspecialchars( $value, ENT_COMPAT, 'UTF-8', false );
				$attr_string .= " $name=\"$value\"";
			}
			return "<link rel=\"stylesheet\" href=\"$file\"$attr_string />\n";
		}
		else {
			// Return an HTML comment with message on failure
			$class = __CLASS__;
			return "<!-- $class: File $file not found -->\n";
		}
	}

	/**
	 * Add variables globally
	 *
	 * @param mixed  Assoc array of variable names and values, a php ini filename or null
	 */
	public static function globalVars ( $vars ) {
		// Merge into the stack, overrides existing variables of the same name
		if ( is_array( $vars ) ) {
			self::$globalVars = array_merge( self::$globalVars, $vars );
		}
		// Is it a file? If yes attempt to parse it
		elseif ( is_string( $vars ) and file_exists( $vars ) ) {
			if ( $result = parse_ini_file( $vars ) ) {
				self::$globalVars = array_merge( self::$globalVars, $result );
			}
		}
		// Clear the stack if the argument is explicitly null
		elseif ( is_null( $vars ) ) {
			self::$globalVars = array();
		}
	}

	/**
	 * Clear config file and compiled files for the specified directory
	 *
	 * @param string  System path to the directory
	 */
	public static function clearCache ( $dir = '' ) {
		if ( empty( $dir ) ) {
			$dir = dirname( __FILE__ );
		}
		else if ( !file_exists( $dir ) ) {
			return;
		}
		$configPath = $dir . '/' . self::$config->file;
		if ( file_exists( $configPath ) ) {
			unlink( $configPath );
		}
		// Remove any compiled files
		$suffix = self::$COMPILE_SUFFIX;
		$suffixLength = strlen( $suffix );
		foreach ( scandir( $dir ) as $file ) {
			if (
				strpos( $file, $suffix ) === strlen( $file ) - $suffixLength
			) {
				unlink( $dir . "/{$file}" );
			}
		}
	}

	/**
	 * Flag for enabling logging
	 *
	 * @var boolean
	 */
	public static $logging = false;

	/**
	 * Print the log
	 */
	public static function log () {
		if ( !self::$logging ) {
			return;
		}
		static $log = '';
		$args = func_get_args();
		if ( !count( $args ) ) {
			// No arguments, return the log
			return $log;
		}
		else {
			$arg = $args[0];
		}
		if ( is_string( $arg ) ) {
			$log .= $arg . '<hr>';
		}
		else {
			$out = '<pre>';
			ob_start();
			print_r( $arg );
			$out .= ob_get_clean();
			$out .= '</pre>';
			$log .= $out . '<hr>';
		}
	}

	#####################
	#  Internal functions

	protected static function getBoilerplate () {
		if (
			!( $boilerplate = file_get_contents( self::$location . "/CssCrush.boilerplate" ) ) or
			!self::$options[ 'boilerplate' ]
		) {
			return '';
		}
		// Process any tags, currently only '{{datetime}}' is supported
		if ( preg_match_all( '!\{\{([^}]+)\}\}!', $boilerplate, $boilerplate_matches ) ) {
			$replacements = array();
			foreach ( $boilerplate_matches[0] as $index => $tag ) {
				if ( $boilerplate_matches[1][$index] === 'datetime' ) {
					$replacements[] = date( 'Y-m-d H:i:s O' );
				}
				else {
					$replacements[] = '?';
				}
			}
			$boilerplate = str_replace( $boilerplate_matches[0], $replacements, $boilerplate );
		}
		// Pretty print
		$boilerplate = explode( PHP_EOL, $boilerplate );
		$boilerplate = array_map( 'trim', $boilerplate );
		$boilerplate = array_map( create_function( '$it', 'return !empty($it) ? " $it" : $it;' ), $boilerplate );
		$boilerplate = implode( PHP_EOL . ' *', $boilerplate );
		return <<<TPL
/*
 *$boilerplate
 */
TPL;
	}

	protected static function parseOptions ( $options ) {
		// Create default options for those not set
		$option_defaults = array(
			// Minify. Set true for formatting and comments
			'debug'       => false,
			// Append 'checksum' to output file name
			'versioning'  => true,
			// Use the template boilerplate
			'boilerplate' => true,
			// Variables passed in at runtime
			'vars'        => array(),
			// Keeping track of global vars internally
			'_globalVars' => self::$globalVars,
		);
		
		self::$options = is_array( $options ) ?
			array_merge( $option_defaults, $options ) : $option_defaults;
	}

	protected static function compile ( $hostfile ) {

		$regex = self::$regex;

		// Collate hostfile and imports
		$output = self::collateImports( $hostfile );

		// Extract comments
		$output = preg_replace_callback( $regex->comment, array( 'self', 'cb_extractComments' ), $output );

		// Extract strings
		$output = preg_replace_callback( $regex->string, array( 'self', 'cb_extractStrings' ), $output );

		// Parse variables
		$output = preg_replace_callback( $regex->variables, array( 'self', 'cb_extractVariables' ), $output );

		// Calculate the variable stack:
		//   In-file variables override global variables
		//   Runtime variables override in-file variables
		self::$storage->variables = array_merge(
			self::$globalVars, self::$storage->variables );
		if ( !empty( self::$options[ 'vars' ] ) ) {
			self::$storage->variables = array_merge(
				self::$storage->variables, self::$options[ 'vars' ] );
		}
		self::log( self::$storage->variables );

		// Place variables
		$output = preg_replace_callback( $regex->function->var, array( 'self', 'cb_placeVariables' ), $output );
		
		// Place variables in any string tokens
		foreach ( self::$storage->tokens->strings as $label => &$_string ) {
			if ( strpos( $_string, '$' ) !== false ) {
				$_string = preg_replace_callback( 
					$regex->function->var, array( 'self', 'cb_placeVariables' ), $_string );
			}
		}
		
		// Normalize whitespace
		$output = self::normalize( $output );

		// Measure to ensure we can extract the rules correctly
		$output = "\n" . str_replace( array( '@', '}', '{' ), array( "\n@", "}\n", "{\n" ), $output );

		// Extract rules
		$output = preg_replace_callback( $regex->rule, array( 'self', 'cb_extractRules' ), $output );

		// Alias at-rules (if there are any)
		$output = self::aliasAtRules( $output );

		// print it all back
		$output = self::display( $output );

		//self::log( self::$storage->tokens );

		// Release memory
		self::$storage = null;

		return $output;
	}

	protected static function display ( $output ) {
		$minify = !self::$options[ 'debug' ];
		$regex = self::$regex;

		if ( $minify ) {
			$output = preg_replace( $regex->token->comment, '', $output );
		}
		else {
			// Create newlines after tokens
			$output = preg_replace( '!([{}])!', "$1\n", $output );
			$output = preg_replace( '!([@])!', "\n$1", $output );
			$output = preg_replace( '!(___[a-z0-9]+___)!', "$1\n", $output );

			// Kill double spaces
			$output = ltrim( preg_replace( '!\n+!', "\n", $output ) );
		}

		// Kill leading space
		$output = preg_replace( '!\n\s+!', "\n", $output );

		// Print out rules
		$output = preg_replace_callback( $regex->token->rule, array( 'self', 'cb_printRule' ), $output );

		// Insert parens
		$paren_labels = array_keys( self::$storage->tokens->parens );
		$paren_values = array_values( self::$storage->tokens->parens );
		$output = str_replace( $paren_labels, $paren_values, $output );

		if ( $minify ) {
			$output = self::minify( $output );
		}
		else {
			// Insert comments
			$comment_labels = array_keys( self::$storage->tokens->comments );
			$comment_values = array_values( self::$storage->tokens->comments );
			foreach ( $comment_values as &$comment ) {
				$comment = "$comment\n";
			}
			$output = str_replace( $comment_labels, $comment_values, $output );
			// Normalize line breaks
			$output = preg_replace( '!\n{3,}!', "\n\n", $output );
		}

		// Insert literals
		$string_labels = array_keys( self::$storage->tokens->strings );
		$string_values = array_values( self::$storage->tokens->strings );
		$output = str_replace( $string_labels, $string_values, $output );

		// I think we're done
		return $output;
	}

	protected static function validateCache ( $hostfile ) {
		$config = self::$config;

		// Search base directory for an existing compiled file
		foreach ( scandir( $config->baseDir ) as $filename ) {

			if ( self::$compileName != $filename ) {
				continue;
			}
			// Cached file exists
			self::log( 'Cached file exists' );

			$existingfile = new stdClass;
			$existingfile->name = $filename;
			$existingfile->path = "$config->baseDir/$existingfile->name";
			$existingfile->URL = "$config->baseURL/$existingfile->name";

			// Start off with the host file then add imported files
			$all_files = array( $hostfile->mtime );

			if ( file_exists( $existingfile->path ) and isset( $config->data[ self::$compileName ] ) ) {
				// File exists and has config
				self::log( 'has config' );
				foreach ( $config->data[ $existingfile->name ][ 'imports' ] as $import_file ) {
					// Check if this is docroot relative or hostfile relative
					$root = strpos( $import_file, '/' ) === 0 ? $config->docRoot : $config->baseDir;
					$import_filepath = realpath( $root ) . "/{$import_file}";
					if ( file_exists( $import_filepath ) ) {
						$all_files[] = filemtime( $import_filepath );
					}
					else {
						// File has been moved, remove old file and skip to compile
						self::log( 'Import file has been moved, removing existing file' );
						unlink( $existingfile->path );
						return false;
					}
				}

				$existing_options = $config->data[ $existingfile->name ][ 'options' ];
				$existing_datesum = $config->data[ $existingfile->name ][ 'datem_sum' ];
				if (
						$existing_options == self::$options and
						$existing_datesum == array_sum( $all_files )
				) {
					// Files have not been modified and config is the same: return the old file
					self::log( "Files have not been modified, returning existing
						 file '$existingfile->URL'" );
					return $existingfile->URL .	( self::$options[ 'versioning' ] !== false  ? "?{$existing_datesum}" : '' );
				}
				else {
					// Remove old file and continue making a new one...
					self::log( 'Files have been modified, removing existing file' );
					unlink( $existingfile->path );
				}
			}
			else if ( file_exists( $existingfile->path ) ) {
				// File exists but has no config
				self::log( 'File exists but no config, removing existing file' );
				unlink( $existingfile->path );
			}
			return false;

		} // foreach
		return false;
	}

	protected static function collateImports ( $hostfile ) {

		$config = self::$config;
		$compileName = self::$compileName;
		$regex = self::$regex;

		$str = file_get_contents( $hostfile->path );

		// Obfuscate any import at-rules within comment blocks
		$cb_obfuscateDirectives = array( 'self', 'cb_obfuscateDirectives' );
		$str = preg_replace_callback( $regex->comment, $cb_obfuscateDirectives, $str );

		// Initialize config object
		$config->data[ $compileName ] = array();

		// Keep track of relative paths with nested imports
		$relativeContext = '';
		// Detect whether we're leading from an absolute filepath
		$absoluteFlag = false;
		$imports_mtimes = array();
		$imports_filenames = array();
		$imports_urls = array();
		$import = new stdClass;

		while ( preg_match( $regex->import, $str, $match, PREG_OFFSET_CAPTURE ) ) {
			self::log( $match );

			// Matched a file import statement
			$text = $match[0][0]; // Full match
			$offset = $match[0][1]; // Full match offset
			$import->name = trim( $match[1][0] ); // The url
			$import->mediaContext = trim( $match[2][0] ); // The media context if specified
			$import->isExternalURL = false;

			if ( strpos( $import->name, '/' ) === 0 ) {
				// Absolute path
				self::log( 'Absolute path import' );
				$segments = array( $config->docRoot, $import->name );
				$relativeContext = '';
				$absoluteFlag = true;
			}
			elseif (
				strpos( $import->name, 'http://' ) === 0 or
				strpos( $import->name, 'https://' ) === 0
			) {
				// External URL import
				self::log( 'External URL import' );
				$import->isExternalURL = true;
				$absoluteFlag = false;
			}
			else {
				// Relative path
				self::log( 'Relative path' );
				$root = $absoluteFlag ? $config->docRoot : $config->baseDir;
				$segments = array_filter( array( $root, $relativeContext, $import->name ) );
				if ( $absoluteFlag ) {
					$relativeContext = dirname( substr( $import->path, strlen( $config->baseDir ) + 1 ) );
				}
				$absoluteFlag = false;
			}
			$import->path = !$import->isExternalURL ? realpath( implode( '/', $segments ) ) : $import->name;

			//self::log( 'Relative context: ' .  $relativeContext );
			//self::log( 'Import filepath: ' . $import->path );

			$preStatement  = substr( $str, 0, $offset );
			$postStatement = substr( $str, $offset + strlen( $text ) );

			// Try to fetch the import
			$import->content = @file_get_contents( $import->path );

			if ( $import->content ) {
				// Imported file exists, so construct new content

				// Add import details to config
				if ( !$import->isExternalURL ) {

					// We only validate modified times of local files
					$imports_mtimes[] = filemtime( $import->path );

					// Obfuscate any import at-rules within comment blocks
					$import->content = preg_replace_callback(
						$regex->comment, $cb_obfuscateDirectives, $import->content );

					$imports_filenames[] = $relativeContext ?
						"{$relativeContext}/{$import->name}" : $import->name;
				}

				// Set relative context if there is a nested import statement
				if ( !$import->isExternalURL and preg_match( $regex->import, $import->content ) ) {
					if ( $import->mediaContext ) {
						// Strip nested imports since we can't support nested media blocks
						$message = "Cannot import nested files within '$import->name' due to mediaContext";
						self::log( $message );
						//self::triggerWarning( $message, __METHOD__ );
						$import->content = preg_replace( $regex->import, '', $import->content );
					}
					else {
						$dirName = dirname( $import->name );
						if ( $dirName != '.' ) {
							$relativeContext =
								!empty( $relativeContext ) ? "{$relativeContext}/{$dirName}" : $dirName;
						}
					}
				}
				else {
					$relativeContext = '';
				}

				// Reconstruct the main string
				$str = $preStatement;
				if ( $import->mediaContext ) {
					$str .= "@media $import->mediaContext {" . $import->content . '}';
				}
				else {
					$str .= $import->content;
				}
				$str .= $postStatement;
			}
			else {
				// Failed to open import, just continue with the import line removed
				self::log( 'File not found' );
				$str = $preStatement . $postStatement;

				if ( $import->isExternalURL ) {
					self::triggerNotice( "Unable to import external URL: {$import->path}", __METHOD__ );
				}
			}
		}

		$config->data[ $compileName ][ 'imports' ] = $imports_filenames;
		$config->data[ $compileName ][ 'imports_urls' ] = $imports_urls;
		$config->data[ $compileName ][ 'datem_sum' ] = array_sum( $imports_mtimes ) + $hostfile->mtime;
		$config->data[ $compileName ][ 'options' ] = self::$options;

		// Need to store the current path so we can check we're using the right config path later
		$config->data[ 'originPath' ] = $config->path;

		// Save config changes
		file_put_contents( $config->path, serialize( $config->data ) );

		self::log( $config->data );

		return $str;
	}

	protected static function minify ( $str ) {
		$replacements = array(
			'!\n+| (\{)!'                     => '$1',    // Trim whitespace
			'!(^|[: \(,])0(\.\d+)!'             => '$1$2',  // Strip leading zeros on floats
			'!(^|[: \(,])\.?0[a-zA-Z%]{1,5}!i'  => '${1}0', // Strip unnecessary units on zero values
			'!(^|\:) *(0 0 0|0 0 0 0) *(;|\})!' => '${1}0${3}', // Collapse zero lists
			'!(padding|margin) ?\: *0 0 *(;|\})!' => '${1}:0${2}', // Collapse zero lists continued
			'!\s*([>~+=])\s*!'                  => '$1',     // Clean-up around combinators
			'!\#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3!i'
			                                    => '#$1$2$3', // Compress hex codes
			'!rgba\([0-9]+,[0-9]+,[0-9]+,0\)!'  => 'transparent', // Compress rgba
		);
		return preg_replace(
			array_keys( $replacements ), array_values( $replacements ), $str );
	}

	protected static function normalize ( $str ) {
		$replacements = array(
			'!\s+!'                             => ' ',
			'!(\[)\s*|\s*(\])|(\()\s*|\s*(\))!' => '${1}${2}${3}${4}',  // Trim internal bracket WS
			'!\s*(;|,|\/|\!)\s*!'               => '$1',     // Trim WS around delimiters and special characters
		);
		return preg_replace(
			array_keys( $replacements ), array_values( $replacements ), $str );
	}

	protected static function aliasAtRules ( $output ) {
		if ( empty( self::$aliases[ 'at-rules' ] ) ) {
			return $output;
		}

		$aliases = self::$aliases[ 'at-rules' ];

		foreach ( $aliases as $at_rule => $at_rule_aliases ) {
			if (
				strpos( $output, "@$at_rule " ) === -1 or
				strpos( $output, "@$at_rule{" ) === -1
			) {
				// Nothing to see here
				continue;
			}
			$scan_pos = 0;

			// Find at-rules that we want to alias
			while ( preg_match( "!@$at_rule" . '[\s{]!', $output, $match, PREG_OFFSET_CAPTURE, $scan_pos ) ) {

				// Store the match position
				$block_start_pos = $match[0][1];
				// Capture the curly bracketed block
				$curly_match = self::matchBrackets( $output, $brackets = array( '{', '}' ), $block_start_pos );

				if ( !$curly_match ) {
					// Couldn't match the block
					break;
				}

				// The end of the block
				$block_end_pos = $curly_match->end;

				// Build up string with aliased blocks for splicing
				$original_block = substr( $output, $block_start_pos, $block_end_pos - $block_start_pos );
				$blocks = array();
				foreach ( $at_rule_aliases as $alias ) {
					// Copy original block, replacing at-rule with alias name
					$copy_block = str_replace( "@$at_rule", "@$alias", $original_block );

					// Aliases are nearly always prefixed, capture the current vendor name
					preg_match( self::$regex->vendorPrefix, $alias, $vendor );

					$vendor = $vendor ? $vendor[1] : null;

					// Duplicate rules
					if ( preg_match_all( self::$regex->token->rule, $copy_block, $copy_matches ) ) {
						$originals = array();
						$replacements = array();

						foreach ( $copy_matches[0] as $copy_match ) {
							// Clone the matched rule
							$originals[] = $rule_label = $copy_match;
							$cloneRule = clone self::$storage->tokens->rules[ $rule_label ];

							// Set the vendor context
							$cloneRule->vendorContext = $vendor;

							// Filter out declarations that have different vendor context
							$new_set = array();
							foreach ( $cloneRule as $declaration ) {
								if ( !$declaration->vendor or $declaration->vendor === $vendor ) {
									$new_set[] = $declaration;
								}
							}
							$cloneRule->declarations = $new_set;

							// Store the clone
							$replacements[] = $clone_rule_label = self::createTokenLabel( 'r' );
							self::$storage->tokens->rules[ $clone_rule_label ] = $cloneRule;
						}
						// Finally replace the original labels with the cloned rule labels
						$copy_block = str_replace( $originals, $replacements, $copy_block );
					}
					$blocks[] = $copy_block;
				}

				// The original version is always last in the list
				$blocks[] = $original_block;
				$blocks = implode( "\n", $blocks );

				// Glue back together
				$output =
					substr( $output, 0, $block_start_pos ) .
					$blocks .
					substr( $output, $block_end_pos );

				// Move the regex pointer forward
				$scan_pos = $block_start_pos + strlen( $blocks );

			} // while
		} // foreach
		return $output;
	}


	#############################
	#  preg_replace callbacks

	protected static function cb_extractStrings ( $match ) {
		$label = self::createTokenLabel( 's' );
		self::$storage->tokens->strings[ $label ] = $match[0];
		return $label;
	}

	protected static function cb_restoreStrings ( $match ) {
		return self::$storage->tokens->strings[ $match[0] ];
	}

	protected static function cb_extractComments ( $match ) {
		$comment = $match[0];
		$flagged = strpos( $comment, '/*!' ) === 0;
		$label = self::createTokenLabel( 'c' );
		self::$storage->tokens->comments[ $label ] = $flagged ? '/*!' . substr( $match[1], 1 ) . '*/' : $comment;
		return $label;
	}

	protected static function cb_restoreComments ( $match ) {
		return self::$storage->tokens->comments[ $match[0] ];
	}

	protected static function cb_extractRules ( $match ) {

		$rule = new CssCrush_rule( $match[1], $match[2] );

		// Only store rules with declarations
		if ( !empty( $rule->declarations ) ) {
			if ( !empty( self::$aliases ) ) {
				$rule->addPropertyAliases();
				$rule->addFunctionAliases();
				$rule->addValueAliases();
			}
			$rule->applyMacros();
			$rule->expandSelectors();

			$label = self::createTokenLabel( 'r' );
			self::$storage->tokens->rules[ $label ] = $rule;
			return $label . "\n";
		}
		else {
			return '';
		}
	}

	protected static function cb_extractVariables ( $match ) {
		$regex = self::$regex;

		$block = $match[2];

		// Strip comment markers
		$block = preg_replace( $regex->token->comment, '', $block );

		// Excecute any custom functions
		$parens = self::matchAllParens( $block );
		if ( count( $parens->matches ) ) {
			CssCrush_rule::$storage->tmpParens = $parens->matches;
			$block = preg_replace_callback( $regex->function->custom, array( 'CssCrush_rule', 'css_fn' ), $parens->string );
			// Fold matches back in
			$block = str_replace( array_keys( $parens->matches ), array_values( $parens->matches ), $block );
		}

		$variables_match = self::splitDelimList( $block, ';', true );

		// Loop through the pairs, restore parens
		foreach ( $variables_match->list as $var ) {
			$colon = strpos( $var, ':' );
			if ( $colon === -1 ) {
				continue;
			}
			$name = trim( substr( $var, 0, $colon ) );
			$value = trim( substr( $var, $colon + 1 ) );
			self::$storage->variables[ trim( $name ) ] = $value;
		}
		return '';
	}

	protected static function cb_placeVariables ( $match ) {
		$before_char = $match[1];

		// Check for dollar shorthand
		if ( empty( $match[2] ) and isset( $match[3] ) and strpos( $match[0], '$' ) !== false ) {
			$variable_name = $match[3];
		}
		else {
			$variable_name = $match[2];
		}

		//self::log( $match );
		if ( isset( self::$storage->variables[ $variable_name ] ) ) {
			return $before_char . self::$storage->variables[ $variable_name ];
		}
		else {
			return $before_char;
		}
	}

	protected static function cb_restoreLiteral ( $match ) {
		return self::$storage->tokens[ $match[0] ];
	}

	protected static function cb_obfuscateDirectives ( $match ) {
		return str_replace( '@', '(at)', $match[0] );
	}

	protected static function cb_printRule ( $match ) {
		$minify = !self::$options[ 'debug' ];
		$ruleLabel = $match[0];
		if ( !isset( self::$storage->tokens->rules[ $ruleLabel ] ) ) {
			return '';
		}
		$rule = self::$storage->tokens->rules[ $ruleLabel ];

		// Build the selector
		$selectors = implode( ',', $rule->selectors );

		// Build the block
		$block = array();
		$colon = $minify ? ':' : ': ';
		foreach ( $rule as $declaration ) {
			$block[] = "{$declaration->property}$colon{$declaration->value}";
		}

		// Return whole rule
		if ( $minify ) {
			$block = implode( ';', $block );
			return "$selectors{{$block}}";
		}
		else {
			$block = implode( ";\n\t", $block );
			// Include pre rule comments
			$comments = implode( "\n", $rule->comments );
			return "$comments\n$selectors {\n\t$block;\n}\n";
		}
	}


	############
	#  Utilities

	public static function splitDelimList ( $str, $delim, $fold_in = false ) {
		$match_obj = self::matchAllParens( $str );
		$match_obj->list = array_filter( explode( $delim, $match_obj->string ) );
		if ( $fold_in ) {
			$match_keys = array_keys( $match_obj->matches );
			$match_values = array_values( $match_obj->matches );
			foreach ( $match_obj->list as &$item ) {
				$item = str_replace( $match_keys, $match_values, $item );
			}
		}
		return $match_obj;
	}

	public static function createTokenLabel ( $prefix, $counter = null ) {
		$counter = !is_null( $counter ) ? $counter : ++self::$tokenUID;
		return "___$prefix{$counter}___";
	}

	public static function normalizeWhiteSpace ( $str ) {
		$str = trim( preg_replace( '!\s+!', ' ', $str ) );
		// spaces around commas and inside parens
		$str = str_replace(
			array( ', ', ' ,', '( ', ' )' ),
			array( ',' , ',' , '(' , ')'  ),
			$str );
		return $str;
	}

	public static function matchBrackets ( $str, $brackets = array( '(', ')' ), $search_pos = 0 ) {
		$open_token = $brackets[0];
		$close_token = $brackets[1];
		$openings = array();
		$closings = array();
		$brake = 50; // Set a limit in the case of errors

		$match = new stdClass;

		$start_index = strpos( $str, $open_token, $search_pos );
		$close_index = strpos( $str, $close_token, $search_pos );

		if ( $start_index === false ) {
			return false;
		}
		if ( substr_count( $str, $open_token ) !== substr_count( $str, $close_token ) ) {
		 	$sample = substr( $str, 0, 15 );
			trigger_error( __METHOD__ . ": Unmatched token near '$sample'.\n", E_USER_WARNING );
			return false;
		}

		while (
			( $start_index !== false or $close_index !== false ) and $brake--
		) {
			if ( $start_index !== false and $close_index !== false ) {
				$search_pos = min( $start_index, $close_index );
				if ( $start_index < $close_index ) {
					$openings[] = $start_index;
				}
				else {
					$closings[] = $close_index;
				}
			}
			elseif ( $start_index !== false ) {
				$search_pos = $start_index;
				$openings[] = $start_index;
			}
			else {
				$search_pos = $close_index;
				$closings[] = $close_index;
			}
			$search_pos += 1; // Advance

			if ( count( $closings ) === count( $openings ) ) {
				$match->openings = $openings;
				$match->closings = $closings;
				$match->start = $openings[0];
				$match->end = $closings[ count( $closings ) - 1 ] + 1;
				return $match;
			}
			$start_index = strpos( $str, $open_token, $search_pos );
			$close_index = strpos( $str, $close_token, $search_pos );
		}

		trigger_error( __METHOD__ . ": Reached brake limit of '$brake'. Exiting.\n", E_USER_WARNING );
		return false;
	}

	public static function matchAllParens ( $str ) {
		$storage = array();
		$brackets = array( '(', ')' );
		$match = self::matchBrackets( $str );
		$matches = array();
		while ( $match ) {
			$label = self::createTokenLabel( 'p' );
			$capture = substr( $str, $match->start, $match->end - $match->start );
			self::$storage->tokens->parens[ $label ] = $capture;
			$matches[ $label ] = $capture;
			$str =
				substr( $str, 0, $match->start ) .
				$label .
				substr( $str, $match->end );
			$match = self::matchBrackets( $str, $brackets );
		}
		return (object) array(
			'matches' => $matches,
			'string'  => str_replace( $brackets, '', $str ),
		);
	}

	public static function addRuleMacro ( $fn ) {
		if ( !function_exists( $fn ) ) {
			trigger_error( __METHOD__ . ": Function '$fn' not defined.\n", E_USER_WARNING );
			return;
		}
		if ( !in_array( $fn, self::$macros ) ) {
			self::$macros[] = $fn;
		}
	}

}

# Initialize manually
CssCrush::init();



class CssCrush_rule implements IteratorAggregate {

	public static $storage = array();

	public static function init () {
		self::$storage = (object) self::$storage;
	}

	public $vendorContext = null;
	public $properties = array();
	public $selectors = null;
	public $parens = array();
	public $declarations = array();
	public $comments = array();

	public function __construct ( $selector_string = null, $declarations_string ) {

		$regex = CssCrush::$regex;

		// Parse the selectors chunk
		if ( !empty( $selector_string ) ) {

			$selector_adjustments = array(
				// 'hocus' and 'pocus' pseudo class shorthand
				'!:hocus([^a-z0-9_-])!' => ':any(:hover,:focus)$1',
				'!:pocus([^a-z0-9_-])!' => ':any(:hover,:focus,:active)$1',
				// Reduce double colon syntax for backwards compatability
				'!::(after|before|first-letter|first-line)!' => ':$1',
			);
			$selector_string = preg_replace(
				array_keys( $selector_adjustments ), array_values( $selector_adjustments ), $selector_string );

			$selectors_match = CssCrush::splitDelimList( $selector_string, ',' );
			$this->parens += $selectors_match->matches;

			// Remove and store comments that sit above the first selector
			// remove all comments between the other selectors
			preg_match_all( $regex->token->comment, $selectors_match->list[0], $m );
			$this->comments = $m[0];
			foreach ( $selectors_match->list as &$selector ) {
				$selector = preg_replace( $regex->token->comment, '', $selector );
				$selector = trim( $selector );
			}
			$this->selectors = $selectors_match->list;
		}

		// Parse the declarations chunk
		$declarations_match = CssCrush::splitDelimList( $declarations_string, ';' );
		$this->parens += $declarations_match->matches;

		// Parse declarations in to property/value pairs
		foreach ( $declarations_match->list as $declaration ) {

			// Strip comments around the property
			$declaration = preg_replace( $regex->token->comment, '', $declaration );

			// Store the property
			$colonPos = strpos( $declaration, ':' );
			if ( $colonPos === false ) {
				// If there is no colon it's malformed
				continue;
			}
			else {
				$prop = trim( substr( $declaration, 0, $colonPos ) );
				// Store the property name
				$this->addProperty( $prop );
			}

			// Extract the value part of the declaration
			$value = substr( $declaration, $colonPos + 1 );
			$value = $value !== false ? trim( $value ) : $value;
			if ( $value === false or $value === '' ) {
				// We'll ignore declarations with empty values
				continue;
			}

			// If are parenthesised expressions in the value
			// Search for any custom functions so we can apply them
			if ( count( $declarations_match->matches ) ) {
				self::$storage->tmpParens = $declarations_match->matches;
				$value = preg_replace_callback( $regex->function->custom, array( 'self', 'css_fn' ), $value );
			}

			// Store the property family
			// Store the vendor id, if one is present
			if ( preg_match( $regex->vendorPrefix, $prop, $vendor ) ) {
				$family = $vendor[2];
				$vendor = $vendor[1];
			}
			else {
				$vendor = null;
				$family = $prop;
			}

			// Create an index of all functions in the current declaration
			if ( preg_match_all( $regex->function->match, $value, $functions ) > 0 ) {
				$out = array();
				foreach ( $functions[2] as $index => $fn_name ) {
					$out[] = $fn_name;
				}
				$functions = array_unique( $out );
			}
			else {
				$functions = array();
			}

			// Store the declaration
			$_declaration = (object) array(
				'property'  => $prop,
				'family'    => $family,
				'vendor'    => $vendor,
				'functions' => $functions,
				'value'     => $value,
			);
			$this->declarations[] = $_declaration;
		}
	}

	public function addPropertyAliases () {

		$regex = CssCrush::$regex;
		$aliasedProperties =& CssCrush::$aliases[ 'properties' ];

		// First test for the existence of any aliased properties
		$intersect = array_intersect( array_keys( $aliasedProperties ), array_keys( $this->properties ) );
		if ( empty( $intersect ) ) {
			return;
		}

		// Shim in aliased properties
		$new_set = array();
		foreach ( $this->declarations as $declaration ) {
			$prop = $declaration->property;
			if ( isset( $aliasedProperties[ $prop ] ) ) {
				// There are aliases for the current property
				foreach ( $aliasedProperties[ $prop ] as $prop_alias ) {
					if ( $this->propertyCount( $prop_alias ) ) {
						continue;
					}
					// If the aliased property hasn't been set manually, we create it
					$copy = clone $declaration;
					$copy->family = $copy->property;
					$copy->property = $prop_alias;
					// Remembering to set the vendor property
					$copy->vendor = null;
					// Increment the property count
					$this->addProperty( $prop_alias );
					if ( preg_match( $regex->vendorPrefix, $prop_alias, $vendor ) ) {
						$copy->vendor = $vendor[1];
					}
					$new_set[] = $copy;
				}
			}
			// Un-aliased property or a property alias that has been manually set
			$new_set[] = $declaration;
		}
		// Re-assign
		$this->declarations = $new_set;
	}

	public function addFunctionAliases () {

		$function_aliases =& CssCrush::$aliases[ 'functions' ];
		$aliased_functions = array_keys( $function_aliases );

		if ( empty( $aliased_functions ) ) {
			return;
		}

		$new_set = array();

		// Keep track of the function aliases we apply and to which property 'family'
		// they belong, so we can avoid un-unecessary duplications
		$used_fn_aliases = array();

		// Shim in aliased functions
		foreach ( $this->declarations as $declaration ) {

			// No functions, skip
			if ( empty( $declaration->functions ) ) {
				$new_set[] = $declaration;
				continue;
			}
			// Get list of functions used in declaration that are alias-able, if none skip
			$intersect = array_intersect( $declaration->functions, $aliased_functions );
			if ( empty( $intersect ) ) {
				$new_set[] = $declaration;
				continue;
			}
			// CssCrush::log($intersect);
			// Loop the aliasable functions
			foreach ( $intersect as $fn_name ) {
				if ( $declaration->vendor ) {
					// If the property is vendor prefixed we use the vendor prefixed version
					// of the function if it exists.
					// Else we just skip and use the unprefixed version
					$fn_search = "-{$declaration->vendor}-$fn_name";
					if ( in_array( $fn_search, $function_aliases[ $fn_name ] ) ) {
						$declaration->value = preg_replace(
							'!(^| )' . $fn_name . '!',
							'${1}' . $fn_search,
							$declaration->value
						);
						$used_fn_aliases[ $declaration->family ][] = $fn_search;
					}
				}
				else {

					// Duplicate the rule for each alias
					foreach ( $function_aliases[ $fn_name ] as $fn_alias ) {

						if (
							isset( $used_fn_aliases[ $declaration->family ] ) and
							in_array( $fn_alias, $used_fn_aliases[ $declaration->family ] )
						) {
							// If the function alias has already been applied in a vendor property
							// for the same declaration property assume all is good
							continue;
						}
						$copy = clone $declaration;
						$copy->value = preg_replace(
							'!(^| )' . $fn_name . '!',
							'${1}' . $fn_alias,
							$copy->value
						);
						$new_set[] = $copy;
						// Increment the property count
						$this->addProperty( $copy->property );
					}
				}
			}
			$new_set[] = $declaration;
		}

		// Re-assign
		$this->declarations = $new_set;
	}

	public function addValueAliases () {

		$aliasedValues =& CssCrush::$aliases[ 'values' ];

		// First test for the existence of any aliased properties
		$intersect = array_intersect( array_keys( $aliasedValues ), array_keys( $this->properties ) );

		if ( empty( $intersect ) ) {
			return;
		}

		$new_set = array();
		foreach ( $this->declarations as $declaration ) {
			foreach ( $aliasedValues as $value_prop => $value_aliases ) {
				if ( $this->propertyCount( $value_prop ) < 1 ) {
					continue;
				}
				foreach ( $value_aliases as $value => $aliases ) {
					if ( $declaration->value === $value ) {
						foreach ( $aliases as $alias ) {
							$copy = clone $declaration;
							$copy->value = $alias;
							$new_set[] = $copy;
						}
					}
				}
			}
			$new_set[] = $declaration;
		}
		// Re-assign
		$this->declarations = $new_set;
	}

	public function applyMacros () {
		foreach ( CssCrush::$macros as $fn ) {
			call_user_func( $fn, $this );
		}
	}

	public function expandSelectors () {

		$new_set = array();
		$reg_comma = '!\s*,\s*!';

		foreach ( $this->selectors as $selector ) {
			$pos = strpos( $selector, ':any___' );
			if ( $pos !== false ) {
				// Contains an :any statement so we expand
				$chain = array( '' );
				do {
					if ( $pos === 0 ) {
						preg_match( '!:any(___p\d+___)!', $selector, $m );

						// Parse the arguments
						$expression = trim( $this->parens[ $m[1] ], '()' );
						$parts = preg_split( $reg_comma, $expression, null, PREG_SPLIT_NO_EMPTY );

						$tmp = array();
						foreach ( $chain as $rowCopy ) {
							foreach ( $parts as $part ) {
								$tmp[] = $rowCopy . $part;
							}
						}
						$chain = $tmp;
						$selector = substr( $selector, strlen( $m[0] ) );
					}
					else {
						foreach ( $chain as &$row ) {
							$row .= substr( $selector, 0, $pos );
						}
						$selector = substr( $selector, $pos );
					}
				} while ( ( $pos = strpos( $selector, ':any___' ) ) !== false );

				// Finish off
				foreach ( $chain as &$row ) {
					$new_set[] = $row . $selector;
				}
			}
			else {
				// Nothing special
				$new_set[] = $selector;
			}
		}
		$this->selectors = $new_set;
	}


	############
	#  IteratorAggregate

	public function getIterator () {
		return new ArrayIterator( $this->declarations );
	}


	############
	#  Rule API

	public function propertyCount ( $prop ) {
		if ( array_key_exists( $prop, $this->properties ) ) {
			return $this->properties[ $prop ];
		}
		return 0;
	}

	// Add property to the rule index keeping track of the count
	public function addProperty ( $prop ) {
		if ( isset( $this->properties[ $prop ] ) ) {
			$this->properties[ $prop ]++;
		}
		else {
			$this->properties[ $prop ] = 1;
		}
	}

	public function createDeclaration ( $property, $value, $options = array() ) {
		$_declaration = array(
			'property'  => $property,
			'family'    => null,
			'vendor'    => null,
			'value'     => $value,
		);
		$this->addProperty( $property );
		return (object) array_merge( $_declaration, $options );
	}

	// Get a declaration value without paren tokens
	public function getDeclarationValue ( $declaration ) {
		$paren_keys = array_keys( $this->parens );
		$paren_values = array_values( $this->parens );
		return str_replace( $paren_keys, $paren_values, $declaration->value );
	}


	############
	#  Custom functions

	protected static function parseMathArgs ( $argument_string ) {
		// Split on comma, trim, and remove empties
		$args = array_filter( array_map( 'trim', explode( ',', $argument_string ) ) );

		// Pass anything non-numeric through math
		foreach ( $args as &$arg ) {
			if ( !preg_match( '!^-?[\.0-9]+$!', $arg ) ) {
				$arg = self::css_fn_math( $arg );
			}
		}
		return $args;
	}

	public static function css_fn ( $match ) {

		$before_char = $match[1];
		$fn_name = $match[2];
		$fn_name_clean = str_replace( '-', '', $fn_name );
		$paren_id = $match[3];

		if ( !isset( self::$storage->tmpParens[ $paren_id ] ) ) {
			return $before_char;
		}
		// Get input value and trim parens
		$input = self::$storage->tmpParens[ $paren_id ];
		$input = trim( substr( $input, 1, strlen( $input ) - 2 ) );

		// An empty function name defaults to math
		if ( empty( $fn_name_clean ) ) {
			$fn_name_clean = 'math';
		}
		// Capture a negative sign e.g -( 20 * 2 )
		if ( $fn_name === '-' ) {
			$before_char .= '-';
		}
		return $before_char . call_user_func( array( 'self', "css_fn_$fn_name_clean" ), $input );
	}

	protected static function css_fn_math ( $input ) {
		// Whitelist allowed characters
		$input = preg_replace( '![^\.0-9\*\/\+\-\(\)]!', '', $input );
		$result = 0;
		try {
			$result = eval( "return $input;" );
		}
		catch ( Exception $e ) {};
		return round( $result, 10 );
	}

	protected static function css_fn_percent ( $input ) {

		$args = self::parseMathArgs( $input );

		// Use precision argument if it exists, default to 7
		$precision = isset( $args[2] ) ? $args[2] : 7;

		$result = 0;
		if ( count( $args ) > 1 ) {
			// Arbitary high precision division
			$div = (string) bcdiv( $args[0], $args[1], 25 );
			// Set precision percentage value
			$result = (string) bcmul( $div, '100', $precision );
			// Trim unnecessary zeros and decimals
			$result = trim( $result, '0' );
			$result = rtrim( $result, '.' );
		}
		return $result . '%';
	}

	// Percent function alias
	protected static function css_fn_pc ( $input ) {
		return self::css_fn_percent( $input );
	}

	protected static function css_fn_floor ( $input ) {
		return floor( self::css_fn_math( $input ) );
	}

	protected static function css_fn_ceil ( $input ) {
		return ceil( self::css_fn_math( $input ) );
	}

	protected static function css_fn_round ( $input ) {
		return round( self::css_fn_math( $input ) );
	}

	protected static function css_fn_datauri ( $input ) {

		// Normalize, since argument might be a string token
		if ( strpos( $input, '___s' ) === 0 ) {
			$string_labels = array_keys( CssCrush::$storage->tokens->strings );
			$string_values = array_values( CssCrush::$storage->tokens->strings );
			$input = trim( str_replace( $string_labels, $string_values, $input ), '\'"`' );
		}

		// Default return value
		$result = "url($input)";

		// No attempt to process absolute urls
		if (
			strpos( $input, 'http://' ) === 0 or
			strpos( $input, 'https://' ) === 0
		) {
			return $result;
		}

		// Get system file path
		if ( strpos( $input, '/' ) === 0 ) {
			$file = CssCrush::$config->docRoot . $input;
		}
		else {
			$baseDir = CssCrush::$config->baseDir;
			$file = "$baseDir/$input";
		}
		// csscrush::log($file);

		// File not found
		if ( !file_exists( $file ) ) {
			return $result;
		}

		$file_ext = pathinfo( $file, PATHINFO_EXTENSION );

		// Only allow certain extensions
		$allowed_file_extensions = array(
			'woff' => 'font/woff;charset=utf-8',
			'ttf'  => 'font/truetype;charset=utf-8',
			'gif'  => 'image/gif',
			'jpeg' => 'image/jpg',
			'jpg'  => 'image/jpg',
			'png'  => 'image/png',
		);
		if ( !array_key_exists( $file_ext, $allowed_file_extensions ) ) {
			return $result;
		}

		$mime_type = $allowed_file_extensions[ $file_ext ];
		$base64 = base64_encode( file_get_contents( $file ) );
		$data_uri = "data:{$mime_type};base64,$base64";
		if ( strlen( $data_uri ) > 32000 ) {
			// Too big for IE
		}
		return "url($data_uri)";
	}

}

# Initialize manually
CssCrush_rule::init();


