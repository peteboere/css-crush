<?php
/**
 *
 * Main script. Includes core public API.
 *
 */
namespace CssCrush;

class Crush
{
    const VERSION = '2.1.0-beta';

    // Global settings.
    public static $config;

    // The current active process.
    public static $process;

    // Library root directory.
    public static $dir;

    // Init called once manually post class definition.
    public static function init()
    {
        self::$dir = dirname(dirname(__DIR__));

        self::$config = new \stdClass();

        // Plugin directories.
        self::$config->pluginDirs = array(self::$dir . '/plugins');

        self::$config->version = new Version(self::VERSION);
        self::$config->scriptDir = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
        self::$config->docRoot = self::resolveDocRoot();
        self::$config->logger = new Logger();

        // Set default IO handler.
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
        self::$config->selectorAliases = array();
        self::$config->plugins = array();
        self::$config->options = new Options();

        // Register stock formatters.
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
                warning("[[CssCrush]] - Could not get a valid DOCUMENT_ROOT reference.");
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
        $tree = @parse_ini_file($file, true);

        if ($tree === false) {
            notice("[[CssCrush]] - Could not parse aliases file '$file'.");

            return false;
        }

        $regex = Regex::$patt;

        // Some alias groups need further parsing to unpack useful information into the tree.
        foreach ($tree as $section => $items) {

            if ($section === 'declarations') {

                $store = array();
                foreach ($items as $prop_val => $aliases) {

                    list($prop, $value) = array_map('trim', explode('.', $prop_val));

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

        return $tree + self::$config->bareAliases;
    }


    #############################
    #  Public API.

    /**
     * Process host CSS file and return a new compiled file.
     *
     * @param string $file  URL or System path to the host CSS file.
     * @param mixed $options  An array of options or null.
     * @return string  The public path to the compiled file or an empty string.
     */
    public static function file($file, $options = array())
    {
        self::$process = new Process($options, array('io_context' => 'file'));

        $config = self::$config;
        $process = self::$process;
        $options = $process->options;
        $doc_root = $process->docRoot;

        $process->input->raw = $file;

        if (! ($input_file = Util::resolveUserPath($file))) {
            warning('[[CssCrush]] - Input file \'' . basename($file) . '\' not found.');

            return '';
        }

        if (! $process->resolveContext(dirname($input_file), $input_file)) {

            return '';
        }

        Crush::runStat('hostfile');

        if ($options->cache) {
            $process->cacheData = $process->io('getCacheData');
            if ($process->io('validateCache')) {
                $file_url = $process->io('getOutputUrl');
                $process->release();

                return $file_url;
            }
        }

        $stream = $process->compile();

        return $process->io('write', $stream) ?  $process->io('getOutputUrl') : '';
    }

    /**
     * Process host CSS file and return an HTML link tag with populated href.
     *
     * @param string $file  Absolute or relative path to the host CSS file.
     * @param mixed $options  An array of options or null.
     * @param array $attributes  An array of HTML attributes.
     * @return string  HTML link tag or error message inside HTML comment.
     */
    public static function tag($file, $options = array(), $tag_attributes = array())
    {
        $file = self::file($file, $options);

        if (! empty($file)) {
            $tag_attributes['href'] = $file;
            $tag_attributes += array(
                'rel' => 'stylesheet',
                'media' => 'all',
            );
            $attrs = Util::htmlAttributes($tag_attributes, array('rel', 'href', 'media'));

            return "<link$attrs />\n";
        }
        else {
            // Return an HTML comment with message on failure
            $class = __CLASS__;
            $errors = implode("\n", self::$process->errors);

            return "<!-- $class: $errors -->\n";
        }
    }

    /**
     * Process host CSS file and return CSS as text wrapped in html style tags.
     *
     * @param string $file  Absolute or relative path to the host CSS file.
     * @param mixed $options  An array of options or null.
     * @param array $attributes  An array of HTML attributes, set false to return CSS text without tag.
     * @return string  HTML link tag or error message inside HTML comment.
     */
    public static function inline($file, $options = array(), $tag_attributes = array())
    {
        // For inline output set boilerplate to not display by default.
        if (! is_array($options)) {
            $options = array();
        }
        if (! isset($options['boilerplate'])) {
            $options['boilerplate'] = false;
        }

        $file = self::file($file, $options);

        if (! empty($file)) {

            // On success fetch the CSS text.
            $content = file_get_contents(self::$process->output->dir . '/' . self::$process->output->filename);
            $tag_open = '';
            $tag_close = '';

            if (is_array($tag_attributes)) {
                $attr_string = Util::htmlAttributes($tag_attributes);
                $tag_open = "<style$attr_string>";
                $tag_close = '</style>';
            }
            return "$tag_open{$content}$tag_close\n";
        }
        else {

            // Return an HTML comment with message on failure.
            $class = __CLASS__;
            $errors = implode("\n", self::$process->errors);
            return "<!-- $class: $errors -->\n";
        }
    }

    /**
     * Compile a raw string of CSS string and return it.
     *
     * @param string $string  CSS text.
     * @param mixed $options  An array of options or null.
     * @return string  CSS text.
     */
    public static function string($string, $options = array())
    {
        // For strings set boilerplate to not display by default
        if (! isset($options['boilerplate'])) {
            $options['boilerplate'] = false;
        }

        self::$process = new Process($options, array('io_context' => 'filter'));

        $config = self::$config;
        $process = self::$process;
        $options = $process->options;

        // Set the path context if one is given.
        // Fallback to document root.
        if (! empty($options->context)) {
            $process->resolveContext($options->context);
        }
        else {
            $process->resolveContext();
        }

        // Set the string on the input object.
        $process->input->string = $string;

        // Import files may be ignored.
        if (isset($options->no_import)) {
            $process->input->importIgnore = true;
        }

        return $process->compile()->__toString();
    }

    /**
     * Get debug info.
     */
    public static function stat()
    {
        $process = Crush::$process;
        $stats = $process->stat;

        // Get logged errors as late as possible.
        $stats['errors'] = $process->errors;
        $stats += array(
            'compile_time' => 0,
        );

        return $stats;
    }


    #############################
    #  Global selector aliases.

    public static function addSelectorAlias($name, $body)
    {
        Crush::$config->selectorAliases[$name] = is_callable($body) ? $body : new Template($body);
    }

    public static function removeSelectorAlias($name)
    {
        unset(Crush::$config->selectorAliases[$name]);
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
                case 'hostfile':
                    $process->stat['hostfile'] = $process->input->filename;
                    break;

                case 'vars':
                    $process->stat['vars'] = array_map(function ($item) use ($process) {
                        return $process->tokens->restore(Functions::executeOnString($item), array('s', 'u', 'p'));
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


function log($message, $context = array(), $type = 'debug') {
    Crush::$config->logger->{$type}($message, $context);
}

function debug($message, $context = array()) {
    Crush::$config->logger->debug($message, $context);
}

function notice($message, $context = array()) {
    Crush::$config->logger->notice($message, $context);
}

function warning($message, $context = array()) {
    Crush::$config->logger->warning($message, $context);
}


Crush::init();
