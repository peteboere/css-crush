#!/usr/bin/env php
<?php
/**
 *
 * Command line utility.
 *
 */
require_once 'CssCrush.php';

##################################################################
##  Exit statuses.

define('STATUS_OK', 0);
define('STATUS_ERROR', 1);


##################################################################
##  PHP requirements check.

$version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$required_version = 5.3;

if ($version < $required_version) {

    stderr(array(
        "PHP version $required_version or higher is required to use this tool.",
        "You are currently running PHP version $version")
    );

    exit(STATUS_ERROR);
}


##################################################################
##  Resolve options.

$short_opts = array(

    // Required value arguments.
    'f:', // Input file. Defaults to SDTIN.
    'i:', // Input file alias.
    'o:', // Output file. Defaults to STDOUT.

    // Optional value arguments.
    'b::', // Output boilerplate (optional filepath).

    // Flags.
    'p', // Pretty (un-minified) output.
    'w', // Enable file watching mode.

    // Deprecated (removed in 2.x).
    'h', // Display help. (deprecated)
);

$long_opts = array(

    // Required value arguments.
    'formatter:',     // Formatter name for formatted output.
    'vendor-target:', // Vendor target.
    'vars:',          // Map of variable names in an http query string format.
    'enable:',        // List of plugins to enable.
    'disable:',       // List of plugins to disable.
    'context:',       // Context for resolving URLs.
    'newlines:',      // Newline style.

    // Optional value arguments.
    'boilerplate::',  // Boilerplate alias.

    // Flags.
    'watch',          // Watch mode alias.
    'pretty',         // Pretty output alias.
    'help',           // Display help.
    'version',        // Display version.
    'trace',          // Output sass tracing stubs.

    // Deprecated (removed in 2.x).
    'output:',        // Output file alias.
    'file:',          // Input file alias.
    'variables:',     // Vars alias.
);

$opts = getopt(implode($short_opts), $long_opts);
$args = new stdClass();

// File arguments.
$args->input_file = pick($opts, 'f', 'i', 'file');
$args->output_file = pick($opts, 'o', 'output');
$args->context = pick($opts, 'context');

// Flags.
$args->pretty = isset($opts['p']) ?: isset($opts['pretty']);
$args->watch = isset($opts['w']) ?: isset($opts['watch']);
$args->help = isset($opts['h']) ?: isset($opts['help']);
$args->version = isset($opts['version']);
$args->trace = isset($opts['trace']);

// Arguments that optionally accept a single value.
$args->boilerplate = pick($opts, 'b', 'boilerplate');

// Arguments that require a single value.
$args->formatter = pick($opts, 'formatter');
$args->vendor_target = pick($opts, 'vendor-target');
$args->vars = pick($opts, 'vars', 'variables');
$args->newlines = pick($opts, 'newlines');

// Arguments that require a value but accept multiple values.
$args->enable_plugins = pick($opts, 'enable');
$args->disable_plugins = pick($opts, 'disable');


##################################################################
##  Filter option values.

// Validate filepath arguments.
if ($args->input_file) {
    if (! ($args->input_file = realpath($args->input_file))) {
        stderr('Input file does not exist.');

        exit(STATUS_ERROR);
    }
}

if ($args->output_file) {
    $out_dir = realpath(dirname($args->output_file));
    if (! $out_dir) {
        stderr('Output directory does not exist.');

        exit(STATUS_ERROR);
    }
    $args->output_file = $out_dir . '/' . basename($args->output_file);
}

if ($args->context) {
    if (! ($args->context = realpath($args->context))) {
        stderr('Context path does not exist.');

        exit(STATUS_ERROR);
    }
}

if (is_string($args->boilerplate)) {

    if (! ($args->boilerplate = realpath($args->boilerplate))) {
        stderr('Boilerplate file does not exist.');

        exit(STATUS_ERROR);
    }
}


// Run multiple value arguments through array cast.
foreach (array('enable_plugins', 'disable_plugins') as $arg) {
    if ($args->{$arg}) {
        $args->{$arg} = (array) $args->{$arg};
    }
}


##################################################################
##  Help and version info.

if ($args->version) {

    stdout('CSS-Crush ' . CssCrush::$config->version);

    exit(STATUS_OK);
}

elseif ($args->help) {

    stdout(manpage());

    exit(STATUS_OK);
}


##################################################################
##  Input.

$input = null;

// File input.
if ($args->input_file) {

    $input = file_get_contents($args->input_file);
}

// STDIN.
elseif ($stdin_contents = get_stdin_contents()) {

    $input = $stdin_contents;
}

// Bail with manpage if no input.
else {

    // No input, just output help screen.
    stdout(manpage());

    exit(STATUS_OK);
}


if ($args->watch && ! $args->input_file) {

    stderr('Watch mode requires an input file.');

    exit(STATUS_ERROR);
}


##################################################################
##  Set process options.

$process_opts = array();
$process_opts['boilerplate'] = isset($args->boilerplate) ? $args->boilerplate : false;
$process_opts['minify'] = $args->pretty ? false : true;

if ($args->formatter) {
    $process_opts['formatter'] = $args->formatter;
}

if ($args->formatter) {
    $process_opts['formatter'] = $args->formatter;
}

// Newlines arg.
if ($args->newlines) {
    $process_opts['newlines'] = $args->newlines;
}

// Enable plugin args.
if ($args->enable_plugins) {
    $process_opts['enable'] = parse_list($args->enable_plugins);
}

