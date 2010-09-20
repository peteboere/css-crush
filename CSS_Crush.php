<?php
/*

CSS Crush

@example

<?php 

include 'CSS_Crush.php'; 
$path_to_compiled_file = CSS_Crush::file( 'screen.css' );

?>

<link rel="stylesheet" type="text/css" href="<?php echo $path_to_compiled_file; ?>" media="screen" />

*/
class CSS_Crush {
	
	private static $config;

	// Properties available to each 'file' process 
	private static $options;
	private static $compiledname;
	private static $variables;
	private static $literals;
	private static $literalCount;
	
	// Pattern matching 
	static private $regex = array( 
		'imports'  => '#@import +url *\(? *([\'"])?(.+\.css)\1? *\)? *;?#',
		'variables'=> '#@variables\s+\{\s*(.*?)\s*\};?#s',
		'comments' => '#/\*(.*?)\*/#s',
	);
	
	// Init gets called at the top of any public methods
	private static $initialized = false;
	private static function init () {
		self::$initialized = true;
		self::$config = new stdClass;
		self::$config->file = '.' . __CLASS__;
		self::$config->data = null;
		self::$regex = (object) self::$regex;
		// We set to current directory by default
		self::setPath( dirname( __FILE__ ) );
	}
	
	// Initialize config data, create config file if needed
	private static function loadConfig () {
		if ( self::$config->data and file_exists( self::$config->path ) ) {
			// Already loaded and config file exists in the current directory
			return;
		}
		else if ( file_exists( self::$config->path ) ) {
			// Load from file
			self::$config->data = unserialize( file_get_contents( self::$config->path ) );
		}
		else {
			// Create
			self::log( 'Creating config file' );
			file_put_contents( self::$config->path, serialize( array() ) );
			self::$config->data = array();
		}
	}
	
	private static function setPath ( $new_dir ) {
		if ( !self::$initialized ) { self::init(); }
		$docRoot = $_SERVER[ 'DOCUMENT_ROOT' ];
		if ( strpos( $new_dir, $docRoot ) !== 0 ) {
			$new_dir = realpath( "{$docRoot}/{$new_dir}" );
		}
		if ( !file_exists( $new_dir ) ) {
			throw new Exception( __METHOD__ . ': Path "' . $new_dir . '" doesn\'t exist' );
		}
		else if ( !is_writable( $new_dir ) ) {
			self::log( 'Attempting to change permissions' );
			try {
				chmod( $new_dir, 0755 );
			}
			catch ( Exception $e ) {
				throw new Exception( __METHOD__ . ': Directory unwritable' );
			}
			self::log( 'Permissions updated' );
		} 
		self::$config->path = "{$new_dir}/" . self::$config->file;
		self::$config->baseDir = $new_dir;
		self::$config->baseURL = substr( $new_dir, strlen( $docRoot ) );
	}
	
	
################################################################################################
#    Public API
################################################################################################

