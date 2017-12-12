<?php
/**
 *
 * Command line utility.
 *
 */
require_once 'CssCrush.php';

define('STATUS_OK', 0);
define('STATUS_ERROR', 1);

$version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$requiredVersion = 5.6;

if ($version < $requiredVersion) {

    stderr(["PHP version $requiredVersion or higher is required to use this tool.",
        "You are currently running PHP $version"]);

    exit(STATUS_ERROR);
}

try {
    $args = parse_args();
}
catch (Exception $ex) {

    stderr(message($ex->getMessage(), ['type'=>'error']));

    exit($ex->getCode());
}


##################################################################
##  Information options.

if ($args->version) {

    stdout((string) CssCrush\Version::detect());

    exit(STATUS_OK);
}
elseif ($args->help) {

    stdout(manpage());

    exit(STATUS_OK);
}


##################################################################
##  Resolve input.

$input = null;

if ($args->input_file) {

    $input = file_get_contents($args->input_file);
}
elseif ($stdin = get_stdin_contents()) {

    $input = $stdin;
}
else {
    stdout(manpage());

    exit(STATUS_OK);
}


if ($args->watch && ! $args->input_file) {

    stderr(message('Watch mode requires an input file.', ['type'=>'error']));

    exit(STATUS_ERROR);
}


##################################################################
##  Resolve process options.

$configFile = 'crushfile.php';
if (file_exists($configFile)) {
    $options = CssCrush\Util::readConfigFile($configFile);
}
else {
    $options = [];
}

if ($args->pretty) {
    $options['minify'] = false;
}

foreach (['boilerplate', 'formatter', 'newlines',
    'stat_dump', 'source_map', 'import_path'] as $option) {
    if ($args->$option) {
        $options[$option] = $args->$option;
    }
}

if ($args->enable_plugins) {
    $options['plugins'] = parse_list($args->enable_plugins);
}

if ($args->vendor_target) {
    $options['vendor_target'] = parse_list($args->vendor_target);
}

if ($args->vars) {
    parse_str($args->vars, $in_vars);
    $options['vars'] = $in_vars;
}

if ($args->output_file) {
    $options['output_dir'] = dirname($args->output_file);
    $options['output_file'] = basename($args->output_file);
}

$options += [
    'doc_root' => getcwd(),
    'context' => $args->context,
];


##################################################################
##  Output.

error_reporting(0);

if ($args->watch) {

    csscrush_set('config', ['io' => 'CssCrush\IO\Watch']);

    stdout('CONTROL-C to quit.');

    $outstandingErrors = false;

    while (true) {

        csscrush_file($args->input_file, $options);
        $stats = csscrush_stat();

        $changed = $stats['compile_time'] && ! $stats['errors'];
        $errors = $stats['errors'];
        $warnings = $stats['warnings'];
        $showErrors = $errors && (! $outstandingErrors || ($outstandingErrors != $errors));

        if ($errors) {
            if ($showErrors) {
                $outstandingErrors = $errors;
                stderr(message($errors, ['type'=>'error']));
            }
        }
        elseif ($changed) {
            $outstandingErrors = false;
            stderr(message(fmt_fileinfo($stats, 'output'), ['type'=>'write']));
        }

        if (($showErrors || $changed) && $warnings) {
            stderr(message($warnings, ['type'=>'warning']));
        }

        if ($changed && $args->stats) {
            stderr(message($stats, ['type'=>'stats']));
        }

        sleep(1);
    }
}
else {

    $stdOutput = null;

    if ($args->input_file && isset($options['output_dir'])) {
        $options['cache'] = false;
        csscrush_file($args->input_file, $options);
    }
    else {
        $stdOutput = csscrush_string($input, $options);
    }

    $stats = csscrush_stat();
    $errors = $stats['errors'];
    $warnings = $stats['warnings'];

    if ($errors) {
        stderr(message($errors, ['type'=>'error']));

        exit(STATUS_ERROR);
    }
    elseif ($args->input_file && ! empty($stats['output_filename'])) {
        stderr(message(fmt_fileinfo($stats, 'output'), ['type'=>'write']));
    }

    if ($warnings) {
        stderr(message($warnings, ['type'=>'warning']));
    }

    if ($args->stats) {
        stderr(message($stats, ['type'=>'stats']));
    }

    if ($stdOutput) {
        stdout($stdOutput);
    }

    exit(STATUS_OK);
}


##################################################################
##  Helpers.

function stderr($lines, $closing_newline = true) {

    $out = implode(PHP_EOL, (array) $lines) . ($closing_newline ? PHP_EOL : '');
    fwrite(defined('TESTMODE') && TESTMODE ? STDOUT : STDERR, $out);
}

function stdout($lines, $closing_newline = true) {

    $out = implode(PHP_EOL, (array) $lines) . ($closing_newline ? PHP_EOL : '');
    fwrite(STDOUT, $out);
}

