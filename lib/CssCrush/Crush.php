<?php
/**
 *
 * Core public API.
 *
 */
namespace CssCrush;

class Crush
{
    const VERSION = '2.4.0';

    // Global settings.
    public static $config;

    // The current active process.
    public static $process;

    // Library root directory.
    public static $dir;

    public static function init()
    {
        self::$dir = dirname(dirname(__DIR__));

        self::$config = new \stdClass();

        self::$config->pluginDirs = array(self::$dir . '/plugins');
        self::$config->version = new Version(self::VERSION);
        self::$config->scriptDir = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
        self::$config->docRoot = self::resolveDocRoot();
        self::$config->logger = new Logger();
        self::$config->io = 'CssCrush\IO';

        // Shared resources.
        self::$config->vars = array();
        self::$config->aliasesFile = self::$dir . '/aliases.ini';
        self::$config->aliases = array();
        self::$config->bareAliases = array(
            'properties' => array(),
            'functions' => array(),
            'function_groups' => array(),
            'declarations' => array(),
            'at-rules' => array(),
        );
        self::$config->options = new Options();

        require_once self::$dir . '/misc/formatters.php';
    }

    static protected function resolveDocRoot($doc_root = null)
    {
        // Get document_root reference
        // $_SERVER['DOCUMENT_ROOT'] is unreliable in certain CGI/Apache/IIS setups

        if (! $doc_root) {

            $script_filename = $_SERVER['SCRIPT_FILENAME'];
            $script_name = $_SERVER['SCRIPT_NAME'];

            if ($script_filename && $script_name) {

                $len_diff = strlen($script_filename) - strlen($script_name);

                // We're comparing the two strings so normalize OS directory separators
                $script_filename = str_replace('\\', '/', $script_filename);
                $script_name = str_replace('\\', '/', $script_name);

                // Check $script_filename ends with $script_name
                if (substr($script_filename, $len_diff) === $script_name) {

                    $path = substr($script_filename, 0, $len_diff);
                    $doc_root = realpath($path);
                }
            }

            if (! $doc_root) {
                $doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
            }

            if (! $doc_root) {
                warning("Could not get a valid DOCUMENT_ROOT reference.");
            }
        }

        return Util::normalizePath($doc_root);
    }

    public static function loadAssets()
    {
        static $called;
        if ($called) {
            return;
        }
        $called = true;

        if (! self::$config->aliases) {
            $aliases = self::parseAliasesFile(self::$config->aliasesFile);
            self::$config->aliases = $aliases ?: self::$config->bareAliases;
        }
    }

    public static function parseAliasesFile($file)
    {
        if (! ($tree = Util::parseIni($file, true))) {

            return false;
        }

        $regex = Regex::$patt;

        // Some alias groups need further parsing to unpack useful information into the tree.
        foreach ($tree as $section => $items) {

            if ($section === 'declarations') {

                $store = array();
                foreach ($items as $prop_val => $aliases) {

                    list($prop, $value) = array_map('trim', explode(':', $prop_val));

                    foreach ($aliases as &$alias) {

                        list($p, $v) = explode(':', $alias);
                        $vendor = null;

                        // Try to detect the vendor from property and value in turn.
                        if (
                            preg_match($regex->vendorPrefix, $p, $m) ||
                            preg_match($regex->vendorPrefix, $v, $m)
                        ) {
                            $vendor = $m[1];
                        }
                        $alias = array($p, $v, $vendor);
                    }
                    $store[$prop][$value] = $aliases;
                }
                $tree['declarations'] = $store;
            }

            // Function groups.
            elseif (strpos($section, 'functions.') === 0) {

                $group = substr($section, strlen('functions'));

                $vendor_grouped_aliases = array();
                foreach ($items as $func_name => $aliases) {

                    // Assign group name to the aliasable function.
                    $tree['functions'][$func_name] = $group;

                    foreach ($aliases as $alias_func) {

                        // Only supporting vendor prefixed aliases, for now.
                        if (preg_match($regex->vendorPrefix, $alias_func, $m)) {

                            // We'll cache the function matching regex here.
                            $vendor_grouped_aliases[$m[1]]['find'][] = Regex::make("~{{ LB }}$func_name(?=\()~i");
                            $vendor_grouped_aliases[$m[1]]['replace'][] = $alias_func;
                        }
                    }
                }
                $tree['function_groups'][$group] = $vendor_grouped_aliases;
            }
        }

        $tree += self::$config->bareAliases;

        $tree['properties']['foo'] =
        $tree['at-rules']['foo'] =
        $tree['functions']['foo'] = array('-webkit-foo', '-moz-foo', '-ms-foo');

        return $tree;
    }

