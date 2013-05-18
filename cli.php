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

$required_value_opts = array(
    'i|input|f|file', // Input file. Defaults to STDIN.
    'o|output',       // Output file. Defaults to STDOUT.
    'E|enable' ,      // List of plugins to enable.
    'D|disable',      // List of plugins to disable.
    'vars|variables', // Map of variable names in an http query string format.
    'formatter',      // Formatter name for formatted output.
    'vendor-target',  // Vendor target.
    'context',        // Context for resolving URLs.
    'newlines',       // Newline style.
);

$optional_value_opts = array(
    'b|boilerplate', // Boilerplate.
);

$flag_opts = array(
    'p|pretty', // Pretty output.
    'w|watch',  // Watch mode.
    'list',     // List plugins.
    'help',     // Display help.
    'version',  // Display version.
    'trace',    // Output sass tracing stubs.
);

// Create option strings for getopt().
$short_opts = array();
$long_opts = array();
$join_opts = function ($opts_list, $modifier) use (&$short_opts, &$long_opts) {
    foreach ($opts_list as $opt) {
        foreach (explode('|', $opt) as $arg) {
            if (strlen($arg) === 1) {
                $short_opts[] = "$arg$modifier";
            }
            else {
                $long_opts[] = "$arg$modifier";
            }
        }
    }
};
$join_opts($required_value_opts, ':');
$join_opts($optional_value_opts, '::');
$join_opts($flag_opts, '');


// Parse opts.
$opts = getopt(implode($short_opts), $long_opts);

$args = new stdClass();

// File arguments.
$args->input_file = pick($opts, 'i', 'input', 'f', 'file');
$args->output_file = pick($opts, 'o', 'output');
$args->context = pick($opts, 'context');

// Flags.
$args->pretty = isset($opts['p']) ?: isset($opts['pretty']);
$args->watch = isset($opts['w']) ?: isset($opts['watch']);
$args->list = isset($opts['l']) ?: isset($opts['list']);
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
$args->enable_plugins = pick($opts, 'E', 'enable');
$args->disable_plugins = pick($opts, 'D', 'disable');

// Detect trailing IO files from raw script arguments.
list($trailing_input_file, $trailing_output_file) = get_trailing_io_args();

// If detected apply, not overriding explicit IO file options.
if (! $args->input_file && $trailing_input_file) {
    $args->input_file = $trailing_input_file;
}
if (! $args->output_file && $trailing_output_file) {
    $args->output_file = $trailing_output_file;
}


##################################################################
##  Information options.

if ($args->version) {

    stdout('CSS-Crush ' . CssCrush::$config->version);

    exit(STATUS_OK);
}
elseif ($args->help) {

    stdout(manpage());

    exit(STATUS_OK);
}
elseif ($args->list) {

    $plugins = array();

    foreach (CssCrush_Plugin::info() as $name => $docs) {
        // Use first line of plugin doc for description.
        $headline = isset($docs[0]) ? $docs[0] : false;
        $plugins[] = colorize("<g>$name</>" . ($headline ? " - $headline" : ''));
    }
    stdout($plugins);

    exit(STATUS_OK);
}


##################################################################
##  Validate option values.

