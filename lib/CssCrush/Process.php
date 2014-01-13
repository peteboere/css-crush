<?php
/**
 *
 *  The main class for compiling.
 *
 */
namespace CssCrush;

class Process
{
    public function __construct($user_options = array(), $dev_options = array())
    {
        $config = Crush::$config;

        Crush::loadAssets();

        $dev_options += array('io_context' => 'filter');
        $this->ioContext = $dev_options['io_context'];

        // Initialize properties.
        $this->cacheData = array();
        $this->mixins = array();
        $this->fragments = array();
        $this->references = array();
        $this->charset = null;
        $this->sources = array();
        $this->vars = array();
        $this->settings = array();
        $this->misc = new \stdClass();
        $this->input = new \stdClass();
        $this->output = new \stdClass();
        $this->tokens = new Tokens();
        $this->hooks = new Hooks();
        $this->sourceMap = null;
        $this->selectorAliases = array();
        $this->selectorAliasesPatt = null;

        $this->debugLog = array();
        $this->errors = array();
        $this->stat = array();

        // Copy config values.
        $this->plugins = $config->plugins;
        $this->aliases = $config->aliases;

        // Options.
        $this->options = new Options($user_options, $config->options);

        // Keep track of global vars to maintain cache integrity.
        $this->options->global_vars = $config->vars;

        // Shortcut commonly used options to avoid __get() overhead.
        $this->docRoot = isset($this->options->doc_root) ? $this->options->doc_root : $config->docRoot;
        $this->addTracingStubs = in_array('stubs', $this->options->__get('trace'));
        $this->generateMap = $this->ioContext === 'file' && $this->options->__get('source_map');
        $this->ruleFormatter = $this->options->__get('formatter');
        $this->minifyOutput = $this->options->__get('minify');
        $this->newline = $this->options->__get('newlines');
    }

    public function release()
    {
        unset(
            $this->tokens,
            $this->mixins,
            $this->references,
            $this->cacheData,
            $this->misc,
            $this->plugins,
            $this->aliases,
            $this->selectorAliases
        );
    }

    public function resolveContext($input_dir = null, $input_file = null)
    {
        if ($input_file) {
            $this->input->path = $input_file;
            $this->input->filename = basename($input_file);
            $this->input->mtime = filemtime($input_file);
        }
        else {
            $this->input->path = null;
            $this->input->filename = null;
        }

        $this->input->dir = $input_dir ?: $this->docRoot;
        $this->input->dirUrl = substr($input_dir, strlen($this->docRoot));

        $this->output->dir = $this->io('getOutputDir');
        $this->output->filename = $this->io('getOutputFileName');
        $this->output->dirUrl = substr($this->output->dir, strlen($this->docRoot));

        $context_resolved = true;
        if ($input_file) {
            $context_resolved = $this->io('testOutputDir');
        }

        $this->io('init');

        return $context_resolved;
    }

    public function io($method)
    {
        // Get argument list (excluding the method name which comes first).
        $args = func_get_args();
        array_shift($args);

        return call_user_func_array(array(Crush::$config->io, $method), $args);
    }


    #############################
    #  Boilerplate.

