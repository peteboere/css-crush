<?php
/**
 *
 * Main script. Includes core public API
 *
 */

class csscrush {

	// Path information, global settings
	public static $config;

	// Properties available to each process
	public static $process;
	public static $storage;

	// Internal
	protected static $assetsLoaded = false;
	protected static $tokenUID;


	// Init called once manually post class definition
	public static function init ( $seed_file ) {

		self::$config = new stdclass();

		// Path to this installation
		self::$config->location = dirname( $seed_file );

		// Get version ID from seed file
		$seed_file_contents = file_get_contents( $seed_file );
		$match_count = preg_match( '!@version\s+([\d\.\w-]+)!', $seed_file_contents, $version_match );
		self::$config->version = $match_count ? new csscrush_version( $version_match[1] ) : null;

		// Set the docRoot reference
		self::setDocRoot();

		// Set the default IO handler
		self::$config->io = 'csscrush_io';

		// Global storage
		self::$config->vars = array();
		self::$config->aliases = array();
		self::$config->aliasesRaw = array();
		self::$config->plugins = array();

		// Default options
		self::$config->options = (object) array(

			// Minify. Set true for formatting and comments
			'debug' => false,

			// Append 'checksum' to output file name
			'versioning' => true,

			// Use the template boilerplate
			'boilerplate' => true,

			// Variables passed in at runtime
			'vars' => array(),

			// Enable/disable the cache
			'cache' => true,

			// Output file. Defaults the host-filename
			'output_file' => null,

			// Vendor target. Only apply prefixes for a specific vendor, set to 'none' for no prefixes
			'vendor_target' => 'all',

			// Whether to rewrite the url references inside imported files
			'rewrite_import_urls' => true,

			// List of plugins to enable (as Array of names)
			'enable' => null,

			// List of plugins to disable (as Array of names)
			'disable' => null,

			// Output sass debug-info stubs that work with development tools like FireSass.
			'trace' => false,
		);

		// Initialise other classes
		csscrush_regex::init();
		csscrush_function::init();
	}


	public static function setDocRoot ( $doc_root = null ) {

		// Get document_root reference
		// $_SERVER['DOCUMENT_ROOT'] is unreliable in certain CGI/Apache/IIS setups

		if ( ! $doc_root ) {

			$script_filename = $_SERVER[ 'SCRIPT_FILENAME' ];
			$script_name = $_SERVER[ 'SCRIPT_NAME' ];

			if ( $script_filename && $script_name ) {

				$len_diff = strlen( $script_filename ) - strlen( $script_name );

				// We're comparing the two strings so normalize OS directory separators
				$script_filename = str_replace( '\\', '/', $script_filename );
				$script_name = str_replace( '\\', '/', $script_name );

				// Check $script_filename ends with $script_name
				if ( substr( $script_filename, $len_diff ) === $script_name ) {

					$doc_root = realpath( substr( $script_filename, 0, $len_diff ) );
				}
			}

			if ( ! $doc_root ) {

				// If doc_root is still falsy, fallback to DOCUMENT_ROOT
				$doc_root = realpath( $_SERVER[ 'DOCUMENT_ROOT' ] );
			}

			if ( ! $doc_root ) {

				// If doc_root is still falsy, log an error
				$error = "Could not get a document_root reference.";
				csscrush::logError( $error );
				trigger_error( __METHOD__ . ": $error\n", E_USER_NOTICE );
			}
		}

		self::$config->docRoot = csscrush_util::normalizePath( $doc_root );
	}


	public static function io_call ( $method ) {

		// Fetch the argument list, shift off the first item
		$args = func_get_args();
		array_shift( $args );

		// The method address
		$the_method = array( self::$config->io, $method );

		// Return the call result
		return call_user_func_array( $the_method, $args );
	}


	// Aliases and macros loader
	protected static function loadAssets () {

		// Find an aliases file in the root directory
		// a local file overrides the default
		$aliases_file = csscrush_util::find( 'Aliases-local.ini', 'Aliases.ini' );

		// Load aliases file if it exists
		if ( $aliases_file ) {

			if ( $result = @parse_ini_file( $aliases_file, true ) ) {

				self::$config->aliasesRaw = $result;

				// Value aliases require a little preprocessing
				if ( isset( self::$config->aliasesRaw[ 'values' ] ) ) {
					$store = array();
					foreach ( self::$config->aliasesRaw[ 'values' ] as $prop_val => $aliases ) {
						list( $prop, $value ) = array_map( 'trim', explode( ':', $prop_val ) );
						$store[ $prop ][ $value ] = $aliases;
					}
					self::$config->aliasesRaw[ 'values' ] = $store;
				}

				// Ensure all alias groups are at least set (issue #34)
				self::$config->aliasesRaw += array(
					'properties' => array(),
					'functions'  => array(),
					'values'     => array(),
					'at-rules'   => array(),
				);
			}
			else {
				trigger_error( __METHOD__ . ": Aliases file could not be parsed.\n", E_USER_NOTICE );
			}
		}
		else {
			trigger_error( __METHOD__ . ": Aliases file not found.\n", E_USER_NOTICE );
		}

		// Find a plugins file in the root directory,
		// a local file overrides the default
		$plugins_file = csscrush_util::find( 'Plugins-local.ini', 'Plugins.ini' );

		// Load plugins
		if ( $plugins_file ) {
			if ( $result = @parse_ini_file( $plugins_file ) ) {
				foreach ( $result[ 'plugins' ] as $plugin_name ) {
					// Backwards compat.
					$plugin_name = basename( $plugin_name, '.php' );
					if ( csscrush_plugin::enable( $plugin_name ) ) {
						self::$config->plugins[ $plugin_name ] = true;
					}
				}
			}
			else {
				trigger_error( __METHOD__ . ": Plugin file could not be parsed.\n", E_USER_NOTICE );
			}
		}
	}