function get_stdin_contents() {

    stream_set_blocking(STDIN, 0);
    $contents = stream_get_contents(STDIN);
    stream_set_blocking(STDIN, 1);

    return $contents;
}

function parse_list(array $option) {

    $out = [];
    foreach ($option as $arg) {
        if (is_string($arg)) {
            foreach (preg_split('~\s*,\s*~', $arg) as $item) {
                $out[] = $item;
            }
        }
        else {
            $out[] = $arg;
        }
    }
    return $out;
}

function message($messages, $options = []) {

    $defaults = [
        'color' => 'b',
        'label' => null,
        'indent' => false,
        'format_label' => false,
    ];
    $preset = ! empty($options['type']) ? $options['type'] : null;
    switch ($preset) {
        case 'error':
            $defaults['color'] = 'r';
            $defaults['label'] = 'ERROR';
            break;
        case 'warning':
            $defaults['color'] = 'y';
            $defaults['label'] = 'WARNING';
            break;
        case 'write':
            $defaults['color'] = 'g';
            $defaults['label'] = 'WRITE';
            break;
        case 'stats':
            // Making stats concise and readable.
            $messages['input_file'] = $messages['input_path'];
            $messages['compile_time'] = round($messages['compile_time'], 5) . ' seconds';
            foreach (['input_filename', 'input_path', 'output_filename',
                'output_path', 'vars', 'errors', 'warnings'] as $key) {
                unset($messages[$key]);
            }
            ksort($messages);
            $defaults['indent'] = true;
            $defaults['format_label'] = true;
            break;
    }
    extract($options + $defaults);

    $out = [];
    foreach ((array) $messages as $_label => $value) {
        $_label = $label ?: $_label;
        if ($format_label) {
            $_label = ucfirst(str_replace('_', ' ', $_label));
        }
        $prefix = $indent ? '└── ' : '';
        $colorUp = strtoupper($color);
        if (is_scalar($value)) {
            $out[] = colorize("<$color>$prefix<$colorUp>$_label:<$color> $value</>");
        }
    }
    return implode(PHP_EOL, $out);
}

function fmt_fileinfo($stats, $type) {
    $time = round($stats['compile_time'], 3);
    return $stats[$type . '_path'] . " ({$time}s)";
}