// Filepath arguments.
if ($args->input_file) {
    $input_file = $args->input_file;
    if (! ($args->input_file = realpath($args->input_file))) {
        stderr("Input file '$input_file' does not exist.");

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
foreach (array('enable_plugins', 'disable_plugins', 'vendor_target') as $arg) {
    if ($args->{$arg}) {
        $args->{$arg} = (array) $args->{$arg};
    }
}


##################################################################
##  Resolve input.

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
    $process_opts['vendor_target'] = parse_list($args->vendor_target);
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
    csscrush_set('config', array('io' => 'CssCrush_IOWatch'));

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

        if (! @file_put_contents($args->output_file, $output, LOCK_EX)) {

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

function colorize ($str) {

    static $color_support;
    static $tags = array(
        '<b>' => "\033[0;30m",
        '<r>' => "\033[0;31m",
        '<g>' => "\033[0;32m",
        '<y>' => "\033[0;33m",
        '<b>' => "\033[0;34m",
        '<v>' => "\033[0;35m",
        '<c>' => "\033[0;36m",
        '<w>' => "\033[0;37m",

        '<B>' => "\033[1;30m",
        '<R>' => "\033[1;31m",
        '<G>' => "\033[1;32m",
        '<Y>' => "\033[1;33m",
        '<B>' => "\033[1;34m",
        '<V>' => "\033[1;35m",
        '<C>' => "\033[1;36m",
        '<W>' => "\033[1;37m",

        '</>' => "\033[m",
    );

    if (! isset($color_support)) {
        $color_support = true;
        if (DIRECTORY_SEPARATOR == '\\') {
            $color_support = false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
        }
    }

    $find = array_keys($tags);
    $replace = $color_support ? array_values($tags) : '';

    return str_replace($find, $replace, $str);
}

function get_trailing_io_args () {

    $trailing_input_file = null;
    $trailing_output_file = null;

    // Get raw script args, shift off calling scriptname and reduce to last three.
    $trailing_args = $GLOBALS['argv'];
    array_shift($trailing_args);
    $trailing_args = array_slice($trailing_args, -3);

    // Create patterns to detecting options.
    $required_values = implode('|', $GLOBALS['required_value_opts']);
    $value_opt_patt = "~^-{1,2}($required_values)$~";
    $other_opt_patt = "~^-{1,2}([a-z0-9\-]+)?(=|$)~ix";

    // Step through the args.
    $filtered = array();
    for ($i = 0; $i < count($trailing_args); $i++) {

        $current = $trailing_args[$i];

        // If tests as a required value option, reset and skip next.
        if (preg_match($value_opt_patt, $current)) {
            $filtered = array();
            $i++;
        }
        // If it looks like any other kind of flag, or optional value option, reset.
        elseif (preg_match($other_opt_patt, $current)) {
            $filtered = array();
        }
        else {
            $filtered[] = $current;
        }
    }

    // We're only interested in the last two values.
    $filtered = array_slice($filtered, -2);

    switch (count($filtered)) {
        case 1:
            $trailing_input_file = $filtered[0];
            break;
        case 2:
            $trailing_input_file = $filtered[0];
            $trailing_output_file = $filtered[1];
            break;
    }

    return array($trailing_input_file, $trailing_output_file);
}

function manpage () {

    $manpage = <<<TPL

<B>USAGE:</>
    <B>csscrush <G>[OPTIONS] <g>[input-file] [output-file]

<B>OPTIONS:</>
    <G>-i<g>, --input</>:
        Input file. If omitted takes input from STDIN.

    <G>-o<g>, --output</>:
        Output file. If omitted prints to STDOUT.

    <G>-p<g>, --pretty</>:
        Formatted, un-minified output.

    <G>-w<g>, --watch</>:
        Watch input file for changes.
        Writes to file specified with -o option or to the input file
        directory with a '.crush.css' file extension.

    <G>-D<g>, --disable</>:
        List of plugins to disable. Pass 'all' to disable all.

    <G>-E<g>, --enable</>:
        List of plugins to enable. Overrides <g>--disable</>.

    <g>--boilerplate</>:
        Whether or not to output a boilerplate. Optionally accepts filepath
        to a custom boilerplate template.

    <g>--context</>:
        Filepath context for resolving relative URLs. Only meaningful when
        taking raw input from STDIN.

    <g>--formatter</>:
        Formatter to use for formatted (<g>--pretty</>) output.
        Available formatters:

        'block' (default) -
            Rules are block formatted.
        'single-line' -
            Rules are printed in single lines.
        'padded' -
            Rules are printed in single lines with right padded selectors.

    <g>--help</>:
        Display this help message.

     <g>--list</>:
        Show plugins.

    <g>--newlines</>:
        Force newline style on output css. Defaults to the current platform
        newline. Possible values: 'windows' (or 'win'), 'unix', 'use-platform'.

    <g>--trace</>:
        Output debug-info stubs compatible with client-side sass debuggers.

    <g>--vars</>:
        Map of variable names in an http query string format.

    <g>--vendor-target</>:
        Set to 'all' for all vendor prefixes (default).
        Set to 'none' for no vendor prefixes.
        Set to a specific vendor prefix.

    <g>--version</>:
        Print version number.

<B>EXAMPLES:</>
    # Restrict vendor prefixing.
    csscrush --pretty --vendor-target webkit -i styles.css

    # Piped input.
    cat styles.css | csscrush --vars 'foo=black&bar=white' > alt-styles.css

    # Linting.
    csscrush --pretty --E property-sorter -i styles.css -o linted.css

    # Watch mode.
    csscrush --watch -i styles.css -o compiled/styles.css

    # Using custom boilerplate template.
    csscrush --boilerplate=css/boilerplate.txt css/styles.css

TPL;

    return colorize($manpage);
}