	public static function file ( $hostfile, $options = null ) {
		if ( !self::$initialized ) { self::init(); }
		if ( strpos( $hostfile, '/' ) === 0 ) {
			// Absolute path, so call set path to change setting 
			self::setPath( dirname( $_SERVER[ 'DOCUMENT_ROOT' ] . $hostfile ) );
			// Also just use filename since 
			$hostfile = basename( $hostfile );
		} 

		self::loadConfig();
		$config =& self::$config;
		
		// Create default options for those not set
		$option_defaults = array(
			'macros'   => true,
			'comments' => false,
			'minify'   => true,
		);
		self::$options = $options = is_array( $options ) ? 
			array_merge( $option_defaults, $options ) : $option_defaults;
		
		// Make basic information about the hostfile accessible
		$hostfile = (object) array( 'name' => $hostfile );
		$hostfile->path = "{$config->baseDir}/{$hostfile->name}";
		$hostfile->mtime = filemtime( $hostfile->path );
		
		if ( !file_exists( $hostfile->path ) ) {
			// If host file doesn't exist simply return an empty string 
			return '';
		}
		
		// File we're looking for
		self::$compiledname = 
			basename( str_replace( '/', '-', $hostfile->name ), '.css' ) . '.crush.css';
		
		// Search base directory for an existing compiled file
		foreach ( scandir( $config->baseDir ) as $filename ) {
			if ( self::$compiledname == $filename ) {
				// Cached file exists
				self::log( 'Cached file exists' );
				$existingfile = new stdClass;
				$existingfile->name = $filename;
				$existingfile->path = "{$config->baseDir}/{$existingfile->name}";
				$existingfile->URL = "{$config->baseURL}/{$existingfile->name}";

				// Start off with the host file then add imported files
				$all_files = array( $hostfile->mtime );
				
				if ( file_exists( $existingfile->path ) and isset( $config->data[ self::$compiledname ] ) ) {
					// File exists and has config
					foreach ( $config->data[ $existingfile->name ][ 'imports' ] as $import_file ) {
						$import_filepath = "{$config->baseDir}/{$import_file}";
						if ( file_exists( $import_filepath ) ) {
							$all_files[] = filemtime( $import_filepath );
						}
						else {
							// File has been moved, remove old file and skip to compile
							self::log( 'Import file has been moved, removing existing file' );
							unlink( $existingfile->path );
							break 2;
						}
					} 
					if ( 
							$config->data[ $existingfile->name ][ 'options' ] == self::$options and
							array_sum( $all_files ) == $config->data[ $existingfile->name ][ 'datem_sum' ] 
					) {						
						// Files have not been modified and config is the same: return the old file
						self::log( 'Files have not been modified, returning existing file' );
						return $existingfile->URL;
					}
					else {
						// Remove old file and continue making a new one...
						self::log( 'Files has been modified, removing existing file' );
						unlink( $existingfile->path );
					}
				}
				else if ( file_exists( $existingfile_path ) ) {
					// File exists but has no config
					self::log( 'File exists but no config, removing existing file' );
					unlink( $existingfile->path );
				}
				break;
			} 
		}
		
		/////////////////////////////////
		// Compile
		
		// Reset properties for current process
		self::$literals = array();
		self::$variables = array();
		self::$literalCount = 0;
		$regex = self::$regex;
		
		// Collate hostfile and imports
		$output = self::collateImports( &$hostfile );
		
		// Extract literals
		$re = '#(\'|")(?:\\1|[^\1])*?\1#';
		$output = preg_replace_callback( $re, "self::cb_extractStrings", $output );
		
		// Extract comments
		$output = preg_replace_callback( $regex->comments, "self::cb_extractComments", $output );
			
		// Extract variables
		$output = preg_replace_callback( $regex->variables, "self::cb_extractVariables", $output );
		//self::log( self::$variables );
		
		// Search and replace variables
		$re = '#var\(\s*([A-Z0-9_-]+)\s*\)#i';
		$output = preg_replace_callback( $re, "self::cb_placeVariables", $output);
		
		// Optionally apply macros
		if ( $options[ 'macros' ] !== false ) {
			self::applyMacros( &$output );
		}
		
		// Optionally minify
		if ( $options[ 'minify' ] !== false ) {
			self::minify( &$output );
		}
		
		// Expand selectors
		$re = '#([^}{]+){#s';
		$output = preg_replace_callback( $re, "self::cb_expandSelector", $output);
		
		// Restore all comments
		$output = preg_replace_callback( '#(___c\d+___)#', "self::cb_restoreLiteral", $output);
		
		// Restore all literals
		$output = preg_replace_callback( '#(___\d+___)#', "self::cb_restoreLiteral", $output);
	
		// Release un-needed memory 
		self::$literals = self::$variables = null;
	
		// Add in boilerplate
		$output = <<<TXT
/* 
 *  File created by CSS Crush
 *  http://github.com/peteboere/css-crush
 */
{$output}
TXT;
		// Create file and return path. Return empty string on failure
		return file_put_contents( "{$config->baseDir}/" . self::$compiledname, $output ) ? 
					"{$config->baseURL}/" . self::$compiledname : '';
	}
	
	
	static public function clearCache () {
		if ( !self::$initialized ) { self::init(); }
		if ( file_exists( self::$config->path ) ) {
			unlink( self::$config->path );
		}
		// Remove any compiled files
		$suffix = '.crush.css';
		$suffixLength = strlen( $suffix );
		foreach ( scandir( self::$config->baseDir ) as $file ) {
			$expectedPos = strlen( $file ) - $suffixLength;
			if ( strpos( $file, $suffix ) === $expectedPos ) {
				unlink( self::$config->baseDir . "/{$file}" );
			}
		}
	}

################################################################################################
#    Internal functions
################################################################################################