    protected function getBoilerplate()
    {
        $file = false;
        $boilerplate_option = $this->options->boilerplate;

        if ($boilerplate_option === true) {
            $file = Crush::$dir . '/boilerplate.txt';
        }
        elseif (is_string($boilerplate_option)) {
            if (file_exists($boilerplate_option)) {
                $file = $boilerplate_option;
            }
        }

        // Return an empty string if no file is found.
        if (! $file) {
            return '';
        }

        $boilerplate = file_get_contents($file);

        // Substitute any tags
        if (preg_match_all('~\{\{([^}]+)\}\}~', $boilerplate, $boilerplate_matches)) {

            // Command line arguments (if any).
            $command_args = 'n/a';
            if (isset($_SERVER['argv'])) {
                $argv = $_SERVER['argv'];
                array_shift($argv);
                $command_args = 'csscrush ' . implode(' ', $argv);
            }

            $tags = array(
                'datetime' => @date('Y-m-d H:i:s O'),
                'year' => @date('Y'),
                'version' => csscrush_version(),
                'command' => $command_args,
                'plugins' => implode(',', array_keys($this->plugins)),
                'compile_time' => function () {
                    $now = microtime(true) - Crush::$process->stat['compile_start_time'];
                    return round($now, 4) . ' seconds';
                },
            );

            foreach ($boilerplate_matches[0] as $index => $tag) {
                $tag_name = $boilerplate_matches[1][$index];
                $replacement = '?';
                if (isset($tags[$tag_name])) {
                    $replacement =  is_callable($tags[$tag_name]) ? $tags[$tag_name]() : $tags[$tag_name];
                }
                $replacements[] = $replacement;
            }
            $boilerplate = str_replace($boilerplate_matches[0], $replacements, $boilerplate);
        }

        // Pretty print.
        $EOL = $this->newline;
        $boilerplate = preg_split('~[\t]*'. Regex::$classes->newline . '[\t]*~', $boilerplate);
        $boilerplate = array_map('trim', $boilerplate);
        $boilerplate = "$EOL * " . implode("$EOL * ", $boilerplate);

        return "/*{$boilerplate}$EOL */$EOL";
    }


    #############################
    #  Selector aliases.

    protected function resolveSelectorAliases()
    {
        $this->stream->pregReplaceCallback(
            Regex::make('~@selector-alias +\:?({{ident}}) +([^;]+) *;~iS'),
            function ($m) {
                $name = strtolower($m[1]);
                Crush::$process->selectorAliases[$name] = new Template(Util::stripCommentTokens($m[2]));
            });

        // Merge with global selector aliases.
        $this->selectorAliases += Crush::$config->selectorAliases;

        // Create the selector aliases pattern and store it.
        if ($this->selectorAliases) {
            $names = implode('|', array_keys($this->selectorAliases));
            $this->selectorAliasesPatt
                = Regex::make('~\:(' . $names . '){{RB}}(\()?~iS');
        }
    }