	// Establish the input and output directories and optionally test if output dir writable
	protected static function setPath ( $input_dir, $write_test = true ) {

		$config = self::$config;
		$process = self::$process;
		$doc_root = $config->docRoot;

		if ( strpos( $input_dir, $doc_root ) !== 0 ) {
			// Not a system path
			$input_dir = realpath( "$doc_root/$input_dir" );
		}

		// Store input directory
		$process->inputDir = $input_dir;
		$process->inputDirUrl = substr( $process->inputDir, strlen( $doc_root ) );

		// Store reference to the output dir
		$process->outputDir = csscrush::io_call( 'getOutputDir' );
		$process->outputDirUrl = substr( $process->outputDir, strlen( $doc_root ) );

		// Test the output directory to see if it's writable
		$pathtest = csscrush::io_call( 'testOutputDir', $write_test );

		// Setup the IO handler
		csscrush::io_call( 'init' );

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

		// Reset for current process
		self::reset( $options );

		$config = self::$config;
		$process = self::$process;
		$options = $process->options;
		$doc_root = $config->docRoot;

		// Since we're comparing strings, we need to iron out OS differences
		$file = str_replace( '\\', '/', $file );

		// Finding the system path of the input file and validating it
		$pathtest = true;
		if ( strpos( $file, $doc_root ) === 0 ) {
			// System path
			$pathtest = self::setPath( dirname( $file ) );
		}
		else if ( strpos( $file, '/' ) === 0 ) {
			// WWW root path
			$pathtest = self::setPath( dirname( $doc_root . $file ) );
		}
		else {
			// Relative path
			$pathtest = self::setPath( dirname( dirname( __FILE__ ) . '/' . $file ) );
		}

		if ( ! $pathtest ) {
			// Main directory not found or is not writable return an empty string
			return '';
		}

		// Get the input file object
		if ( ! ( $process->input = csscrush::io_call( 'getInput', $file ) ) ) {
			return '';
		}

		// Create a filename that will be used later
		// Used in validateCache, and writing to filesystem
		$process->outputFileName = csscrush::io_call( 'getOutputFileName' );

		// Caching.
		if ( $options->cache ) {

			// Load the cache data
			$process->cacheData = csscrush::io_call( 'getCacheData' );

			// If cache is enabled check for a valid compiled file
			$valid_compliled_file = csscrush::io_call( 'validateExistingOutput' );

			if ( is_string( $valid_compliled_file ) ) {
				return $valid_compliled_file;
			}
		}

		// Collate hostfile and imports.
		$stream = csscrush_importer::hostfile( $process->input );

		// Compile.
		$stream = self::compile( $stream );

		// Create file and return url. Return empty string on failure
		if ( file_put_contents( "$process->outputDir/$process->outputFileName", $stream ) ) {
			$timestamp = $options->versioning ? '?' . time() : '';
			return "$process->outputDirUrl/$process->outputFileName$timestamp";
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

		if ( ! empty( $file ) ) {

			// On success return the tag with any custom attributes
			$attributes[ 'rel' ] = 'stylesheet';
			$attributes[ 'href' ] = $file;

			// Should media type be forced to 'all'?
			if ( ! isset( $attributes[ 'media' ] ) ) {
				$attributes[ 'media' ] = 'all';
			}
			$attr_string = csscrush_util::htmlAttributes( $attributes );
			return "<link$attr_string />\n";
		}
		else {

			// Return an HTML comment with message on failure
			$class = __CLASS__;
			$errors = implode( "\n", self::$process->errors );
			return "<!-- $class: $errors -->\n";
		}
	}

	/**
	 * Process host CSS file and return CSS as text wrapped in html style tags
	 *
	 * @param string $file  Absolute or relative path to the host CSS file
	 * @param mixed $options  An array of options or null
	 * @param array $attributes  An array of HTML attributes, set false to return CSS text without tag
	 * @return string  HTML link tag or error message inside HTML comment
	 */
	public static function inline ( $file, $options = null, $attributes = array() ) {

		// For inline output set boilerplate to not display by default
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		if ( ! isset( $options[ 'boilerplate' ] ) ) {
			$options[ 'boilerplate' ] = false;
		}

		$file = self::file( $file, $options );

		if ( ! empty( $file ) ) {

			// On success fetch the CSS text
			$content = file_get_contents( self::$process->outputDir . '/' . self::$process->outputFileName );
			$tag_open = '';
			$tag_close = '';

			if ( is_array( $attributes ) ) {
				$attr_string = csscrush_util::htmlAttributes( $attributes );
				$tag_open = "<style$attr_string>";
				$tag_close = '</style>';
			}
			return "$tag_open{$content}$tag_close\n";
		}
		else {

			// Return an HTML comment with message on failure
			$class = __CLASS__;
			$errors = implode( "\n", self::$process->errors );
			return "<!-- $class: $errors -->\n";
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

		// For strings set boilerplate to not display by default
		if ( ! isset( $options[ 'boilerplate' ] ) ) {
			$options[ 'boilerplate' ] = false;
		}

		// Reset for current process
		self::reset( $options );

		$config = self::$config;
		$process = self::$process;
		$options = $process->options;

		// Set the path context if one is given.
		// Fallback to document root.
		if ( ! empty( $options->context ) ) {
			self::setPath( $options->context );
		}
		else {
			self::setPath( $config->docRoot );
		}

		// It's not associated with a real file so we create an 'empty' input object.
		$process->input = csscrush::io_call( 'getInput' );

		// Set the string on the object
		$process->input->string = $string;

		// Import files may be ignored
		if ( isset( $options->no_import ) ) {
			$process->input->importIgnore = true;
		}

		// Collate imports
		$stream = csscrush_importer::hostfile( $process->input );

		// Return compiled string
		return self::compile( $stream );
	}

	/**
	 * Add variables globally
	 *
	 * @param mixed $var  Assoc array of variable names and values, a php ini filename or null
	 */
	public static function globalVars ( $vars ) {

		$config = self::$config;

		// Merge into the stack, overrides existing variables of the same name
		if ( is_array( $vars ) ) {
			$config->vars = array_merge( $config->vars, $vars );
		}
		// Test for a file. If it is attempt to parse it
		elseif ( is_string( $vars ) && file_exists( $vars ) ) {
			if ( $result = @parse_ini_file( $vars ) ) {
				$config->vars = array_merge( $config->vars, $result );
			}
		}
		// Clear the stack if the argument is explicitly null
		elseif ( is_null( $vars ) ) {
			$config->vars = array();
		}
	}

	/**
	 * Clear config file and compiled files for the specified directory
	 *
	 * @param string $dir  System path to the directory
	 */
	public static function clearCache ( $dir = '' ) {
		return csscrush::io_call( 'clearCache', $dir );
	}


	#####################
	#  Developer related

	public static $logging = false;

	public static function log ( $arg = null, $label = '' ) {

		if ( ! self::$logging ) {
			return;
		}
		static $log = '';

		$args = func_get_args();
		if ( ! count( $args ) ) {
			// No arguments, return the log
			return $log;
		}

		if ( $label ) {
			$log .= "<h4>$label</h4>";
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

	public static function logError ( $msg ) {
		self::$process->errors[] = $msg;
		self::log( $msg );
	}


	#####################
	#  Internal functions

	public static function addTracingStubs ( &$stream ) {

		$selector_patt = '! (^|;|\})+ ([^;{}]+) (\{) !xmS';
		$token_or_whitespace = '!(\s*___c\d+___\s*|\s+)!';

		$matches = csscrush_regex::matchAll( $selector_patt, $stream );

		// Start from last match and move backwards.
		while ( $m = array_pop( $matches ) ) {

			// Shortcuts for readability.
			list( $full_match, $before, $content, $after ) = $m;
			$full_match_text  = $full_match[0];
			$full_match_start = $full_match[1];

			// The correct before string.
			$before = substr( $full_match_text, 0, $content[1] - $full_match_start );

			// Split the matched selector part.
			$content_parts = preg_split( $token_or_whitespace, $content[0], null,
				PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

			foreach ( $content_parts as $part ) {

				if ( ! preg_match( $token_or_whitespace, $part ) ) {

					// Match to a valid selector.
					if ( preg_match( '!^([^@]|@(?:page|abstract))!S', $part ) ) {

						// Count line breaks between the start of stream and
						// the matched selector to get the line number.
						$selector_index = $full_match_start + strlen( $before );
						$line_num = 1;
						$stream_before = "";
						if ( $selector_index ) {
							$stream_before = substr( $stream, 0, $selector_index );
							$line_num = substr_count( $stream_before, "\n" ) + 1;
						}

						// Get the currently processed file path, and escape it.
						$current_file = str_replace( ' ', '%20', csscrush::$process->currentFile );
						$current_file = preg_replace( '![^\w-]!', '\\\\$0', $current_file );

						// Splice in tracing stub.
						$label = csscrush::tokenLabelCreate( 't' );
						$stream = $stream_before . "$label" . substr( $stream, $selector_index );
						self::$storage->tokens->traces[ $label ]
							= "@media -sass-debug-info{filename{font-family:$current_file}line{font-family:\\00003$line_num}}";

					}
					else {
						// Not matched as a valid selector, move on.
						continue 2;
					}
					break;
				}

				// Append split segment to $before.
				$before .= $part;
			}
		}
	}

	public static function prepareStream ( &$stream ) {

		$regex = csscrush_regex::$patt;
		$process = csscrush::$process;
		$trace = $process->options->trace;

		$stream = preg_replace_callback( csscrush_regex::$patt->commentAndString,
			array( 'self', 'cb_extractCommentAndString' ), $stream );

		// If @charset is set store it.
		if ( preg_match( $regex->charset, $stream, $m ) ) {
			$replace = '';
			if ( ! $process->charset ) {
				$replace = str_repeat( "\n", substr_count( $m[0], "\n" ) );
				$process->charset = new csscrush_string( $m[1] );
			}
			$stream = preg_replace( $regex->charset, $replace, $stream );
		}

		// Catch obvious typing errors.
		$parse_errors = array();
		$current_file = $process->currentFile;
		$balanced_parens = substr_count( $stream, "(" ) === substr_count( $stream, ")" );
		$balanced_curlies = substr_count( $stream, "{" ) === substr_count( $stream, "}" );

		if ( ! $balanced_parens ) {
			$parse_errors[] = "Unmatched '(' in $current_file.";
		}
		if ( ! $balanced_curlies ) {
			$parse_errors[] = "Unmatched '{' in $current_file.";
		}

		if ( $parse_errors ) {
			foreach ( $parse_errors as $error_msg ) {
				csscrush::logError( $error_msg );
				trigger_error( "$error_msg\n", E_USER_WARNING );
			}
			return false;
		}

		// Optionally add tracing stubs.
		if ( $trace ) {
			self::addTracingStubs( $stream );
		}

		$stream = csscrush_util::normalizeWhiteSpace( $stream );

		return true;
	}

	protected static function getBoilerplate () {

		$file = false;
		$boilerplate_option = self::$process->options->boilerplate;

		if ( $boilerplate_option === true ) {
			$file = csscrush_util::find(
				'CssCrush-local.boilerplate', 'CssCrush.boilerplate' );
		}
		elseif ( is_string( $boilerplate_option ) ) {
			if ( file_exists( $boilerplate_option ) ) {
				$file = $boilerplate_option;
			}
		}

		// Return an empty string if no file is found.
		if ( ! $file ) {
			return '';
		}

		// Load the file
		$boilerplate = file_get_contents( $file );

		// Substitute any tags
		if ( preg_match_all( '!\{\{([^}]+)\}\}!', $boilerplate, $boilerplate_matches ) ) {

			$replacements = array();
			foreach ( $boilerplate_matches[0] as $index => $tag ) {
				$tag_name = $boilerplate_matches[1][$index];
				if ( $tag_name === 'datetime' ) {
					$replacements[] = @date( 'Y-m-d H:i:s O' );
				}
				elseif ( $tag_name === 'version' ) {
					$replacements[] = 'v' . csscrush::$config->version;
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

	protected static function getOptions ( $options ) {

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		// Keeping track of global vars internally to maintain cache integrity
		$options[ '_globalVars' ] = self::$config->vars;

		// Populate unset options with defaults
		$options += (array) self::$config->options;

		return (object) $options;
	}

	protected static function pruneAliases () {

		$options = self::$process->options;

		// If a vendor target is given, we prune the aliases array
		$vendor = $options->vendor_target;

		// Default vendor argument, use all aliases as normal
		if ( 'all' === $vendor ) {
			return;
		}

		// For expicit 'none' argument turn off aliases
		if ( 'none' === $vendor ) {
			self::$config->aliases = null;
			return;
		}

		// Normalize vendor_target argument
		$vendor = '-' . str_replace( '-', '', $vendor ) . '-';

		// Loop the aliases array, filter down to the target vendor
		foreach ( self::$config->aliases as $group_name => $group_array ) {

			// Property/value aliases are a special case
			if ( 'values' === $group_name ) {
				foreach ( $group_array as $property => $values ) {
					$result = array();
					foreach ( $values as $value => $prefix_values ) {
						foreach ( $prefix_values as $prefix ) {
							if ( strpos( $prefix, $vendor ) === 0 ) {
								$result[] = $prefix;
							}
						}
					}
					self::$config->aliases[ 'values' ][ $property ][ $value ] = $result;
				}
				continue;
			}
			foreach ( $group_array as $alias_keyword => $prefix_array ) {

				$result = array();
				foreach ( $prefix_array as $prefix ) {
					if ( strpos( $prefix, $vendor ) === 0 ) {
						$result[] = $prefix;
					}
				}
				// Prune the whole alias keyword if there is no result
				if ( empty( $result ) ) {
					unset( self::$config->aliases[ $group_name ][ $alias_keyword ] );
				}
				else {
					self::$config->aliases[ $group_name ][ $alias_keyword ] = $result;
				}
			}
		}
	}

	protected static function calculateVariables () {

		$options = self::$process->options;

		// In-file variables override global variables
		// Runtime variables override in-file variables
		self::$storage->variables = array_merge( self::$config->vars, self::$storage->variables );

		if ( ! empty( $options->vars ) ) {
			self::$storage->variables = array_merge(
				self::$storage->variables, $options->vars );
		}

		// Place variables referenced inside variables
		// Excecute any custom functions
		foreach ( self::$storage->variables as $name => &$value ) {
			// Referenced variables
			$value = preg_replace_callback(
				csscrush_regex::$patt->varFunction, array( 'self', 'cb_placeVariables' ), $value );

			// Custom functions:
			//   Variable values can be escaped from function parsing with a tilde prefix
			if ( strpos( $value, '~' ) === 0 ) {
				$value = ltrim( $value, "!\t\r " );
			}
			else {
				csscrush_function::executeCustomFunctions( $value );
			}
		}
	}

	protected static function placeVariables ( &$stream ) {

		// Substitute simple case variables
		$stream = preg_replace_callback(
			csscrush_regex::$patt->varFunction, array( 'self', 'cb_placeVariables' ), $stream );

		// Substitute variables with default values
		$var_fn_patt = csscrush_regex::createFunctionMatchPatt( array( '$' ) );
		$var_fn_callback = array( '$' => array( 'csscrush', 'cb_varFunctionWithDefault' ) );
		csscrush_function::executeCustomFunctions( $stream, $var_fn_patt, $var_fn_callback );

		// Repeat above steps for variables embedded in string tokens
		foreach ( self::$storage->tokens->strings as $label => &$string ) {

			if ( strpos( $string, '$(' ) !== false ) {

				$string = preg_replace_callback(
					csscrush_regex::$patt->varFunction,
						array( 'self', 'cb_placeVariables' ), $string );
				csscrush_function::executeCustomFunctions( $string, $var_fn_patt, $var_fn_callback );
			}
		}
	}

	protected static function reset ( $options = null ) {

		// Reset properties for current process
		self::$tokenUID = 0;

		self::$process = (object) array();
		self::$process->cacheData = array();
		self::$process->mixins = array();
		self::$process->abstracts = array();
		self::$process->errors = array();
		self::$process->selectorRelationships = array();
		self::$process->charset = null;
		self::$process->currentFile = null;
		self::$process->options = self::getOptions( $options );

		self::$storage = (object) array();
		self::$storage->tokens = (object) array(
			'strings'   => array(),
			'comments'  => array(),
			'rules'     => array(),
			'parens'    => array(),
			'mixinArgs' => array(),
			'urls'      => array(),
			'traces'    => array(),
		);
		self::$storage->variables = array();
		self::$storage->misc = (object) array();
	}

	protected static function compile ( $stream ) {

		$config = self::$config;
		$process = self::$process;
		$options = $process->options;

		// Load in aliases and plugins.
		if ( ! self::$assetsLoaded ) {
			self::loadAssets();
			self::$assetsLoaded = true;
		}

		// Load and unload plugins.
		// First copy the base $config->plugins list.
		$process->plugins = $config->plugins;

		// Add option enabled plugins to the list.
		if ( is_array( $options->enable ) ) {
			foreach ( $options->enable as $plugin_name ) {
				$process->plugins[ $plugin_name ] = true;
			}
		}

		// Remove option disabled plugins from the list, and disable them.
		if ( $options->disable === 'all' ) {
			$options->disable = array_keys( $config->plugins );
		}
		if ( is_array( $options->disable ) ) {
			foreach ( $options->disable as $plugin_name ) {
				csscrush_plugin::disable( $plugin_name );
				unset( $process->plugins[ $plugin_name ] );
			}
		}

		// Enable all plugins in the remaining list.
		foreach ( $process->plugins as $plugin_name => $bool ) {
			csscrush_plugin::enable( $plugin_name );
		}

		// Set aliases.
		self::$config->aliases = self::$config->aliasesRaw;

		// Prune if a vendor target is set.
		self::pruneAliases();

		// Parse variables.
		self::extractVariables( $stream );

		// Calculate the variable stack.
		self::calculateVariables();

		// Apply variables.
		self::placeVariables( $stream );

		// Resolve @ifdefine blocks.
		self::resolveIfDefines( $stream );

		// Pull out @mixin definitions.
		self::extractMixins( $stream );

		// Pull out @fragment blocks, and invoke.
		self::resolveFragments( $stream );

		// Adjust the stream so we can extract the rules cleanly.
		$map = array(
			'@' => "\n@",
			'}' => "}\n",
			'{' => "{\n",
			';' => ";\n",
		);
		$stream = "\n" . str_replace( array_keys( $map ), array_values( $map ), $stream );

		// Parse rules.
		self::extractRules( $stream );

		// Process @in blocks.
		self::prefixSelectors( $stream );

		// Main processing on the rule objects.
		self::processRules();

		// Alias any @-rules.
		self::aliasAtRules( $stream );

		// Print it all back.
		self::display( $stream );

		// Add in boilerplate.
		if ( $options->boilerplate ) {
			$stream = self::getBoilerplate() . "\n$stream";
		}

		// Add @charset at top if set.
		if ( $process->charset ) {
			$stream = "@charset $process->charset;\n" . $stream;
		}

		// Release memory.
		self::$storage = null;
		$process->mixins = null;
		$process->abstracts = null;
		$process->selectorRelationships = null;

		return $stream;
	}

	protected static function display ( &$stream ) {

		$options = self::$process->options;
		$minify = ! $options->debug;
		$regex = csscrush_regex::$patt;

		if ( $minify ) {
			$stream = csscrush_util::stripCommentTokens( $stream );
		}
		else {
			// Formatting
			$stream = preg_replace( '!([{}])!', "$1\n", $stream );
			$stream = preg_replace( '!([@])!', "\n$1", $stream );

			// Newlines after some tokens
			$stream = preg_replace( '!(___[rc][0-9]+___)!', "$1\n", $stream );

			// Kill double spaces
			$stream = ltrim( preg_replace( '!\n+!', "\n", $stream ) );
		}

		// Kill leading space
		$stream = preg_replace( '!\n\s+!', "\n", $stream );

		// Print out rules
		$stream = preg_replace_callback( $regex->ruleToken, array( 'self', 'cb_printRule' ), $stream );

		// Insert parens
		$stream = csscrush_util::strReplaceHash( $stream, self::$storage->tokens->parens );

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

		// Insert URLs
		if ( self::$storage->tokens->urls ) {

			// Clean-up rewritten URLs
			foreach ( csscrush::$storage->tokens->urls as $token => $url ) {

				// Optionally set the URLs to absolute
				if (
					$options->rewrite_import_urls === 'absolute' &&
					strpos( $url, 'data:' ) !== 0
				) {
					$url = self::$process->inputDirUrl . '/' . $url;
				}
				csscrush::$storage->tokens->urls[ $token ] = csscrush_util::cleanUpUrl( $url );
			}
			$stream = csscrush_util::strReplaceHash( $stream, self::$storage->tokens->urls );
		}

		// Insert string literals
		$stream = csscrush_util::strReplaceHash( $stream, self::$storage->tokens->strings );

	}

	protected static function minify ( $str ) {
		$replacements = array(
			'!\n+| (\{)!'                         => '$1',    // Trim whitespace
			'!(^|[: \(,])0(\.\d+)!'               => '$1$2',  // Strip leading zeros on floats
			'!(^|[: \(,])\.?0(?:e[mx]|c[hm]|rem|v[hwm]|in|p[tcx])!i'
			                                      => '${1}0', // Strip unnecessary units on zero values for length types
			'!(^|\:) *(0 0 0|0 0 0 0) *(;|\})!'   => '${1}0${3}', // Collapse zero lists
			'!(padding|margin) ?\: *0 0 *(;|\})!' => '${1}:0${2}', // Collapse zero lists continued
			'!\s*([>~+=])\s*!'                    => '$1',     // Clean-up around combinators
			'!\#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3!i'
			                                      => '#$1$2$3', // Compress hex codes
		);
		return preg_replace(
			array_keys( $replacements ), array_values( $replacements ), $str );
	}

	protected static function aliasAtRules ( &$stream ) {

		if ( empty( self::$config->aliases[ 'at-rules' ] ) ) {
			return;
		}

		$aliases = self::$config->aliases[ 'at-rules' ];

		foreach ( $aliases as $at_rule => $at_rule_aliases ) {
			if (
				strpos( $stream, "@$at_rule " ) === -1 ||
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
				$curly_match = csscrush_util::matchBrackets( $stream, $brackets = array( '{', '}' ), $block_start_pos );

				if ( ! $curly_match ) {
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
					preg_match( csscrush_regex::$patt->vendorPrefix, $alias, $vendor );

					$vendor = $vendor ? $vendor[1] : null;

					// Duplicate rules
					if ( preg_match_all( csscrush_regex::$patt->ruleToken, $copy_block, $copy_matches ) ) {
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
								if ( ! $declaration->vendor || $declaration->vendor === $vendor ) {
									$new_set[] = $declaration;
								}
							}
							$cloneRule->declarations = $new_set;

							// Store the clone
							$replacements[] = $clone_rule_label = self::tokenLabelCreate( 'r' );
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
	}

	protected static function prefixSelectors ( &$stream ) {

		$matches = csscrush_regex::matchAll( '@in\s+([^{]+){', $stream, true );

		// Move through the matches in reverse order
		while ( $match = array_pop( $matches ) ) {

			list( $match_string, $match_start_pos ) = $match[0];
			$match_length = strlen( $match_string );

			$before = substr( $stream, 0, $match_start_pos );

			$raw_argument = trim( $match[1][0] );

			$arguments = csscrush_util::splitDelimList( $match[1][0], ',', false, true );
			$arguments = $arguments->list;

			$curly_match = csscrush_util::matchBrackets(
								$stream, array( '{', '}' ), $match_start_pos, true );

			if ( ! $curly_match || empty( $raw_argument ) ) {
				// Couldn't match the block
				$stream = $before . substr( $stream, $match_start_pos + $match_length );
				continue;
			}

			// Match all the rule tokens
			$rule_matches = csscrush_regex::matchAll(
								csscrush_regex::$patt->ruleToken,
								$curly_match->inside );

			foreach ( $rule_matches as $rule_match ) {

				// Get the rule instance
				$rule = csscrush_rule::get( $rule_match[0][0] );

				// Set the isNested flag
				$rule->isNested = true;

				// Using arguments create new selector list for the rule
				$new_selector_list = array();

				foreach ( $arguments as $arg_selector ) {

					foreach ( $rule->selectorList as $rule_selector ) {

						if ( ! $rule_selector->allowPrefix ) {

							$new_selector_list[ $rule_selector->readableValue ] = $rule_selector;
						}
						elseif ( strpos( $rule_selector->value, '&' ) !== false ) {

							// Ampersand is the positional symbol for where the
							// prefix will be placed

							// Find and replace (only once) the ampersand
							$new_value = preg_replace(
									'!&!',
									$arg_selector,
									$rule_selector->value,
									1 );

							// Not storing the selector as named
							$new_selector_list[] = new csscrush_selector( $new_value );
						}
						else {

							// Not storing the selector as named
							$new_selector_list[] = new csscrush_selector( "$arg_selector {$rule_selector->value}" );
						}
					}
				}
				$rule->selectorList = $new_selector_list;
			}

			// Concatenate
			$stream = $before . $curly_match->inside . $curly_match->after;
		}
	}

	public static function tokenLabelCreate ( $prefix ) {
		$counter = ++self::$tokenUID;
		return "___$prefix{$counter}___";
	}

	public static function processRules () {

		// Reset the selector relationships
		self::$process->selectorRelationships = array();

		$aliases =& self::$config->aliases;

		foreach ( self::$storage->tokens->rules as $rule ) {

			// Store selector relationships
			$rule->indexSelectors();

			csscrush_hook::run( 'rule_prealias', $rule );

			if ( ! empty( $aliases[ 'properties' ] ) ) {
				$rule->addPropertyAliases();
			}
			if ( ! empty( $aliases[ 'functions' ] ) ) {
				$rule->addFunctionAliases();
			}
			if ( ! empty( $aliases[ 'values' ] ) ) {
				$rule->addValueAliases();
			}

			csscrush_hook::run( 'rule_postalias', $rule );

			$rule->expandSelectors();

			// Find previous selectors and apply them
			$rule->applyExtendables();

			csscrush_hook::run( 'rule_postprocess', $rule );
		}
	}


	#############################
	#  preg_replace callbacks

	protected static function cb_extractCommentAndString ( $match ) {

		$full_match = $match[0];

		// We return the newlines to maintain line numbering when tracing.
		$newlines = str_repeat( "\n", substr_count( $full_match, "\n" ) );

		if ( strpos( $full_match, '/*' ) === 0 ) {

			// Strip private comments
			$private_comment_marker = '$!';

			if (
				strpos( $full_match, '/*' . $private_comment_marker ) === 0 ||
				! self::$process->options->debug
			) {
				return $newlines;
			}

			$label = self::tokenLabelCreate( 'c' );

			// Fix broken comments as they will break any subsquent
			// imported files that are inlined.
			if ( ! preg_match( '!\*/$!', $full_match ) ) {
				$full_match .= '*/';
			}
			self::$storage->tokens->comments[ $label ] = $full_match;
		}
		else {

			$label = csscrush::tokenLabelCreate( 's' );

			// Fix broken strings as they will break any subsquent
			// imported files that are inlined.
			if ( $full_match[0] !== $full_match[ strlen( $full_match )-1 ] ) {
				$full_match .= $full_match[0];
			}
			csscrush::$storage->tokens->strings[ $label ] = $full_match;
		}

		return $newlines . $label;
	}

	protected static function cb_extractMixins ( $match ) {

		$name = trim( $match[1] );
		$block = trim( $match[2] );

		if ( ! empty( $name ) && ! empty( $block ) ) {
			self::$process->mixins[ $name ] = new csscrush_mixin( $block );
		}

		return '';
	}

	protected static function cb_extractVariables ( $match ) {

		$regex = csscrush_regex::$patt;

		$block = $match[2];

		// Strip comment markers
		$block = csscrush_util::stripCommentTokens( $block );

		// Need to split safely as there are semi-colons in data-uris
		$variables_match = csscrush_util::splitDelimList( $block, ';', true );

		// Loop through the pairs
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

		$variable_name = $match[1];

		if ( isset( self::$storage->variables[ $variable_name ] ) ) {
			return self::$storage->variables[ $variable_name ];
		}
	}

	public static function cb_varFunctionWithDefault ( $raw_argument ) {

		list( $name, $default_value ) = csscrush_function::parseArgsSimple( $raw_argument );

		if ( isset( self::$storage->variables[ $name ] ) ) {

			return self::$storage->variables[ $name ];
		}
		else {
			return $default_value;
		}
	}

	protected static function cb_extractRules ( $match ) {

		$rule = (object) array();
		$rule->selector_raw = trim( $match[1] );
		$rule->declaration_raw = trim( $match[2] );

		csscrush_hook::run( 'rule_preprocess', $rule );

		$rule = new csscrush_rule( $rule->selector_raw, $rule->declaration_raw );

		// Store rules if they have declarations or extend arguments
		if ( count( $rule ) || $rule->extendArgs ) {

			$label = $rule->label;

			self::$storage->tokens->rules[ $label ] = $rule;

			// If only using extend still return a label
			return $label . "\n";
		}
		return '';
	}

	protected static function cb_printRule ( $match ) {

		$minify = ! self::$process->options->debug;
		$whitespace = $minify ? '' : ' ';

		$ruleLabel = $match[0];

		// If no rule matches the label return empty string
		if ( ! isset( self::$storage->tokens->rules[ $ruleLabel ] ) ) {
			return '';
		}

		$rule =& self::$storage->tokens->rules[ $ruleLabel ];

		// Tracing stubs.
		$tracing_stub = '';
		if ( $rule->tracingStub ) {
			$tracing_stub =& self::$storage->tokens->traces[ $rule->tracingStub ];
		}

		// If there are no selectors or declarations associated with the rule return empty string
		if ( empty( $rule->selectorList ) || ! count( $rule ) ) {
			return '';
		}

		// Build the selector; uses selector __toString method
		$selectors = implode( ",$whitespace", $rule->selectorList );

		// Build the block
		$block = array();
		foreach ( $rule as $declaration ) {
			$important = $declaration->important ? "$whitespace!important" : '';
			$block[] = "$declaration->property:{$whitespace}$declaration->value{$important}";
		}

		// Return whole rule
		if ( $minify ) {
			$block = implode( ';', $block );
			return "$tracing_stub$selectors{{$block}}";
		}
		else {
			// Include pre-rule comments.
			$comments = implode( "\n", $rule->comments );
			if ( $tracing_stub ) {
				$tracing_stub .= "\n";
			}
			$block = implode( ";\n\t", $block );
			return "$comments\n$tracing_stub$selectors {\n\t$block;\n\t}\n";
		}
	}


	############
	#  Parsing methods

	public static function extractRules ( &$stream ) {
		$stream = preg_replace_callback( csscrush_regex::$patt->rule, array( 'self', 'cb_extractRules' ), $stream );
	}

	public static function extractVariables ( &$stream ) {
		$stream = preg_replace_callback( csscrush_regex::$patt->variables, array( 'self', 'cb_extractVariables' ), $stream );
	}

	public static function extractMixins ( &$stream ) {
		$stream = preg_replace_callback( csscrush_regex::$patt->mixin, array( 'self', 'cb_extractMixins' ), $stream );
	}

	public static function resolveFragments ( &$stream ) {

		$matches = csscrush_regex::matchAll( '@fragment\s+(<ident>)\s*{', $stream, true );
		$fragments = array();

		// Move through the matches last to first
		while ( $match = array_pop( $matches ) ) {

			list( $match_string, $match_start_pos ) = $match[0];
			$fragment_name = $match[1][0];

			$match_length = strlen( $match_string );
			$before = substr( $stream, 0, $match_start_pos );

			$curly_match = csscrush_util::matchBrackets(
								$stream, $brackets = array( '{', '}' ), $match_start_pos, true );

			if ( ! $curly_match ) {
				// Couldn't match the block
				$stream = $before . substr( $stream, $match_start_pos + $match_length );
				continue;
			}
			else {
				// Recontruct the stream without the fragment
				$stream = $before . $curly_match->after;

				// Create the fragment and store it
				$fragments[ $fragment_name ] =
						new csscrush_fragment( $curly_match->inside );
			}
		}

		// Now find all the fragment calls
		$matches = csscrush_regex::matchAll( '@fragment\s+(<ident>)\s*(\(|;)', $stream, true );

		// Move through the matches last to first
		while ( $match = array_pop( $matches ) ) {

			list( $match_string, $match_start_pos ) = $match[0];
			$match_length = strlen( $match_string );
			$before = substr( $stream, 0, $match_start_pos );

			// The matched fragment name
			$fragment_name = $match[1][0];

			// The fragment object, or null if name not present
			$fragment = isset( $fragments[ $fragment_name ] ) ? $fragments[ $fragment_name ] : null;

			// Fragment may be called without any argument list
			$with_arguments = $match[2][0] === '(';

			if ( $with_arguments ) {
				$paren_match = csscrush_util::matchBrackets(
								$stream, $brackets = array( '(', ')' ), $match_start_pos, true );
				$after = ltrim( $paren_match->after, ';' );
			}
			else {
				$after = substr( $stream, $match_start_pos + $match_length );
			}

			if ( ! $fragment || ( $with_arguments && ! $paren_match ) ) {

				// Invalid fragment, or malformed argument list
				$stream = $before . substr( $stream, $match_start_pos + $match_length );
				continue;
			}
			else {

				$args = array();
				if ( $with_arguments ) {
					// Get the argument array to pass to the fragment
					$args = csscrush_util::splitDelimList( $paren_match->inside, ',', true, true );
					$args = $args->list;
				}

				// Execute the fragment and get the return value
				$fragment_return = $fragment->call( $args );

				// Recontruct the stream with the fragment return value
				$stream = $before . $fragment_return . $after;
			}
		}
	}

	public static function resolveIfDefines ( &$stream ) {

		$matches = csscrush_regex::matchAll( '@ifdefine\s+(not\s+)?(<ident>)\s*\{', $stream, true );

		// Move through the matches last to first.
		while ( $match = array_pop( $matches ) ) {

			$full_match = $match[0][0];
			$full_match_start = $match[0][1];
			$before = substr( $stream, 0, $full_match_start );

			$negate = $match[1][1] != -1;
			$name = $match[2][0];
			$name_defined = isset( self::$storage->variables[ $name ] );

			$curly_match = csscrush_util::matchBrackets(
								$stream, $brackets = array( '{', '}' ), $full_match_start, true );

			if ( ! $curly_match ) {
				// Couldn't match the block.
				$stream = $before . substr( $stream, $full_match_start + strlen( $full_match ) );
				continue;
			}

			if ( ! $negate && $name_defined || $negate && ! $name_defined ) {
				// Test resolved true so include the innards.
				$stream = $before . $curly_match->inside . $curly_match->after;
			}
			else {
				// Recontruct the stream without the innards.
				$stream = $before . $curly_match->after;
			}
		}
	}

}


#######################
#  Procedural style API

function csscrush_file ( $file, $options = null ) {
	return csscrush::file( $file, $options );
}
function csscrush_tag ( $file, $options = null, $attributes = array() ) {
	return csscrush::tag( $file, $options, $attributes );
}
function csscrush_inline ( $file, $options = null, $attributes = array() ) {
	return csscrush::inline( $file, $options, $attributes );
}
function csscrush_string ( $string, $options = null ) {
	return csscrush::string( $string, $options );
}
function csscrush_globalvars ( $vars ) {
	return csscrush::globalVars( $vars );
}
function csscrush_clearcache ( $dir = '' ) {
	return csscrush::clearcache( $dir );
}