	static private function collateImports ( &$hostfile ) {
		$str = file_get_contents( $hostfile->path );
		$config =& self::$config;
		$compiledname = self::$compiledname;
		$regex = self::$regex;
		
		// Obfuscate any directives within comment blocks
		$str = preg_replace_callback( $regex->comments, "self::cb_obfuscateDirectives", $str );
		
		// Initialize config object
		$config->data[ $compiledname ] = array();
		
		// Keep track of relative paths with nested imports
		$relativeContext = '';
		$imports_mtimes = array();
		$imports_filenames = array();
		
		while ( preg_match( $regex->imports, $str, $match, PREG_OFFSET_CAPTURE ) ) {
			// Matched a file import statement
			self::log( $match );
			$text = $match[0][0]; // Full match
			$offset = $match[0][1]; // Full match offset
			$import = new stdClass;
			$import->name = $match[2][0];
			$segments = array_filter( array( $config->baseDir, $relativeContext, $import->name ) );
			$import->path = implode( '/', $segments );
			
			self::log( $relativeContext );
			self::log( 'Import filepath: ' . $import->path );
			
			$preStatement  = substr( $str, 0, $offset );
			$postStatement = substr( $str, $offset + strlen( $text ) );
			
			if ( $import->content = @file_get_contents( $import->path ) ) {
				// Imported file exists, so construct new content
				
				// Add import details to config
				$imports_mtimes[] = filemtime( $import->path );
				$imports_filenames[] = $relativeContext ? 
					"{$relativeContext}/{$import->name}" : $import->name;
				
				// Obfuscate any directives within comment blocks
				$import_content = preg_replace_callback( 
					$regex->imports, "self::cb_obfuscateDirectives", $import->content );
				
				// Set relative context if there is a nested import statement
				if ( preg_match( $regex->imports, $import->content ) ) {
					$dirName = dirname( $import->name );
					if ( $dirName != '.' ) {
						$relativeContext = 
							!empty( $relativeContext ) ? "{$relativeContext}/{$dirName}" : $dirName;
					}
				}
				else {
					$relativeContext = '';
				}
				// Reconstruct the main string
				$str = "{$preStatement}\n{$import->content}\n{$postStatement}";
			}
			else {
				// Failed to open import, just continue with the import line removed
				self::log( 'File not found' );
				$str = "{$preStatement}\n{$postStatement}";
			}
		}
		
		$config->data[ $compiledname ][ 'imports' ] = $imports_filenames;
		$config->data[ $compiledname ][ 'datem_sum' ] = array_sum( $imports_mtimes ) + $hostfile->mtime;
		$config->data[ $compiledname ][ 'options' ] = self::$options;

		// Save config changes
		file_put_contents( $config->path, serialize( $config->data ) );
		self::log( $config->data );
		
		return $str;
	}

	static private function applyMacros ( &$str ) {
		$user_funcs = get_defined_functions();
		$csscrushs = array();
		foreach ( $user_funcs[ 'user' ] as $func ) {
			if ( strpos( $func, 'csscrush_' ) === 0 ) {
				// Put functions into groups
				$parts = explode( '_', $func );
				$property = implode( '-', array_slice( $parts, 2 ) );
				$csscrushs[ $parts[1] ][ $property ] = $func;
			} 
		}
		// Discriminate which groups to apply 
		// Then merge all enabled groups
		$opts = self::$options[ 'macros' ];
		$maclist = array();
		foreach ( $csscrushs as $group ) {
			if ( $opts === true or in_array( $group, $opts ) ) {
				$maclist = array_merge( $maclist, $group );
			}
		}
		// Loop macrolop list and apply callbacks
		foreach ( $maclist as $property => $callback ) {
			$wrapper = '$prop = "' . $property . '";' .
					'$result = ' . $callback . '( $prop, $match[2] );' .
					'return $match[1] . "\n" . $result . $match[3];';
			$str = preg_replace_callback( 
					'#([\{\s;]+)' . $property . '\s*:\s*' . '([^;\}]+)' . '([;\}])#', 
					create_function ( '$match', $wrapper ),
					$str );
		} 
	}
	
