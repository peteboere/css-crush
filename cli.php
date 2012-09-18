#!/usr/bin/env php
<?php
/**
 *
 * Command line application
 *
 */

require_once 'CssCrush.php';

// Exit status constants
define( 'STATUS_OK', 0 );
define( 'STATUS_ERROR', 1 );

// Open stream handles
$stdin  = fopen( 'php://stdin', 'r' );
$stdout = fopen( 'php://stdout', 'w' );
$stderr = fopen( 'php://stderr', 'w' );

// Get stdin contents
if ( ! stream_set_blocking( $stdin, false ) ) {

	stderr( 'Failed to disable stdin blocking' );
	exit( STATUS_ERROR );
}
$stdin_contents = stream_get_contents( $stdin );
fclose( $stdin );


##################################################################
##  Helpers

function stderr ( $lines, $closing_newline = true ) {
	global $stderr;
	fwrite( $stderr,
		implode( PHP_EOL, (array) $lines ) . ( $closing_newline ? PHP_EOL : '' )
	);
}

function stdout ( $lines, $closing_newline = true ) {
	global $stdout;
	fwrite( $stdout,
		implode( PHP_EOL, (array) $lines ) . ( $closing_newline ? PHP_EOL : '' )
	);
}


##################################################################
##  Version detection

$version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$required_version = 5.3;

if ( $version < $required_version ) {

	stderr( array(
		"PHP version $required_version or higher is required to use this tool.",
		"You are currently running PHP version $version" )
	);
	exit( STATUS_ERROR );
}


##################################################################
##  Options

$short_opts = array(
	"f:",  // Input file. Defaults to sdtin
	"o:",  // Output file. Defaults to stdout
	"p",   // Pretty formatting
	'b',   // Output boilerplate
	'h',   // Display help
);

$long_opts = array(
	'file:',           // Input file. Defaults to sdtin
	'output:',         // Output file. Defaults to stdout
	'pretty',          // Pretty formatting
	'boilerplate',     // Output boilerplate
	'help',            // Display help
	'version',         // Display version
	'trace',           // Output sass tracing stubs
	'vendor-target:',  // Vendor target
	'variables:',      // Map of variable names in an http query string format
	'enable:',         // List of plugins to enable
	'disable:',        // List of plugins to disable
	'context:',        // Context for resolving URLs
);

$opts = getopt( implode( $short_opts ), $long_opts );

$input_file = @( $opts['f'] ?: $opts['file'] );
$output_file = @( $opts['o'] ?: $opts['output'] );
$pretty = @( isset( $opts['p'] ) ?: isset( $opts['pretty'] ) );
$boilerplate = @( isset( $opts['b'] ) ?: isset( $opts['boilerplate'] ) );
$help_flag = @( isset( $opts['h'] ) ?: isset( $opts['help'] ) );
$version_flag = @isset( $opts['version'] );
$trace_flag = @isset( $opts['trace'] );
$vendor_target = @$opts['vendor-target'];
$variables = @$opts['variables'];
$enable_plugins = isset( $opts['enable'] ) ? (array) $opts['enable'] : null;
$disable_plugins = isset( $opts['disable'] ) ? (array) $opts['disable'] : null;
$context = isset( $opts['context'] ) ? (array) $opts['context'] : null;


##################################################################
##  Help page

$command = 'csscrush';

$help = <<<TPL

Usage:
    csscrush [-f|--file] [-o|--output-file] [-p|--pretty] [-b|--boilerplate]
             [-h|--help] [--variables] [--vendor-target] [--version]

Options:
    -f, --file:
        The input file, if omitted takes input from stdin

    -o, --output:
        The output file, if omitted prints to stdout

    -p, --pretty:
        Formatted, unminified output

    -b, --boilerplate:
        Whether or not to output a boilerplate

    -h, --help:
        Display this help mesasge

    --enable:
        List of plugins to enable

    --disable:
        List of plugins to disable

    --context:
        Filepath context for resolving URLs

    --trace:
        Output debug-info stubs compatible with sass development tools

    --variables:
        Map of variable names in an http query string format

    --vendor-target:
        Set to 'all' for all vendor prefixes (default)
        Set to 'none' for no vendor prefixes
        Set to a specific vendor prefix

    --version:
        Version number

Examples:
    $command -f styles.css --pretty --vendor-target webkit

    # Piping on unix based terminals
    cat 'styles.css' | $command --boilerplate

    # Linting
    $command -f screen.css -p --enable property-sorter -o screen-linted.css

TPL;



if ( $version_flag ) {

	stdout( 'CSS Crush ' . csscrush::$config->version );
	exit( STATUS_OK );
}

if ( $help_flag ) {

	stdout( $help );
	exit( STATUS_OK );
}


##################################################################
##  Input

$input = null;

if ( $input_file ) {

	if ( ! file_exists( $input_file ) ) {
		stdout( 'Input file not found' . PHP_EOL );
		exit( STATUS_ERROR );
	}
	$input = file_get_contents( $input_file );
}
elseif ( $stdin_contents ) {

	$input = $stdin_contents;
}
else {

	// No input, just output help screen
	stdout( $help );
	exit( STATUS_OK );
}


##################################################################
##  Processing

$process_opts = array();
$process_opts[ 'boilerplate' ] = $boilerplate ? true : false;
$process_opts[ 'debug' ] = $pretty ? true : false;
$process_opts[ 'rewrite_import_urls' ] = true;

// Enable plugin args
if ( $enable_plugins ) {
	foreach ( $enable_plugins as $arg ) {
		foreach ( preg_split( '!\s*,\s*!', $arg ) as $plugin ) {
			$process_opts[ 'enable' ][] = $plugin;
		}
	}
}

// Disable plugin args
if ( $disable_plugins ) {
	foreach ( $disable_plugins as $arg ) {
		foreach ( preg_split( '!\s*,\s*!', $arg ) as $plugin ) {
			$process_opts[ 'disable' ][] = $plugin;
		}
	}
}

// Tracing
if ( $trace_flag ) {
	$process_opts[ 'trace' ] = true;
}

// Vendor target args
if ( $vendor_target ) {
	$process_opts[ 'vendor_target' ] = $vendor_target;
}

// Variables args
if ( $variables ) {
	parse_str( $variables, $in_vars );
	$process_opts[ 'vars' ] = $in_vars;
}

// Resolve a context for URLs
if ( ! $context ) {
	$context = $input_file ? dirname( realpath( $input_file ) ) : null;
}

// If there is an import context set it to the document root
if ( $context ) {
	$old_doc_root = csscrush::$config->docRoot;
	csscrush::$config->docRoot = $context;
	$process_opts[ 'context' ] = $context;
}

// Process the stream
$output = csscrush::string( $input, $process_opts );

// Reset the document root after processing
if ( $context ) {
	csscrush::$config->docRoot = $old_doc_root;
}


##################################################################
##  Output

if ( $output_file ) {

	if ( ! @file_put_contents( $output_file, $output ) ) {

		$message[] = "Could not write to path '$output_file'";

		if ( strpos( $output_file, '~' ) === 0 ) {
			$message[] = 'Tilde expansion does not work here';
		}

		stderr( $message );
		exit( STATUS_ERROR );
	}
}
else {

	if ( csscrush::$process->errors ) {
		stderr( csscrush::$process->errors );
	}

	stdout( $output );
	exit( STATUS_OK );
}