// Disable plugin args.
if ($args->disable_plugins) {
    $process_opts['disable'] = parse_list($args->disable_plugins);
}

// Tracing arg.
if ($args->trace) {
    $process_opts['trace'] = true;
}

// Vendor target arg.
if ($args->vendor_target) {
    $process_opts['vendor_target'] = $args->vendor_target;
}

// Variables args.
if ($args->vars) {
    parse_str($args->vars, $in_vars);
    $process_opts['vars'] = $in_vars;
}

// Resolve an input file context for relative filepaths.
if (! $args->context) {
    $args->context = $args->input_file ? dirname($args->input_file) : getcwd();
}
$process_opts['context'] = $args->context;

// Set document_root to the current working directory.
$process_opts['doc_root'] = getcwd();

// If output file is specified set output directory and output filename.
if ($args->output_file) {
    $process_opts['output_dir'] = dirname($args->output_file);
    $process_opts['output_file'] = basename($args->output_file);
}


##################################################################
##  Output.

if ($args->watch) {

    // Override the IO class.
    CssCrush::$config->io = 'CssCrush_IOWatch';

    stdout('CONTROL-C to quit.');

    while (true) {

        $created_file = CssCrush::file($args->input_file, $process_opts);

        if (CssCrush::$process->errors) {
            stderr(CssCrush::$process->errors);

            exit(STATUS_ERROR);
        }

        sleep(1);
    }
}
else {

    $output = CssCrush::string($input, $process_opts);

    if ($args->output_file) {

        if (! @file_put_contents($args->output_file, $output)) {

            $message[] = "Could not write to path '{$args->output_file}'.";
            stderr($message);

            exit(STATUS_ERROR);
        }
    }
    else {

        if (CssCrush::$process->errors) {
            stderr(CssCrush::$process->errors);
        }
        stdout($output);

        exit(STATUS_OK);
    }
}


##################################################################
##  Helpers.

function stderr ($lines, $closing_newline = true) {
    $out = implode(PHP_EOL, (array) $lines) . ($closing_newline ? PHP_EOL : '');
    fwrite(STDERR, $out);
}

function stdout ($lines, $closing_newline = true) {
    $out = implode(PHP_EOL, (array) $lines) . ($closing_newline ? PHP_EOL : '');

    // On OSX terminal is sometimes truncating 'visual' output to terminal
    // with fwrite to STDOUT.
    echo $out;
}

function get_stdin_contents () {
    $stdin = fopen('php://stdin', 'r');
    stream_set_blocking($stdin, false);
    $stdin_contents = stream_get_contents($stdin);
    fclose($stdin);

    return $stdin_contents;
}

function parse_list (array $option) {
    $out = array();
    foreach ($option as $arg) {
        foreach (preg_split('~\s*,\s*~', $arg) as $item) {
            $out[] = $item;
        }
    }

    return $out;
}

function pick (array &$arr) {

    $args = func_get_args();
    array_shift($args);

    foreach ($args as $key) {
        if (isset($arr[$key])) {
            // Optional values return false but we want true is argument is present.
            return is_bool($arr[$key]) ? true : $arr[$key];
        }
    }
    return null;
}

function manpage () {

    return <<<TPL

Usage:
    csscrush [-f|-i] [-o] [-p|--pretty] [-w|--watch] [-b|--boilerplate]
             [--help] [--formatter] [--vars] [--vendor-target]
             [--version] [--newlines]

Options:
    -f, -i:
        The input file. If omitted takes input from STDIN.

    -o:
        The output file. If omitted prints to STDOUT.

    -p, --pretty:
        Formatted, un-minified output.

    -w, --watch:
        Watch input file for changes.
        Writes to file specified with -o option or to the input file
        directory with a '.crush.css' file extension.

    -b, --boilerplate:
        Whether or not to output a boilerplate. Optionally accepts filepath
        to custom boilerplate template.

    --help:
        Display this help mesasge.

    --context:
        Filepath context for resolving URLs.

    --disable:
        List of plugins to disable. Pass 'all' to disable all.

    --enable:
        List of plugins to enable. Overrides --disable.

    --formatter:
        Formatter to use for formatted (--pretty) output.
        Available formatters:

        'block' (default) -
            Rules are block formatted.
        'single-line' -
            Rules are printed in single lines.
        'padded' -
            Rules are printed in single lines with right padded selectors.

    --newlines:
        Force newline style on output css. Defaults to the current platform
        newline. Possible values: 'windows' (or 'win'), 'unix', 'use-platform'.

    --trace:
        Output debug-info stubs compatible with client-side sass debuggers.

    --vars:
        Map of variable names in an http query string format.

    --vendor-target:
        Set to 'all' for all vendor prefixes (default).
        Set to 'none' for no vendor prefixes.
        Set to a specific vendor prefix.

    --version:
        Print version number.

Examples:
    # Restrict vendor prefixing.
    csscrush --pretty --vendor-target webkit -i styles.css

    # Piped input.
    cat styles.css | csscrush --vars 'foo=black&bar=white' > alt-styles.css

    # Linting.
    csscrush --pretty --enable property-sorter -i styles.css -o linted.css

    # Watch mode.
    csscrush --watch -i styles.css -o compiled/styles.css

    # Using custom boilerplate template.
    csscrush --boilerplate=css/boilerplate.txt -i css/styles.css
TPL;
}