	static private function minify ( &$str ) {
		// Colons cannot be globally matched safely because of pseudo-selectors etc.
		$innerbrace = create_function(
			'$match',
			'return preg_replace( \'#\s*:\s*#\', \':\', $match[0] );' 
		);
		$str = preg_replace_callback( '#\{[^}]+\}#s', $innerbrace, trim( $str ) );
				
		$replacements = array(
			'#\s{2,}#'                          => ' ',      // Double spaces
			'#[^}{]+\{\s*}#'                    => '',       // Empty statements
			'#\s*(;|,|\{)\s*#'                  => '$1',
			'#\s*;?\s*\}\s*#'                   => '}',
			'#([^0-9])0[a-zA-Z]{2}#'            => '${1}0',  // unnecessary units on zeros
			'#:(0 0|0 0 0|0 0 0 0)([;}])#'      => ':0${2}', // unnecessary zeros
			'#(\[)\s*|\s*(\])|(\()\s*|\s*(\))#' => '${1}${2}${3}${4}',  // Bracket internal space
			'#\s*([>~+=])\s*#'                  => '$1',     // Combinators
		);

		$str = preg_replace( 
			array_keys( $replacements ), array_values( $replacements ), $str );
	}


################################################################################################
#    Search / replace callbacks
################################################################################################

	static private function cb_extractStrings ( $match ) {
		$label = "___" . ++self::$literalCount . "___";
		self::$literals[ $label ] = $match[0];
		return $label;
	}
	
	static private function cb_extractComments ( $match ) {
		$comment = $match[0];
		$flagged = strpos( $comment, '/*!' ) === 0;
		if ( self::$options[ 'comments' ] or $flagged ) {
			$label = "___c" . ++self::$literalCount . "___";
			self::$literals[ $label ] = $flagged ? '/*' . substr( $match[1], 1 ) . '*/' : $comment;
			return $label;			
		}
		return '';
	}
	
	static private function cb_extractVariables ( $match ) {
		$vars = preg_split( '#\s*;\s*#', $match[1], null, PREG_SPLIT_NO_EMPTY );
		foreach ( $vars as $var ) {
			$parts = preg_split( '#\s*:\s*#', $var, null, PREG_SPLIT_NO_EMPTY );
			self::$variables[ $parts[0] ] = $parts[1];
		}
		return '';
	}
	
	static private function cb_placeVariables ( $match ) {
		$key = $match[1];
		if ( isset( self::$variables[ $key ] ) ) {
			return self::$variables[ $key ];
		}
		else {
			return '';
		}
	}
	
	static private function cb_expandSelector_braces ( $match ) {
		$label = "__any" . ++self::$literalCount . "__";
		self::$literals[ $label ] = $match[1];
		return $label;
	}
	