function pick(array &$arr) {

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

function colorize($str) {

    static $color_support;
    static $tags = [
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
    ];

    if (! isset($color_support)) {
        $color_support = defined('TESTMODE') && TESTMODE ? false : true;
        if (DIRECTORY_SEPARATOR == '\\') {
            $color_support = false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
        }
    }

    $find = array_keys($tags);
    $replace = $color_support ? array_values($tags) : '';

    return str_replace($find, $replace, $str);
}

function get_trailing_io_args($required_value_opts) {

    $trailing_input_file = null;
    $trailing_output_file = null;

    // Get raw script args, shift off calling scriptname and reduce to last three.
    $trailing_args = $GLOBALS['argv'];
    array_shift($trailing_args);
    $trailing_args = array_slice($trailing_args, -3);

    // Create patterns for detecting options.
    $required_values = implode('|', $required_value_opts);
    $value_opt_patt = "~^-{1,2}($required_values)$~";
    $other_opt_patt = "~^-{1,2}([a-z0-9\-]+)?(=|$)~ix";

    // Step through the args.
    $filtered = [];
    for ($i = 0; $i < count($trailing_args); $i++) {

        $current = $trailing_args[$i];

        // If tests as a required value option, reset and skip next.
        if (preg_match($value_opt_patt, $current)) {
            $filtered = [];
            $i++;
        }
        // If it looks like any other kind of flag, or optional value option, reset.
        elseif (preg_match($other_opt_patt, $current)) {
            $filtered = [];
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

    return [$trailing_input_file, $trailing_output_file];
}

function parse_args() {

    $required_value_opts = [
        'i|input|f|file', // Input file. Defaults to STDIN.
        'o|output', // Output file. Defaults to STDOUT.
        'E|enable|plugins',
        'D|disable',
        'vars|variables',
        'formatter',
        'vendor-target',
        'context',
        'import-path',
        'newlines',
    ];

    $optional_value_opts = [
        'b|boilerplate',
        'stat-dump',
    ];

    $flag_opts = [
        'p|pretty',
        'w|watch',
        'help',
        'version',
        'source-map',
        'stats',
        'test',
    ];

    // Create option strings for getopt().
    $short_opts = [];
    $long_opts = [];
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

    $opts = getopt(implode($short_opts), $long_opts);

    $args = new stdClass();

    // Information options.
    $args->help = isset($opts['h']) ?: isset($opts['help']);
    $args->version = isset($opts['version']);

    // File arguments.
    $args->input_file = pick($opts, 'i', 'input', 'f', 'file');
    $args->output_file = pick($opts, 'o', 'output');
    $args->context = pick($opts, 'context');

    // Flags.
    $args->pretty = isset($opts['p']) ?: isset($opts['pretty']);
    $args->watch = isset($opts['w']) ?: isset($opts['watch']);
    $args->source_map = isset($opts['source-map']);
    $args->stats = pick($opts, 'stats');
    define('TESTMODE', isset($opts['test']));

    // Arguments that optionally accept a single value.
    $args->boilerplate = pick($opts, 'b', 'boilerplate');
    $args->stat_dump = pick($opts, 'stat-dump');

    // Arguments that require a single value.
    $args->formatter = pick($opts, 'formatter');
    $args->vars = pick($opts, 'vars', 'variables');
    $args->newlines = pick($opts, 'newlines');

    // Arguments that require a value but accept multiple values.
    $args->enable_plugins = pick($opts, 'E', 'enable', 'plugins');
    $args->vendor_target = pick($opts, 'vendor-target');
    $args->import_path = pick($opts, 'import-path');

    // Run multiple value arguments through array cast.
    foreach (['enable_plugins', 'vendor_target'] as $arg) {
        if ($args->$arg) {
            $args->$arg = (array) $args->$arg;
        }
    }

    // Detect trailing IO files from raw script arguments.
    list($trailing_input_file, $trailing_output_file) = get_trailing_io_args($required_value_opts);

    // If detected apply, not overriding explicit IO file options.
    if (! $args->input_file && $trailing_input_file) {
        $args->input_file = $trailing_input_file;
    }
    if (! $args->output_file && $trailing_output_file) {
        $args->output_file = $trailing_output_file;
    }

    if ($args->input_file) {
        $inputFile = $args->input_file;
        if (! ($args->input_file = realpath($args->input_file))) {
            throw new Exception("Input file '$inputFile' does not exist.", STATUS_ERROR);
        }
    }

    if ($args->output_file) {
        $outDir = dirname($args->output_file);
        if (! realpath($outDir) && ! @mkdir($outDir, 0755, true)) {
            throw new Exception('Output directory does not exist and could not be created.', STATUS_ERROR);
        }
        $args->output_file = realpath($outDir) . '/' . basename($args->output_file);
    }

    if ($args->context) {
        if (! ($args->context = realpath($args->context))) {
            throw new Exception('Context path does not exist.', STATUS_ERROR);
        }
    }
    else {
        $args->context = $args->input_file ? dirname($args->input_file) : getcwd();
    }
    if (is_string($args->boilerplate)) {
        if (! ($args->boilerplate = realpath($args->boilerplate))) {
            throw new Exception('Boilerplate file does not exist.', STATUS_ERROR);
        }
    }

    return $args;
}

function manpage() {

    $manpage = <<<TPL

<B>USAGE:</>
    <B>csscrush <G>[OPTIONS] <g>[input-file] [output-file]

<B>OPTIONS:</>
    <G>-i<g>, --input</>
        Input file. If omitted takes input from STDIN.

    <G>-o<g>, --output</>
        Output file. If omitted prints to STDOUT.

    <G>-p<g>, --pretty</>
        Formatted, un-minified output.

    <G>-w<g>, --watch</>
        Watch input file for changes.
        Writes to file specified with -o option or to the input file
        directory with a '.crush.css' file extension.

    <G>-E<g>, --plugins</>
        List of plugins (comma separated) to enable.

    <g>--boilerplate</>
        Whether or not to output a boilerplate. Optionally accepts filepath
        to a custom boilerplate template.

    <g>--context</>
        Filepath context for resolving relative import URLs.
        Only meaningful when taking raw input from STDIN.

    <g>--import-path</>
        Comma separated list of additional paths to search when resolving
        relative import URLs.

    <g>--formatter</>
        Possible values:
            'block' (default)
                Rules are block formatted.
            'single-line'
                Rules are printed in single lines.
            'padded'
                Rules are printed in single lines with right padded selectors.

    <g>--help</>
        Display this help message.

    <g>--newlines</>
        Force newline style on output css. Defaults to the current platform
        newline. Possible values: 'windows' (or 'win'), 'unix', 'use-platform'.

    <g>--source-map</>
        Create a source map file (compliant with the Source Map v3 proposal).

    <g>--stats</>
        Display post-compile stats.

    <g>--vars</>
        Map of variable names in an http query string format.

    <g>--vendor-target</>
        Possible values:
            'all'
                For all vendor prefixes (default).
            'none'
                For no vendor prefixing.
            'moz', 'webkit', 'ms' etc.
                Limit to a specific vendor prefix (or comma separated list).

    <g>--version</>
        Display version number.

<B>EXAMPLES:</>
    # Restrict vendor prefixing.
    csscrush --pretty --vendor-target webkit -i styles.css

    # Piped input.
    cat styles.css | csscrush --vars 'foo=black&bar=white' > alt-styles.css

    # Linting.
    csscrush --pretty -E property-sorter -i styles.css -o linted.css

    # Watch mode.
    csscrush --watch -i styles.css -o compiled/styles.css

    # Using custom boilerplate template.
    csscrush --boilerplate=css/boilerplate.txt css/styles.css

TPL;

    return colorize($manpage);
}
