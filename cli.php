<?php
/**
 *
 * Command line application
 *
 */

require_once 'CssCrush.php';

// error_reporting('-1');

// stdin
$stdin = fopen( 'php://stdin', 'r' );
stream_set_blocking( $stdin, false ) or die ( 'Failed to disable stdin blocking' );
$stdin_contents = stream_get_contents( $stdin );

// stdout
$stdout = fopen( 'php://stdout', 'w' );


##################################################################
##  Version detection

$version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$required_version = 5.3;
if ( $version < $required_version ) {
	fwrite( $stdout, "PHP version $required_version or higher is required to use this tool.\nYou are currently running PHP version $version\n\n" );
	exit( 1 );
}


##################################################################
##  Options

$short_opts = array(
	"f::",  // Input file. Defaults to sdtin
	"o::",  // Output file. Defaults to stdout
	"p",    // Pretty formatting
	'b',    // Output boilerplate
	'h',    // Display help
);

$long_opts = array(
	'file::',      // Input file. Defaults to sdtin
	'output::',    // Output file. Defaults to stdout
	'pretty',      // Pretty formatting
	'boilerplate', // Output boilerplate
	'help',        // Display help
	'vendor-target:', // Vendor target
	'variables:',  // Map of variable names in an http query string format
);

$opts = getopt( implode( $short_opts ), $long_opts );

$input_file = @( $opts['f'] ?: $opts['file'] );
$output_file = @( $opts['o'] ?: $opts['output'] );
$variables = @$opts['variables'];
$vendor_target = @$opts['vendor-target'];
$boilerplate = @( isset( $opts['b'] ) ?: isset( $opts['boilerplate'] ) );
$pretty = @( isset( $opts['p'] ) ?: isset( $opts['pretty'] ) );
$help_flag = @( isset( $opts['h'] ) ?: isset( $opts['help'] ) );


##################################################################
##  Help page

// $command = $argv[0] == 'csscrush' ? 'csscrush' : 'php path/to/CssCrush/cli.php';
$command = 'csscrush';

$help = <<<TPL

synopsis:
    csscrush [-f|--file] [-o|--output-file] [-p|--pretty] [-b|--boilerplate]
             [-h|--help] [--variables] [--vendor-target]

options:
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

    --variables:
        Map of variable names in an http query string format

    --vendor-target:
        Set to 'all' for all vendor prefixes (default)
        Set to 'none' for no vendor prefixes
        Set to a specific vendor prefix

examples:
    $command -f=styles.css --pretty --vendor-target=webkit

    # Piping on unix based terminals
    cat 'styles.css' | $command --boilerplate


TPL;


if ( $help_flag ) {
	fwrite( $stdout, $help );
	exit( 1 );
}


##################################################################
##  Input

$input = null;

if ( $input_file ) {
	if ( ! file_exists( $input_file ) ) {
		fwrite( $stdout, "can't find input file\n\n" );
		exit( 0 );
	}
	$input = file_get_contents( $input_file );
}
elseif ( $stdin_contents ) {
	$input = $stdin_contents;
}
else {
	fwrite( $stdout, $help );
	exit( 1 );
}


##################################################################
##  Processing

$process_opts = array();
if ( $vendor_target ) {
	$process_opts[ 'vendor_target' ] = $vendor_target;
}
if ( $variables ) {
	parse_str( $variables, $in_vars );
	$process_opts[ 'vars' ] = $in_vars;
}
$process_opts[ 'boilerplate' ] = $boilerplate ? true : false;
$process_opts[ 'debug' ] = $pretty ? true : false;
$process_opts[ 'rewrite_import_urls' ] = true;

$import_context = $input_file ? dirname( realpath( $input_file ) ) : null;

// If there is an import context set it to the document root
if ( $import_context ) {
	$old_doc_root = csscrush::$config->docRoot;
	csscrush::$config->docRoot = $import_context;
	$process_opts[ 'import_context' ] = $import_context;
}

// Process the stream
$output = csscrush::string( $input, $process_opts );

// Reset the document root after processing
if ( $import_context ) {
	csscrush::$config->docRoot = $old_doc_root;
}


##################################################################
##  Output

if ( $output_file ) {
	if ( ! @file_put_contents( $output_file, $output ) ) {
		fwrite( $stdout, "Could not write to path '$output_file'\n" );
		if ( strpos( $output_file, '~' ) === 0 ) {
			fwrite( $stdout, "No tilde expansion\n" );
		}
		exit( 0 );
	}
}
else {
	$output .= "\n";
	fwrite( $stdout, $output );
}
exit( 1 );