	static private function cb_expandSelector ( $match ) {
		/* http://dbaron.org/log/20100424-any */
		$text = $match[0];
		$between = $match[1];
		if ( strpos( $between, ':any' ) === false ) {
			return $text;
		}
		$between = preg_replace_callback( 
			'#:any\(([^)]*)\)#', "self::cb_expandSelector_braces", $between );
		
		// Strip any comment labels, whoops
		$between = preg_replace( '#___c\d+___#', '', $between );
		
		$re_comma = '#\s*,\s*#';
		$matched_statements = preg_split( $re_comma, $between );

		$stack = array();
		foreach ( $matched_statements as $matched_statement ) {
			$pos = strpos( $matched_statement, '__any' );
			if ( $pos !== false ) {
				// Contains an :any statement so we expand 
				$chain = array( '' );
				do {
					if ( $pos === 0 ) {
						preg_match( '#__any\d+__#', $matched_statement, $m );
						$parts = preg_split( $re_comma, self::$literals[ $m[0] ] );
						$tmp = array();
						foreach ( $chain as $rowCopy ) {
							foreach ( $parts as $part ) {
								$tmp[] = $rowCopy . $part;
							}
						}
						$chain = $tmp;
						$matched_statement = substr( $matched_statement, strlen( $m[0] ) );
					}
					else {
						foreach ( $chain as &$row ) {
							$row .= substr( $matched_statement, 0, $pos );
						}
						$matched_statement = substr( $matched_statement, $pos );
					}
				} while ( ( $pos = strpos( $matched_statement, '__any' ) ) !== false );
				
				// Finish off
				foreach ( $chain as &$row ) {
					$stack[] = $row . $matched_statement;
				}
			}
			else {
				// Nothing special
				$stack[] = $matched_statement;
			}
		}
		//self::log( $stack);
		return implode( ",", $stack ) . '{';
	}
	
	static private function cb_obfuscateDirectives ( $match ) {
		return str_replace( '@', '(at)', $match[0] );
	}
	
	static private function cb_restoreLiteral ( $match ) {
		return self::$literals[ $match[0] ];
	}
	
	
################################################################################################
#    Logging / debugging
################################################################################################

	public static $debug = true;
	
	public static function log () {
		if ( !self::$debug ) {
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
	
}


################################################################################################
#    Macro callbacks ( user functions )
################################################################################################

///////////// IELegacy /////////////

// Fix opacity in ie6/7/8
function csscrush_ielegacy_Opacity ( $prop, $val ) {
	$msval = round( $val*100 );
	return "-ms-filter: \"progid:DXImageTransform.Microsoft.Alpha(Opacity={$msval})\";
			filter: progid:DXImageTransform.Microsoft.Alpha(Opacity={$msval});
			zoom:1;
			{$prop}: {$val}";	
}
// Fix display:inline-block in ie6/7
function csscrush_ielegacy_Display ( $prop, $val ) {
	if ( $val == 'inline-block' ) { 
		return "{$prop}:{$val};*{$prop}:inline;*zoom:1";
	}
	return "{$prop}:{$val}";
}
// Fix min-height in ie6
function csscrush_ielegacy_Min_Height ( $prop, $val ) {
	return "{$prop}:{$val};_height:{$val}";
}

///////////// CSS3 /////////////

// Border radius
function csscrush_css3_Border_Radius ( $prop, $val ) {
	return "-moz-{$prop}:{$val};{$prop}:{$val}";
}
function csscrush_css3_Border_Top_Left_Radius ( $prop, $val ) {
	return "-moz-border-radius-topleft:{$val};{$prop}:{$val}";
}
function csscrush_css3_Border_Top_Right_Radius ( $prop, $val ) {
	return "-moz-border-radius-topright:{$val};{$prop}:{$val}";
}
function csscrush_css3_Border_Bottom_Right_Radius ( $prop, $val ) {
	return "-moz-border-radius-bottomright:{$val};{$prop}:{$val}";
}
function csscrush_css3_Border_Bottom_Left_Radius ( $prop, $val ) {
	return "-moz-border-radius-bottomleft:{$val};{$prop}:{$val}";
}
// Box shadow
function csscrush_css3_Box_Shadow ( $prop, $val ) {
	return "-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
}
// Transform
function csscrush_css3_Transform ( $prop, $val ) {
	return "-o-{$prop}:{$val};-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
}
// Transition
function csscrush_css3_Transition ( $prop, $val ) {
	return "-o-{$prop}:{$val};-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
}
// Background size
function csscrush_css3_Background_Size ( $prop, $val ) {
	return "-o-{$prop}:{$val};-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
}
// Box sizing
function csscrush_css3_Box_Sizing ( $prop, $val ) {
	return "-webkit-{$prop}:{$val};-moz-{$prop}:{$val};{$prop}:{$val}";
}

