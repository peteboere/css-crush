<?php
/**
 * 
 * Main script. Includes core public API
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
	public static $compileName;
	public static $options;
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
				\$\(\s*([a-z0-9_-]+)\s*\)  # Dollar syntax
			)!ix',
			'custom'  => '!(^|[^a-z0-9_-])(<functions>)?(___p\d+___)!i',
			'match'   => '!(^|[^a-z0-9_-])([a-z_-]+)(___p\d+___)!i',
		),
		'vendorPrefix' => '!^-([a-z]+)-([a-z-]+)!',
	);

	// Init called once manually post class definition
	public static function init ( $current_dir ) {

		self::$location = $current_dir;

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
	 * @param string $file  URL or System path to the host CSS file
	 * @param mixed $options  An array of options or null
	 * @return string  The public path to the compiled file or an empty string
	 */
	public static function file ( $file, $options = null ) {

		$config = self::$config;

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
		self::parseOptions( $options );

		// Make basic information about the hostfile accessible
		$hostfile = new stdClass;
		$hostfile->name = basename( $file );
		$hostfile->dir = $config->baseDir;
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

		// Compiled filename we're searching for
		self::$compileName = basename( $hostfile->name, '.css' ) . self::$COMPILE_SUFFIX;

		// If cache is enabled check for a valid compiled file
		if ( self::$options[ 'cache' ] === true ) {
			$validCompliledFile = self::validateCache( $hostfile );
			if ( is_string( $validCompliledFile ) ) {
				return $validCompliledFile;
			}
		}

		// Collate hostfile and imports
		$stream = CssCrush_Importer::hostfile( $hostfile );
		
		// Compile
		$stream = self::compile( $stream );

		// Add in boilerplate
		if ( self::$options[ 'boilerplate' ] ) {
			$stream = self::getBoilerplate() . "\n$stream";
		}

		// Create file and return path. Return empty string on failure
		if ( file_put_contents( "$config->baseDir/" . self::$compileName, $stream ) ) {
			return "$config->baseURL/" . self::$compileName .
				( self::$options[ 'versioning' ] ? '?' . time() : '' );
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
	 * @param array $attributes  An array of HTML attributes
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
	 * Compile a raw string of CSS string and return it
	 *
	 * @param string $string  CSS text
	 * @param mixed $options  An array of options or null
	 * @return string  CSS text
	 */
	public static function string ( $string, $options = null ) {
		self::parseOptions( $options );
		return self::compile( $string );
	}

	/**
	 * Add variables globally
	 *
	 * @param mixed $var  Assoc array of variable names and values, a php ini filename or null
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
	 * @param string $dir  System path to the directory
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


	#####################
	#  Developer
	
	// Enable logging
	public static $logging = false;

	// Print the log
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
			// Enable/disable the cache
			'cache'       => true,
			// Keeping track of global vars internally
			'_globalVars' => self::$globalVars,
		);

		self::$options = is_array( $options ) ?
			array_merge( $option_defaults, $options ) : $option_defaults;
	}

	protected static function calculateVariables () {
		
		$regex = self::$regex;
		
		// In-file variables override global variables
		// Runtime variables override in-file variables
		self::$storage->variables = array_merge(
			self::$globalVars, self::$storage->variables );
		if ( !empty( self::$options[ 'vars' ] ) ) {
			self::$storage->variables = array_merge(
				self::$storage->variables, self::$options[ 'vars' ] );
		}

		// Place variables referenced inside variables
		// Excecute any custom functions
		foreach ( self::$storage->variables as $name => &$value ) {
			
			// Referenced variables
			$value = preg_replace_callback(
				$regex->function->var, array( 'self', 'cb_placeVariables' ), $value );

			// Custom functions
			$parens = self::matchAllParens( $value );
			if ( count( $parens->matches ) ) {
				CssCrush::$storage->tmpParens = $parens->matches;
				$value = preg_replace_callback( 
					$regex->function->custom, array( 'CssCrush_Function', 'css_fn' ), $parens->string );
				// Fold matches back in
				$value = str_replace( array_keys( $parens->matches ), array_values( $parens->matches ), $value );
			}
		}

	}

	protected static function placeVariables ( $stream ) {
		$stream = preg_replace_callback(
			self::$regex->function->var, array( 'self', 'cb_placeVariables' ), $stream );
		// Place variables in any string tokens
		foreach ( self::$storage->tokens->strings as $label => &$string ) {
			if ( strpos( $string, '$' ) !== false ) {
				$string = preg_replace_callback(
					self::$regex->function->var, array( 'self', 'cb_placeVariables' ), $string );
			}
		}
		return $stream;
	}

	protected static function compile ( $stream ) {

		$regex = self::$regex;

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

		// Load in aliases and macros
		if ( !self::$assetsLoaded ) {
			self::loadAssets();
			self::$assetsLoaded = true;
		}

		// Set the custom function regular expression
		$css_functions = CssCrush_Function::getFunctions();
		$regex->function->custom = str_replace(
			'<functions>', implode( '|', $css_functions ), $regex->function->custom );

		// Extract comments
		$stream = preg_replace_callback( $regex->comment, array( 'self', 'cb_extractComments' ), $stream );

		// Extract strings
		$stream = preg_replace_callback( $regex->string, array( 'self', 'cb_extractStrings' ), $stream );

		// Parse variables
		$stream = preg_replace_callback( $regex->variables, array( 'self', 'cb_extractVariables' ), $stream );

		// Calculate the variable stack
		self::calculateVariables();
		self::log( self::$storage->variables );

		// Place the variables
		$stream = self::placeVariables( $stream );

		// Normalize whitespace
		$stream = self::normalize( $stream );

		// Adjust the stream so we can extract the rules cleanly
		$map = array(
			'@' => "\n@",
			'}' => "}\n",
			'{' => "{\n",
			';' => ";\n",
		);
		$stream = "\n" . str_replace( array_keys( $map ), array_values( $map ), $stream );

		// Extract rules
		$stream = preg_replace_callback( $regex->rule, array( 'self', 'cb_extractRules' ), $stream );

		// Alias at-rules (if there are any)
		$stream = self::aliasAtRules( $stream );

		// print it all back
		$stream = self::display( $stream );

		// self::log( self::$storage->tokens->rules );
		// self::log( self::$storage->tokens );
		self::log( self::$config->data );

		// Release memory
		self::$storage = null;

		return $stream;
	}

	protected static function display ( $stream ) {
		$minify = !self::$options[ 'debug' ];
		$regex = self::$regex;

		if ( $minify ) {
			$stream = preg_replace( $regex->token->comment, '', $stream );
		}
		else {
			// Create newlines after tokens
			$stream = preg_replace( '!([{}])!', "$1\n", $stream );
			$stream = preg_replace( '!([@])!', "\n$1", $stream );
			$stream = preg_replace( '!(___[a-z0-9]+___)!', "$1\n", $stream );

			// Kill double spaces
			$stream = ltrim( preg_replace( '!\n+!', "\n", $stream ) );
		}

		// Kill leading space
		$stream = preg_replace( '!\n\s+!', "\n", $stream );

		// Print out rules
		$stream = preg_replace_callback( $regex->token->rule, array( 'self', 'cb_printRule' ), $stream );

		// Insert parens
		$paren_labels = array_keys( self::$storage->tokens->parens );
		$paren_values = array_values( self::$storage->tokens->parens );
		$stream = str_replace( $paren_labels, $paren_values, $stream );

		if ( $minify ) {
			$stream = self::minify( $stream );
		}
		else {
			// Insert comments
			$comment_labels = array_keys( self::$storage->tokens->comments );
			$comment_values = array_values( self::$storage->tokens->comments );
			foreach ( $comment_values as &$comment ) {
				$comment = "$comment\n";
			}
			$stream = str_replace( $comment_labels, $comment_values, $stream );
			// Normalize line breaks
			$stream = preg_replace( '!\n{3,}!', "\n\n", $stream );
		}

		// Insert literals
		$string_labels = array_keys( self::$storage->tokens->strings );
		$string_values = array_values( self::$storage->tokens->strings );
		$stream = str_replace( $string_labels, $string_values, $stream );

		// I think we're done
		return $stream;
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
					self::log( "Files and options have not been modified, returning existing
						 file '$existingfile->URL'" );
					return $existingfile->URL .	( self::$options[ 'versioning' ] !== false  ? "?{$existing_datesum}" : '' );
				}
				else {
					// Remove old file and continue making a new one...
					self::log( 'Files or options have been modified, removing existing file' );
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

	protected static function minify ( $str ) {
		$replacements = array(
			'!\n+| (\{)!'                       => '$1',    // Trim whitespace
			'!(^|[: \(,])0(\.\d+)!'             => '$1$2',  // Strip leading zeros on floats
			'!(^|[: \(,])\.?0[a-zA-Z]{1,5}!i'   => '${1}0', // Strip unnecessary units on zero values
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

	protected static function aliasAtRules ( $stream ) {
		if ( empty( self::$aliases[ 'at-rules' ] ) ) {
			return $stream;
		}

		$aliases = self::$aliases[ 'at-rules' ];

		foreach ( $aliases as $at_rule => $at_rule_aliases ) {
			if (
				strpos( $stream, "@$at_rule " ) === -1 or
				strpos( $stream, "@$at_rule{" ) === -1
			) {
				// Nothing to see here
				continue;
			}
			$scan_pos = 0;

			// Find at-rules that we want to alias
			while ( preg_match( "!@$at_rule" . '[\s{]!', $stream, $match, PREG_OFFSET_CAPTURE, $scan_pos ) ) {

				// Store the match position
				$block_start_pos = $match[0][1];
				// Capture the curly bracketed block
				$curly_match = self::matchBrackets( $stream, $brackets = array( '{', '}' ), $block_start_pos );

				if ( !$curly_match ) {
					// Couldn't match the block
					break;
				}

				// The end of the block
				$block_end_pos = $curly_match->end;

				// Build up string with aliased blocks for splicing
				$original_block = substr( $stream, $block_start_pos, $block_end_pos - $block_start_pos );
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
				$stream =
					substr( $stream, 0, $block_start_pos ) .
					$blocks .
					substr( $stream, $block_end_pos );

				// Move the regex pointer forward
				$scan_pos = $block_start_pos + strlen( $blocks );

			} // while

		} // foreach
		return $stream;
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

		$rule = new CssCrush_Rule( $match[1], $match[2] );

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

	public static function splitDelimList ( $str, $delim, $fold_in = false, $allow_empty = false ) {
		$match_obj = self::matchAllParens( $str );
		$match_obj->list = explode( $delim, $match_obj->string );
		if ( false === $allow_empty ) {
			$match_obj->list = array_filter( $match_obj->list );
		}
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