    public static function applySelectorAliases($str)
    {
        $process = Crush::$process;

        if (! $process->selectorAliases || ! preg_match($process->selectorAliasesPatt, $str)) {
            return $str;
        }

        $table =& $process->selectorAliases;

        while (preg_match_all($process->selectorAliasesPatt, $str, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {

            $selector_alias_call = end($m);
            $selector_alias_name = strtolower($selector_alias_call[1][0]);

            $start = $selector_alias_call[0][1];
            $length = strlen($selector_alias_call[0][0]);
            $args = array();

            // It's a function alias if a start paren is matched.
            if (isset($selector_alias_call[2])) {

                // Parse argument list.
                if (preg_match(Regex::$patt->parens, $str, $parens, PREG_OFFSET_CAPTURE, $start)) {
                    $args = Functions::parseArgs($parens[2][0]);

                    // Amend offsets.
                    $paren_start = $parens[0][1];
                    $paren_len = strlen($parens[0][0]);
                    $length = ($paren_start + $paren_len) - $start;
                }
            }

            // Resolve the selector alias value to a template instance if a callable is given.
            $template = $table[$selector_alias_name];
            if (is_callable($template)) {
                $template = new Template($template($args));
            }

            $str = substr_replace($str, $template->apply($args), $start, $length);
        }

        return $str;
    }


    #############################
    #  Aliases.

    protected function filterAliases()
    {
        // If a vendor target is given, we prune the aliases array.
        $vendors = $this->options->vendor_target;

        // Default vendor argument, so use all aliases as normal.
        if ('all' === $vendors) {

            return;
        }

        // For expicit 'none' argument turn off aliases.
        if ('none' === $vendors) {
            $this->aliases = Crush::$config->bareAliases;

            return;
        }

        // Normalize vendor names and create regex patt.
        $vendor_names = (array) $vendors;
        foreach ($vendor_names as &$vendor_name) {
            $vendor_name = trim($vendor_name, '-');
        }
        $vendor_patt = '~^\-(' . implode($vendor_names, '|') . ')\-~i';


        // Loop the aliases array, filter down to the target vendor.
        foreach ($this->aliases as $section => $group_array) {

            // Declarations aliases.
            if ($section === 'declarations') {

                foreach ($group_array as $property => $values) {
                    foreach ($values as $value => $prefix_values) {
                        foreach ($prefix_values as $index => $declaration) {

                            if (in_array($declaration[2], $vendor_names)) {
                                continue;
                            }

                            // Unset uneeded aliases.
                            unset($this->aliases[$section][$property][$value][$index]);

                            if (empty($this->aliases[$section][$property][$value])) {
                                unset($this->aliases[$section][$property][$value]);
                            }
                            if (empty($this->aliases[$section][$property])) {
                                unset($this->aliases[$section][$property]);
                            }
                        }
                    }
                }
            }

            // Function group aliases.
            elseif ($section === 'function_groups') {

                foreach ($group_array as $func_group => $vendors) {
                    foreach ($vendors as $vendor => $replacements) {

                        if (! in_array($vendor, $vendor_names)) {
                            unset($this->aliases['function_groups'][$func_group][$vendor]);
                        }
                    }
                }
            }

            // Everything else.
            else {
                foreach ($group_array as $alias_keyword => $prefix_array) {

                    // Skip over pointers to function groups.
                    if ($prefix_array[0] === '.') {
                        continue;
                    }

                    $result = array();

                    foreach ($prefix_array as $prefix) {
                        if (preg_match($vendor_patt, $prefix)) {
                            $result[] = $prefix;
                        }
                    }

                    // Prune the whole alias keyword if there is no result.
                    if (empty($result)) {
                        unset($this->aliases[$section][$alias_keyword]);
                    }
                    else {
                        $this->aliases[$section][$alias_keyword] = $result;
                    }
                }
            }
        }
    }


    #############################
    #  Plugins.

    protected function filterPlugins()
    {
        $options = $this->options;
        $config = Crush::$config;

        // Checking for table keys is more convenient than array searching.
        $disable = array_flip($options->disable);
        $enable = array_flip($options->enable);

        if (isset($disable['all'])) {
            $disable = $config->plugins;
        }

        // Remove option disabled plugins from the list, and disable them.
        if ($disable) {
            foreach ($disable as $plugin_name => $index) {
                Plugin::disable($plugin_name);
                unset($this->plugins[$plugin_name]);
            }
        }

        // Secondly add option enabled plugins to the list.
        if ($enable) {
            foreach ($enable as $plugin_name => $index) {
                $this->plugins[$plugin_name] = true;
            }
        }

        foreach ($this->plugins as $plugin_name => $bool) {
            Plugin::enable($plugin_name);
        }
    }


    #############################
    #  Variables.

    protected function captureVars()
    {
        $patt = Regex::make('~@define(?:\s*{{ block }}|\s+(?<name>{{ ident }})\s+(?<value>[^;]+)\s*;)~iS');

        $this->stream->pregReplaceCallback($patt, function ($m) {
            if (isset($m['name'])) {
                Crush::$process->vars[$m['name']] = $m['value'];
            }
            else {
                Crush::$process->vars = DeclarationList::parse($m['block_content'], array(
                        'keyed' => true,
                        'ignore_directives' => true,
                    )) + Crush::$process->vars;
            }
        });

        // In-file variables override global variables.
        $this->vars += Crush::$config->vars;

        // Runtime variables override in-file variables.
        if (! empty($this->options->vars)) {
            $this->vars = $this->options->vars + $this->vars;
        }

        // Place variables referenced inside variables.
        foreach ($this->vars as $name => &$value) {
            $value = preg_replace_callback(Regex::$patt->varFunction, 'CssCrush\Process::cb_placeVars', $value);
        }
    }

    protected function placeAllVars()
    {
        // Place variables in main stream.
        self::placeVars($this->stream->raw);

        $raw_tokens =& $this->tokens->store;

        // Repeat above steps for variables embedded in string tokens.
        foreach ($raw_tokens->s as $label => &$value) {
            self::placeVars($value);
        }

        // Repeat above steps for variables embedded in URL tokens.
        foreach ($raw_tokens->u as $label => $url) {
            if (! $url->isData && self::placeVars($url->value)) {
                // Re-evaluate $url->value if anything has been interpolated.
                $url->evaluate();
            }
        }
    }

    static protected function placeVars(&$value)
    {
        // Variables with no default value.
        $value = preg_replace_callback(Regex::$patt->varFunction,
            'CssCrush\Process::cb_placeVars', $value, -1, $vars_placed);

        // Variables with default value.
        if (strpos($value, '$(') !== false) {

            // Assume at least one replace.
            $vars_placed = 1;

            // Variables may be nested so need to apply full function parsing.
            $value = Functions::executeOnString($value, '~(\$)\(~',
                array('$' => function ($raw_args) {
                    list($name, $default_value) = Functions::parseArgsSimple($raw_args);
                    if (isset(Crush::$process->vars[$name])) {
                        return Crush::$process->vars[$name];
                    }
                    else {
                        return $default_value;
                    }
                }));
        }

        // If we know replacements have been made we may want to update $value. e.g URL tokens.
        return $vars_placed;
    }

    static protected function cb_placeVars($m)
    {
        $var_name = $m[1];
        if (isset(Crush::$process->vars[$var_name])) {
            return Crush::$process->vars[$var_name];
        }
    }


    #############################
    #  @settings blocks.

    protected function resolveSettings()
    {
        $patt = Regex::make('~@settings(?:\s*{{ block }}|\s+(?<name>{{ ident }})\s+(?<value>[^;]+)\s*;)~iS');
        $captured_settings = array();

        $this->stream->pregReplaceCallback($patt, function ($m) use (&$captured_settings) {
            if (isset($m['name'])) {
                $captured_settings[strtolower($m['name'])] = $m['value'];
            }
            else {
                $captured_settings = DeclarationList::parse($m['block_content'], array(
                    'keyed' => true,
                    'ignore_directives' => true,
                    'lowercase_keys' => true,
                )) + $captured_settings;
            }

            return '';
        });

        // Like variables, settings passed via options override settings defined in CSS.
        $this->settings = new Settings($this->options->settings + $captured_settings);
    }


    #############################
    #  @ifdefine blocks.

    protected function resolveIfDefines()
    {
        $ifdefine_patt = Regex::make('~@ifdefine \s+ (not \s+)? ({{ ident }}) \s* \{~ixS');

        $matches = $this->stream->matchAll($ifdefine_patt);

        while ($match = array_pop($matches)) {

            $curly_match = new BalancedMatch($this->stream, $match[0][1]);

            if (! $curly_match->match) {
                continue;
            }

            $negate = $match[1][1] != -1;
            $name = $match[2][0];
            $name_defined = isset($this->vars[$name]);

            if (! $negate && $name_defined || $negate && ! $name_defined) {
                // Test resolved true so include the innards.
                $curly_match->unWrap();
            }
            else {
                // Recontruct the stream without the innards.
                $curly_match->replace('');
            }
        }
    }


    #############################
    #  Mixins.

    protected function captureMixins()
    {
        $this->stream->pregReplaceCallback(Regex::$patt->mixin, function ($m) {
            Crush::$process->mixins[$m['name']] = new Mixin($m['block_content']);
        });
    }


    #############################
    #  Fragments.

    protected function resolveFragments()
    {
        $fragments =& Crush::$process->fragments;

        $this->stream->pregReplaceCallback(Regex::$patt->fragmentCapture, function ($m) use (&$fragments) {
            $fragments[$m['name']] = new Fragment(
                    $m['block_content'],
                    array('name' => strtolower($m['name']))
                );
            return '';
        });

        $this->stream->pregReplaceCallback(Regex::$patt->fragmentInvoke, function ($m) use (&$fragments) {
            $fragment = isset($fragments[$m['name']]) ? $fragments[$m['name']] : null;
            if ($fragment) {
                $args = array();
                if (isset($m['parens'])) {
                    $args = Functions::parseArgs($m['parens_content']);
                }
                return $fragment->apply($args);
            }
            return '';
        });
    }


    #############################
    #  Rules.

    public function captureRules()
    {
        $this->stream->pregReplaceCallback(Regex::$patt->rule, function ($m) {

            $selector = trim($m['selector']);
            $block = trim($m['block_content']);

            // Ignore and remove empty rules.
            if (empty($block) || empty($selector)) {
                return '';
            }

            $rule = new Rule($selector, $block, $m['trace_token']);

            // Store rules if they have declarations or extend arguments.
            if (! empty($rule->declarations->store) || $rule->extendArgs) {

                return Crush::$process->tokens->add($rule, 'r', $rule->label);
            }
        });
    }

    protected function processRules()
    {
        // Create table of name/selector to rule references.
        $named_references = array();
        foreach ($this->tokens->store->r as $rule) {
            if ($rule->name) {
                $named_references[$rule->name] = $rule;
            }
            foreach ($rule->selectors as $selector) {
                $this->references[$selector->readableValue] = $rule;
            }
        }

        // Explicit named references take precedence.
        $this->references = $named_references + $this->references;

        foreach ($this->tokens->store->r as $rule) {

            $rule->declarations->flatten($rule);
            $rule->declarations->process($rule);

            $this->hooks->run('rule_prealias', $rule);

            $rule->declarations->aliasProperties($rule->vendorContext);
            $rule->declarations->aliasFunctions($rule->vendorContext);
            $rule->declarations->aliasDeclarations($rule->vendorContext);

            $this->hooks->run('rule_postalias', $rule);

            $rule->selectors->expand();
            $rule->applyExtendables();

            $this->hooks->run('rule_postprocess', $rule);
        }
    }


    #############################
    #  @in blocks.

    protected function resolveInBlocks()
    {
        $matches = $this->stream->matchAll('~@in\s+([^{]+)\{~iS');
        $tokens = Crush::$process->tokens;

        // Move through the matches in reverse order.
        while ($match = array_pop($matches)) {

            $match_start_pos = $match[0][1];
            $raw_argument = trim($match[1][0]);

            $arguments = Util::splitDelimList(Process::applySelectorAliases($raw_argument));

            $curly_match = new BalancedMatch($this->stream, $match_start_pos);

            if (! $curly_match->match || empty($raw_argument)) {
                continue;
            }

            // Match all the rule tokens.
            $rule_matches = Regex::matchAll(
                Regex::$patt->r_token, $curly_match->inside());

            foreach ($rule_matches as $rule_match) {

                // Get the rule instance.
                $rule = $tokens->get($rule_match[0][0]);

                // Using arguments create new selector list for the rule.
                $new_selector_list = array();

                foreach ($arguments as $arg_selector) {

                    foreach ($rule->selectors as $rule_selector) {

                        $use_parent_symbol = strpos($rule_selector->value, '&') !== false;

                        // Skipping the prefix.
                        if (! $rule_selector->allowPrefix && ! $use_parent_symbol) {

                            $new_selector_list[$rule_selector->readableValue] = $rule_selector;
                        }

                        // Positioning the prefix with parent symbol "&".
                        elseif ($use_parent_symbol) {

                            $new_value = str_replace(
                                    '&',
                                    $arg_selector,
                                    $rule_selector->value);

                            $new = new Selector($new_value);
                            $new_selector_list[$new->readableValue] = $new;
                        }

                        // Prepending the prefix.
                        else {

                            $new = new Selector("$arg_selector {$rule_selector->value}");
                            $new_selector_list[$new->readableValue] = $new;
                        }
                    }
                }
                $rule->selectors->store = $new_selector_list;
            }

            $curly_match->unWrap();
        }
    }


    #############################
    #  @-rule aliasing.

    protected function aliasAtRules()
    {
        if (empty($this->aliases['at-rules'])) {

            return;
        }

        $aliases = $this->aliases['at-rules'];
        $regex = Regex::$patt;

        foreach ($aliases as $at_rule => $at_rule_aliases) {

            $matches = $this->stream->matchAll("~@$at_rule" . '[\s{]~i');

            // Find at-rules that we want to alias.
            while ($match = array_pop($matches)) {

                $curly_match = new BalancedMatch($this->stream, $match[0][1]);

                if (! $curly_match->match) {
                    // Couldn't match the block.
                    continue;
                }

                // Build up string with aliased blocks for splicing.
                $original_block = $curly_match->whole();
                $new_blocks = array();

                foreach ($at_rule_aliases as $alias) {

                    // Copy original block, replacing at-rule with alias name.
                    $copy_block = str_replace("@$at_rule", "@$alias", $original_block);

                    // Aliases are nearly always prefixed, capture the current vendor name.
                    preg_match($regex->vendorPrefix, $alias, $vendor);

                    $vendor = $vendor ? $vendor[1] : null;

                    // Duplicate rules.
                    if (preg_match_all($regex->r_token, $copy_block, $copy_matches)) {

                        $originals = array();
                        $replacements = array();

                        foreach ($copy_matches[0] as $rule_label) {

                            // Clone the matched rule.
                            $originals[] = $rule_label;
                            $clone_rule = clone $this->tokens->get($rule_label);

                            $clone_rule->vendorContext = $vendor;

                            // Store the clone.
                            $replacements[] = $this->tokens->add($clone_rule);
                        }

                        // Finally replace the original labels with the cloned rule labels.
                        $copy_block = str_replace($originals, $replacements, $copy_block);
                    }

                    // Add the copied block to the stack.
                    $new_blocks[] = $copy_block;
                }

                // The original version is always pushed last in the list.
                $new_blocks[] = $original_block;

                // Splice in the blocks.
                $curly_match->replace(implode("\n", $new_blocks));
            }
        }
    }


    #############################
    #  Compile / collate.

    protected function collate()
    {
        $options = $this->options;
        $minify = $options->minify;
        $regex = Regex::$patt;
        $EOL = $this->newline;

        // Formatting replacements.
        // Strip newlines added during processing.
        $regex_replacements = array();
        $regex_replacements['~\n+~'] = '';

        if ($minify) {
            // Strip whitespace around colons used in @-rule arguments.
            $regex_replacements['~ ?\: ?~'] = ':';
        }
        else {
            // Pretty printing.
            $regex_replacements['~}~'] = "$0$EOL$EOL";
            $regex_replacements['~([^\s])\{~'] = "$1 {";
            $regex_replacements['~ ?(@[^{]+\{)~'] = "$1$EOL";
            $regex_replacements['~ ?(@[^;]+\;)~'] = "$1$EOL";

            // Trim leading spaces on @-rules and some tokens.
            $regex_replacements[Regex::make('~ +([@}]|\?[rc]{{token-id}}\?)~S')] = "$1";

            // Additional newline between adjacent rules and comments.
            $regex_replacements[Regex::make('~({{r-token}}) (\s*) ({{c-token}})~xS')] = "$1$EOL$2$3";
        }

        // Apply all formatting replacements.
        $this->stream->pregReplaceHash($regex_replacements)->lTrim();

        $this->stream->restore('r');

        // Record stats then drop rule objects to reclaim memory.
        Crush::runStat('selector_count', 'rule_count', 'vars');
        $this->tokens->store->r = array();

        // If specified, apply advanced minification.
        if (is_array($minify)) {
            if (in_array('colors', $minify)) {
                $this->minifyColors();
            }
        }

        $this->decruft();

        if (! $minify) {
            // Add newlines after comments.
            foreach ($this->tokens->store->c as $token => &$comment) {
                $comment .= $EOL;
            }

            // Insert comments and do final whitespace cleanup.
            $this->stream
                ->restore('c')
                ->trim()
                ->append($EOL);
        }

        // Insert URLs.
        $urls = $this->tokens->store->u;
        if ($urls) {

            $link = Util::getLinkBetweenPaths($this->output->dir, $this->input->dir);
            $make_urls_absolute = $options->rewrite_import_urls === 'absolute';

            foreach ($urls as $token => $url) {

                if ($url->isRelative && ! $url->noRewrite) {
                    if ($make_urls_absolute) {
                        $url->toRoot();
                    }
                    // If output dir is different to input dir prepend a link between the two.
                    elseif ($link) {
                        $url->prepend($link);
                    }
                }
            }
        }

        if ($options->boilerplate) {
            $this->stream->prepend($this->getBoilerplate());
        }

        if ($this->charset) {
            $this->stream->prepend("@charset \"$this->charset\";$EOL");
        }

        $this->stream->restore(array('u', 's'));

        if ($this->addTracingStubs) {
            $this->stream->restore('t', false, array($this, 'generateTracingStub'));
        }
        if ($this->generateMap) {
            $this->generateSourceMap();
        }
    }

    public function preCompile()
    {
        // Ensure relevant ini settings aren't too conservative.
        if (ini_get('pcre.backtrack_limit') < 1000000) {
            ini_set('pcre.backtrack_limit', 1000000);
        }
        if (preg_match('~^(\d+)M$~', ini_get('memory_limit'), $m) && $m[1] < 128) {
            ini_set('memory_limit', '128M');
        }

        $this->filterPlugins();
        $this->filterAliases();

        Functions::setMatchPatt();

        $this->stat['compile_start_time'] = microtime(true);
    }

    public function postCompile()
    {
        foreach ($this->plugins as $plugin_name => $bool) {
            Plugin::disable($plugin_name);
        }

        $this->release();

        Crush::runStat('compile_time');
    }

    public function compile()
    {
        $this->preCompile();

        // Collate hostfile and imports.
        $this->stream = new Stream(Importer::hostfile($this->input));

        $this->captureVars();

        $this->placeAllVars();

        $this->resolveIfDefines();

        $this->resolveSettings();

        // Capture phase 1 hook: After all variables and settings have resolved.
        $this->hooks->run('capture_phase1', $this);

        $this->resolveSelectorAliases();

        $this->captureMixins();

        $this->resolveFragments();

        // Capture phase 2 hook: After most built-in directives have resolved.
        $this->hooks->run('capture_phase2', $this);

        $this->captureRules();

        $this->resolveInBlocks();

        $this->aliasAtRules();

        $this->processRules();

        $this->collate();

        $this->postCompile();

        return $this->stream;
    }


    #############################
    #  Source maps.

    public function generateSourceMap()
    {
        $this->sourceMap = array(
            'version' => '3',
            'file' => $this->output->filename,
            'sources' => array(),
        );
        foreach ($this->sources as $source) {
            $this->sourceMap['sources'][] = Util::getLinkBetweenPaths($this->output->dir, $source, false);
        }

        $token_patt = Regex::make('~\?[tm]{{token-id}}\?~S');
        $mappings = array();
        $lines = preg_split(Regex::$patt->newline, $this->stream->raw);
        $tokens =& $this->tokens->store;

        // All mappings are calculated as delta values.
        $previous_dest_col = 0;
        $previous_src_file = 0;
        $previous_src_line = 0;
        $previous_src_col = 0;

        foreach ($lines as $line_number => &$line_text) {

            $line_segments = array();

            while (preg_match($token_patt, $line_text, $m, PREG_OFFSET_CAPTURE)) {

                list($token, $dest_col) = $m[0];
                $token_type = $token[1];

                if (isset($tokens->{$token_type}[$token])) {

                    list($src_file, $src_line, $src_col) = explode(',', $tokens->{$token_type}[$token]);
                    $line_segments[] =
                        Util::vlqEncode($dest_col - $previous_dest_col) .
                        Util::vlqEncode($src_file - $previous_src_file) .
                        Util::vlqEncode($src_line - $previous_src_line) .
                        Util::vlqEncode($src_col - $previous_src_col);

                    $previous_dest_col = $dest_col;
                    $previous_src_file = $src_file;
                    $previous_src_line = $src_line;
                    $previous_src_col = $src_col;
                }
                $line_text = substr_replace($line_text, '', $dest_col, strlen($token));
            }

            $mappings[] = implode(',', $line_segments);
        }

        $this->stream->raw = implode($this->newline, $lines);
        $this->sourceMap['mappings'] = implode(';', $mappings);
    }

    public function generateTracingStub($m)
    {
        if (! ($value = $this->tokens->get($m[0]))) {
            return '';
        }

        list($source_index, $line) = explode(',', $value);
        $line += 1;

        // Get the currently processed file path, and escape it.
        $current_file = 'file://' . str_replace(' ', '%20', $this->sources[$source_index]);
        $current_file = preg_replace('~[^\w-]~', '\\\\$0', $current_file);
        $debug_info = "@media -sass-debug-info{filename{font-family:$current_file}line{font-family:\\00003$line}}";

        if (! $this->minifyOutput) {
            $debug_info .= $this->newline;
        }
        if ($this->generateMap) {
            $debug_info .= $token;
        }

        return $debug_info;
    }


    #############################
    #  Decruft.

    protected function decruft()
    {
        return $this->stream->pregReplaceHash(array(

            // Strip leading zeros on floats.
            '~([: \(,])(-?)0(\.\d+)~S' => '$1$2$3',

            // Strip unnecessary units on zero values for length types.
            '~([: \(,])\.?0' . Regex::$classes->length_unit . '~iS' => '${1}0',

            // Collapse zero lists.
            '~(\: *)(?:0 0 0|0 0 0 0) *([;}])~S' => '${1}0$2',

            // Collapse zero lists 2nd pass.
            '~(padding|margin|border-radius) ?(\: *)0 0 *([;}])~iS' => '${1}${2}0$3',

            // Dropping redundant trailing zeros on TRBL lists.
            '~(\: *)(-?(?:\d+)?\.?\d+[a-z]{1,4}) 0 0 0 *([;}])~iS' => '$1$2 0 0$3',
            '~(\: *)0 0 (-?(?:\d+)?\.?\d+[a-z]{1,4}) 0 *([;}])~iS' => '${1}0 0 $2$3',

            // Compress hex codes.
            Regex::$patt->cruftyHex => '#$1$2$3',
        ));
    }


    #############################
    #  Advanced minification.

    protected function minifyColors()
    {
        static $keywords_patt, $functions_patt;

        $minified_keywords = Color::getMinifyableKeywords();

        if (! $keywords_patt) {
            $keywords_patt = '~(?<![\w-\.#])(' . implode('|', array_keys($minified_keywords)) . ')(?![\w-\.#\]])~iS';
            $functions_patt = Regex::make('~{{ LB }}(rgb|hsl)\(([^\)]{5,})\)~iS');
        }

        $this->stream->pregReplaceCallback($keywords_patt, function ($m) use ($minified_keywords) {
            return $minified_keywords[strtolower($m[0])];
        });

        $this->stream->pregReplaceCallback($functions_patt, function ($m) {
            $args = Functions::parseArgs(trim($m[2]));
            if (stripos($m[1], 'hsl') === 0) {
                $args = Color::cssHslToRgb($args);
            }
            return Color::rgbToHex($args);
        });
    }
}
