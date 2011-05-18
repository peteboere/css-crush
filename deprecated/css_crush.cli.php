<?php 

// public static function init () {
// 	self::$initialized = true;
// 	self::$compileSuffix = '.crush.css';
// 	self::$compileRevisionedSuffix = '.crush.r.css';
// 	self::$config = new stdClass;
// 	self::$config->file = '.' . __CLASS__;
// 	self::$config->data = null;
// 	self::$config->path = null;
// 	self::$config->baseDir = null;
// 	self::$config->baseURL = null;
// 
// 	$docRoot = $_SERVER[ 'DOCUMENT_ROOT' ];
// 	// workaround trailing slash issues
// 	$docRoot = ( substr( $docRoot, -1 ) == '/' ) ? substr( $docRoot, 0, -1 ) : $docRoot;
// 
// 	if ( defined( 'STDIN' ) and $_SERVER[ 'argc' ] > 0 ) {
// 		// Command line
// 		self::log( 'Command line mode' );
// 		self::$cli = true;
// 	}
// 	else {
// 		// Running on a server
// 		self::log( 'Server mode' );
// 		self::$config->docRoot = $docRoot;
// 		self::$cli = false;
// 	}
// 	self::$config->docRoot = $docRoot;
// 	
// 	
// 	self::$regex = (object) self::$regex;
// }



// public static $cli;
// 
// public static function cli ( $file, $options = null ) {
// 	// Make basic information about the hostfile accessible
// 	$hostfile = new stdClass;
// 	$hostfile->name = basename( $file );
// 	$hostfile->path = realpath( $file );
// 	$hostfile->mtime = filemtime( $hostfile->path );
// 
// 	self::$config->baseDir = dirname( $hostfile->path );
// 
// 	self::parseOptions( $options );
// 	return self::compile( $hostfile );
// }


################################################################################################
#  Command line API

/*
php CSS_Crush.php -f=css/screen.css -n
>>> non-minified output
*/

if ( CSS_Crush::$cli ) {
	$options = getopt( "f:o::m::cn", array(
			'file:',    // Input file
			'output::', // Output file
			'macros::', // Comma seperated list of macro properties
			'comments', // (flag) Leave comments intact
			'nominify',
		));
	$file = null;
	$params = array();
	if ( isset( $options[ 'f' ] ) ) {
		$file = $options[ 'f' ];
	}
	else if ( isset( $options[ 'file' ] ) ) {
		$file = $options[ 'file' ];
	}
	if ( !$file or !file_exists( $file ) ) {
		return;
	}
	if ( isset( $options[ 'm' ] ) ) {
		$params[ 'macros' ] = explode( ',', $options[ 'm' ] );
	}
	else if ( isset( $options[ 'macros' ] ) ) {
		$params[ 'macros' ] = explode( ',', $options[ 'macros' ] );
	}
	if ( isset( $options[ 'c' ] ) or isset( $options[ 'comments' ] ) ) {
		$params[ 'comments' ] = true;
	}
	if ( isset( $options[ 'n' ] ) or isset( $options[ 'nominify' ] ) ) {
		$params[ 'minify' ] = false;
	}

	$output = CSS_Crush::cli( $file, $params );

	$outputFile = isset( $options[ 'o' ] );
	if ( $outputFile ) {
		$outputFile = $options[ 'o' ];
	}
	else {
		$outputFile = isset( $options[ 'output' ] ) ? $options[ 'output' ] : false;
	}

	if ( $outputFile ) {
		$output = CSS_Crush::getBoilerplate() . "\n{$output}";
		file_put_contents( $outputFile, $output );
	}
	else {
		echo $output . PHP_EOL;
	}
}
