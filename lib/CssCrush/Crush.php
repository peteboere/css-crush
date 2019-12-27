<?php
/**
 *
 * Core public API.
 *
 */
namespace CssCrush;

class Crush
{
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

        self::$config->pluginDirs = [self::$dir . '/plugins'];
        self::$config->scriptDir = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
        self::$config->docRoot = self::resolveDocRoot();
        self::$config->logger = new Logger();
        self::$config->io = 'CssCrush\IO';

        // Shared resources.
        self::$config->vars = [];
        self::$config->aliasesFile = self::$dir . '/aliases.ini';
        self::$config->aliases = [];
        self::$config->bareAliases = [
            'properties' => [],
            'functions' => [],
            'function_groups' => [],
            'declarations' => [],
            'at-rules' => [],
        ];
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

    public static function plugin($name = null, callable $callback = null)
    {
        static $plugins = [];

        if (! $callback) {
            return isset($plugins[$name]) ? $plugins[$name] : null;
        }

        $plugins[$name] = $callback;
    }

    public static function enablePlugin($name)
    {
        $plugin = self::plugin($name);
        if (! $plugin) {
            $path = self::$dir . "/plugins/$name.php";
            if (! file_exists($path)) {
                notice("Plugin '$name' not found.");
                return;
            }
            require_once $path;
            $plugin = self::plugin($name);
        }

        $plugin(self::$process);
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

                $store = [];
                foreach ($items as $prop_val => $aliases) {

                    list($prop, $value) = array_map('trim', explode(':', $prop_val));

                    foreach ($aliases as &$alias) {

                        list($p, $v) = explode(':', $alias);
                        $vendor = null;

                        // Try to detect the vendor from property and value in turn.
                        if (
                            preg_match($regex->vendorPrefix, $p, $m)
                            || preg_match($regex->vendorPrefix, $v, $m)
                        ) {
                            $vendor = $m[1];
                        }
                        $alias = [$p, $v, $vendor];
                    }
                    $store[$prop][$value] = $aliases;
                }
                $tree['declarations'] = $store;
            }

            // Function groups.
            elseif (strpos($section, 'functions.') === 0) {

                $group = substr($section, strlen('functions'));

                $vendor_grouped_aliases = [];
                foreach ($items as $func_name => $aliases) {

                    // Assign group name to the aliasable function.
                    $tree['functions'][$func_name] = $group;

                    foreach ($aliases as $alias_func) {

                        // Only supporting vendor prefixed aliases, for now.
                        if (preg_match($regex->vendorPrefix, $alias_func, $m)) {

                            // We'll cache the function matching regex here.
                            $vendor_grouped_aliases[$m[1]]['find'][] = Regex::make("~{{ LB }}$func_name(?=\()~iS");
                            $vendor_grouped_aliases[$m[1]]['replace'][] = $alias_func;
                        }
                    }
                }
                $tree['function_groups'][$group] = $vendor_grouped_aliases;
                unset($tree[$section]);
            }
        }

        $tree += self::$config->bareAliases;

        // Persisting dummy aliases for testing purposes.
        $tree['properties']['foo'] =
        $tree['at-rules']['foo'] =
        $tree['functions']['foo'] = ['-webkit-foo', '-moz-foo', '-ms-foo'];

        return $tree;
    }

    #############################
    #  Logging and stats.

    public static function printLog()
    {
        if (! empty(self::$process->debugLog)) {

            if (PHP_SAPI !== 'cli') {
                $out = [];
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
                        return $process->tokens->restore($process->functions->apply($item), ['s', 'u', 'p']);
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

function warning($message, $context = []) {
    Crush::$process->errors[] = $message;
    $logger = Crush::$config->logger;
    if ($logger instanceof Logger) {
        $message = "[CssCrush] $message";
    }
    $logger->warning($message, $context);
}

function notice($message, $context = []) {
    Crush::$process->warnings[] = $message;
    $logger = Crush::$config->logger;
    if ($logger instanceof Logger) {
        $message = "[CssCrush] $message";
    }
    $logger->notice($message, $context);
}

function debug($message, $context = []) {
    Crush::$config->logger->debug($message, $context);
}

function log($message, $context = [], $type = 'debug') {
    Crush::$config->logger->$type($message, $context);
}


Crush::init();