    /**
     * Deprecated.
     *
     * @see csscrush_file().
     */
    public static function file($file, $options = array())
    {
        return csscrush_file($file, $options);
    }

    /**
     * Deprecated.
     *
     * @see csscrush_tag().
     */
    public static function tag($file, $options = array(), $tag_attributes = array())
    {
        return csscrush_tag($file, $options, $tag_attributes);
    }

    /**
     * Deprecated.
     *
     * @see csscrush_inline().
     */
    public static function inline($file, $options = array(), $tag_attributes = array())
    {
        return csscrush_inline($file, $options, $tag_attributes);
    }

    /**
     * Deprecated.
     *
     * @see csscrush_string().
     */
    public static function string($string, $options = array())
    {
        return csscrush_string($string, $options);
    }

    /**
     * Deprecated.
     *
     * @see csscrush_stat().
     */
    public static function stat()
    {
        return csscrush_stat();
    }


    #############################
    #  Logging and stats.

    public static function printLog()
    {
        if (! empty(self::$process->debugLog)) {

            if (PHP_SAPI !== 'cli') {
                $out = array();
                foreach (self::$process->debugLog as $item) {
                    $out[] = '<pre>' . htmlspecialchars($item) . '</pre>';
                }
                echo implode('<hr>', $out);
            }
            else {
                echo implode(PHP_EOL, self::$process->debugLog), PHP_EOL;
            }
        }
    }

    public static function runStat()
    {
        $process = Crush::$process;

        foreach (func_get_args() as $stat_name) {

            switch ($stat_name) {
                case 'paths':
                    $process->stat['input_filename'] = $process->input->filename;
                    $process->stat['input_path'] = $process->input->path;
                    $process->stat['output_filename'] = $process->output->filename;
                    $process->stat['output_path'] = $process->output->dir . '/' . $process->output->filename;
                    break;

                case 'vars':
                    $process->stat['vars'] = array_map(function ($item) use ($process) {
                        return $process->tokens->restore($process->functions->apply($item), array('s', 'u', 'p'));
                    }, $process->vars);
                    break;

                case 'compile_time':
                    $process->stat['compile_time'] = microtime(true) - $process->stat['compile_start_time'];
                    unset($process->stat['compile_start_time']);
                    break;

                case 'selector_count':
                    $process->stat['selector_count'] = 0;
                    foreach ($process->tokens->store->r as $rule) {
                        $process->stat['selector_count'] += count($rule->selectors);
                    }
                    break;

                case 'rule_count':
                    $process->stat['rule_count'] = count($process->tokens->store->r);
                    break;
            }
        }
    }
}

function warning($message, $context = array()) {
    Crush::$process->errors[] = $message;
    $logger = Crush::$config->logger;
    if ($logger instanceof Logger) {
        $message = "[[CssCrush]] - $message";
    }
    $logger->warning($message, $context);
}

function notice($message, $context = array()) {
    Crush::$process->warnings[] = $message;
    $logger = Crush::$config->logger;
    if ($logger instanceof Logger) {
        $message = "[[CssCrush]] - $message";
    }
    $logger->notice($message, $context);
}

function debug($message, $context = array()) {
    Crush::$config->logger->debug($message, $context);
}

function log($message, $context = array(), $type = 'debug') {
    Crush::$config->logger->$type($message, $context);
}


Crush::init();
