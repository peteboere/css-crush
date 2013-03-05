<?php
/**
 *
 *  The main class for compiling.
 *
 */
class CssCrush_Process
{
    public function __construct ( $options )
    {
        $config = CssCrush::$config;

        // Load in aliases and plugins.
        CssCrush::loadAssets();

        // Create options instance for this process.
        $this->options = new CssCrush_Options( $options );

        // Populate option defaults.
        $this->options->merge( $config->options );

        // Keep track of global vars to maintain cache integrity.
        $this->options->global_vars = $config->vars;

        // Initialize properties.
        $this->uid = 0;
        $this->cacheData = array();
        $this->mixins = array();
        $this->references = array();
        $this->errors = array();
        $this->stat = array();
        $this->charset = null;
        $this->currentFile = null;
        $this->tokens = (object) array(
            's' => array(), // Strings
            'c' => array(), // Comments
            'r' => array(), // Rules
            'p' => array(), // Parens
            'u' => array(), // URLs
            't' => array(), // Traces
        );
        $this->variables = array();
        $this->misc = new stdclass();
        $this->input = new stdclass();
        $this->output = new stdclass();

        // Copy config values.
        $this->plugins = $config->plugins;
        $this->aliases = $config->aliases;
        $this->selectorAliases = array();
        $this->selectorAliasesPatt = null;

        // Shortcut commonly used options to avoid overhead with __get calls.
        $this->minifyOutput = $this->options->minify;
        $this->addTracingStubs = in_array( 'stubs', $this->options->trace );
        $this->ruleFormatter = $this->options->formatter;

        // Pick a doc root.
        $this->docRoot = isset( $this->options->doc_root ) ?
            $this->options->doc_root : $config->docRoot;

        // Shortcut the newline option and attach it to the process.
        switch ( $this->options->newlines ) {
            case 'windows':
            case 'win':
                $this->newline = "\r\n";
                break;
            case 'unix':
                $this->newline = "\n";
                break;
            case 'use-platform':
            default:
                // Fall through and use default (platform) newline.
                $this->newline = PHP_EOL;
                break;
        }

        // Run process_init hook.
        CssCrush_Hook::run( 'process_init' );
    }

    public function release ()
    {
        unset(
            $this->tokens,
            $this->variables,
            $this->mixins,
            $this->references,
            $this->misc,
            $this->plugins,
            $this->aliases,
            $this->selectorAliases
        );
    }

    // Establish the input and output directories and optionally test output dir.
    public function setContext ( $input_dir, $test_output_dir = true )
    {
        $doc_root = $this->docRoot;

        if ( strpos( $input_dir, $doc_root ) !== 0 ) {
            // Not a system path.
            $input_dir = realpath( "$doc_root/$input_dir" );
        }

        // Initialise input object and store input directory.
        $this->input->path = null;
        $this->input->filename = null;
        $this->input->dir = $input_dir;
        $this->input->dirUrl = substr( $this->input->dir, strlen( $doc_root ) );

        // Store reference to the output dir.
        $this->output->dir = $this->ioCall( 'getOutputDir' );
        $this->output->dirUrl = substr( $this->output->dir, strlen( $doc_root ) );

        // Test the output directory to see it exists and is writable.
        $output_dir_ok = false;
        if ( $test_output_dir ) {
            $output_dir_ok = $this->ioCall( 'testOutputDir' );
        }

        // Setup the IO handler.
        $this->ioCall( 'init' );

        return $output_dir_ok;
    }

    public function ioCall ( $method )
    {
        // Fetch the argument list, shift off the first item
        $args = func_get_args();
        array_shift( $args );

        // The method address
        $the_method = array( CssCrush::$config->io, $method );

        // Return the call result
        return call_user_func_array( $the_method, $args );
    }


    #############################
    #  Tokens.

    public function createTokenLabel ( $type )
    {
        $counter = ++$this->uid;
        return "?$type$counter?";
    }

    public function addToken ($value, $type)
    {
        $label = $this->createTokenLabel($type);
        $this->tokens->{$type}[$label] = $value;
        return $label;
    }

    public function fetchToken ( $token )
    {
        $path =& $this->tokens->{ $token[1] };
        if ( isset( $path[ $token ] ) ) {
            return $path[ $token ];
        }
        return null;
    }

    public function popToken ( $token )
    {
        $val = $this->fetchToken( $token );
        $this->releaseToken( $token );
        return $val;
    }

    public function releaseToken ( $token )
    {
        unset( $this->tokens->{ $token[1] }[ $token ] );
    }

    public function restoreTokens ( $str, $type = 'p' )
    {
        // Reference the token table.
        $token_table =& $this->tokens->{$type};

        // Find matching tokens.
        $matches = CssCrush_Regex::matchAll(CssCrush_Regex::$patt->{"{$type}_token"}, $str);

        foreach ( $matches as $m ) {
            $token = $m[0][0];
            if ( isset( $token_table[ $token ] ) ) {
                $str = str_replace( $token, $token_table[ $token ], $str );
            }
        }
        return $str;
    }


    #############################
    #  Parens.

    public function captureParens ( &$str )
    {
        static $callback;
        if ( ! $callback ) {
            $callback = create_function( '$m', 'return CssCrush::$process->addToken( $m[0], \'p\' );' );
        }
        $str = preg_replace_callback( CssCrush_Regex::$patt->balancedParens, $callback, $str );
    }

    public function restoreParens ( &$str, $release = true )
    {
        $token_table =& $this->tokens->p;

        foreach ( CssCrush_Regex::matchAll( CssCrush_Regex::$patt->p_token, $str ) as $m ) {
            $token = $m[0][0];
            if ( isset( $token_table[ $token ] ) ) {
                $str = str_replace( $token, $token_table[ $token ], $str );
                if ( $release ) {
                    unset( $token_table[ $token ] );
                }
            }
        }
    }


    #############################
    #  Boilerplate.

    protected function getBoilerplate ()
    {
        $file = false;
        $boilerplate_option = $this->options->boilerplate;

        if ( $boilerplate_option === true ) {
            $file = CssCrush_Util::find(
                'CssCrush-local.boilerplate', 'CssCrush.boilerplate' );
        }
        elseif ( is_string( $boilerplate_option ) ) {
            if ( file_exists( $boilerplate_option ) ) {
                $file = $boilerplate_option;
            }
        }

        // Return an empty string if no file is found.
        if ( ! $file ) {
            return '';
        }

        // Load the file
        $boilerplate = file_get_contents( $file );

        // Substitute any tags
        if ( preg_match_all( '~\{\{([^}]+)\}\}~', $boilerplate, $boilerplate_matches ) ) {

            $replacements = array();
            foreach ( $boilerplate_matches[0] as $index => $tag ) {
                $tag_name = $boilerplate_matches[1][$index];
                if ( $tag_name === 'datetime' ) {
                    $replacements[] = @date( 'Y-m-d H:i:s O' );
                }
                elseif ( $tag_name === 'version' ) {
                    $replacements[] = 'v' . CssCrush::$config->version;
                }
                else {
                    $replacements[] = '?';
                }
            }
            $boilerplate = str_replace( $boilerplate_matches[0], $replacements, $boilerplate );
        }

        // Pretty print.
        $EOL = $this->newline;
        $boilerplate = preg_split( '~[\t ]*(\r\n?|\n)[\t ]*~', $boilerplate );
        $boilerplate = array_map( 'trim', $boilerplate );
        $boilerplate = "$EOL * " . implode( "$EOL * ", $boilerplate );
        return "/*{$boilerplate}$EOL */$EOL";
    }


    #############################
    #  Selector aliases.

    protected function resolveSelectorAliases ()
    {
        static $callback;
        if ( ! $callback ) {
            $callback = create_function( '$m', 'CssCrush::$process->selectorAliases[ $m[1] ] = $m[2];' );
        }
        $this->stream->pregReplaceCallback( CssCrush_Regex::$patt->selectorAlias, $callback );

        // Merge in global selector aliases.
        $this->selectorAliases += CssCrush::$config->selectorAliases;

        // Create the selector aliases pattern and store it.
        if ( $this->selectorAliases ) {
            $names = implode( '|', array_keys( $this->selectorAliases ) );
            $this->selectorAliasesPatt = '~
              \:(' . $names . ')\b(?!-)
              (\()?
            ~xiS';
        }
    }

    static public function applySelectorAliases ( &$str )
    {
        $process = CssCrush::$process;

        // Early bail conditions.
        if (! $process->selectorAliases || ! preg_match($process->selectorAliasesPatt, $str)) {
            return;
        }

        // Find all selector-alias matches.
        $matches = CssCrush_Regex::matchAll($process->selectorAliasesPatt, $str);

        $table =& $process->selectorAliases;

        // Step through the matches from last to first.
        while ($match = array_pop($matches)) {

            $selector_alias_name = $match[1][0];

            if (! isset($table[$selector_alias_name])) {
                continue;
            }

            $replacement = $table[$selector_alias_name];
            $start = $match[0][1];
            $length = strlen($match[0][0]);

            // It's a function alias if a start paren is matched.
            if (isset($match[2])) {

                if (! preg_match(CssCrush_Regex::$patt->balancedParens, $str,
                    $parens, PREG_OFFSET_CAPTURE, $start)) {
                    continue;
                }
                $paren_start = $parens[0][1];
                $paren_len = strlen($parens[0][0]);
                $length = ($paren_start + $paren_len) - $start;

                // Create substitutions list and apply.
                $args = CssCrush_Util::splitDelimList($parens[1][0]);
                foreach ($args as $index => $arg) {
                    $search[] = "#($index)";
                }
                $replacement = str_replace($search, $args, $replacement);

                // Apply substitutions to copies of string tokens within the replacement.
                preg_match_all(CssCrush_Regex::$patt->s_token, $replacement, $_matches);
                foreach ($_matches as $m) {
                    $label = $m[0];
                    if (isset($process->tokens->s[$label])) {

                        // Create new token based on the value.
                        $token_value = str_replace($search, $args, $process->tokens->s[$label]);
                        $new_label = $process->addToken($token_value, 's');

                        // Swap the old token label with new.
                        $replacement = str_replace($label, $new_label, $replacement);
                    }
                }
            }

            // Splice in the result.
            $str = substr_replace($str, $replacement, $start, $length);
        }
    }


    #############################
    #  Aliases.

    protected function filterAliases ()
    {
        // If a vendor target is given, we prune the aliases array.
        $vendor = $this->options->vendor_target;

        // Default vendor argument, so use all aliases as normal.
        if ( 'all' === $vendor ) {
            return;
        }

        // For expicit 'none' argument turn off aliases.
        if ( 'none' === $vendor ) {
            $this->aliases = CssCrush::$config->bareAliasGroups;
            return;
        }

        // Normalize vendor_target argument.
        $vendor = '-' . str_replace( '-', '', $vendor ) . '-';

        // Loop the aliases array, filter down to the target vendor.
        foreach ( $this->aliases as $section => $group_array ) {

            // Declarations aliases.
            if ( $section === 'declarations' ) {

                foreach ( $group_array as $property => $values ) {
                    foreach ( $values as $value => $prefix_values ) {
                        foreach ( $prefix_values as $index => $declaration ) {

                            list( $prop, $val ) = $declaration;
                            if ( !(
                                strpos( $prop, $vendor ) === 0 ||
                                strpos( $val, $vendor ) === 0
                                )
                            ) {
                                // Unset uneeded aliases.
                                unset( $this->aliases[$section][$property][$value][$index] );

                                if ( empty( $this->aliases[$section][$property][$value] ) ) {
                                    unset( $this->aliases[$section][$property][$value] );
                                }
                                if ( empty( $this->aliases[$section][$property] ) ) {
                                    unset( $this->aliases[$section][$property] );
                                }
                            }
                        }
                    }
                }
            }

            // Function group aliases.
            elseif ( $section === 'function_groups' ) {

                foreach ( $group_array as $func_group => $vendors ) {
                    foreach ( $vendors as $fn_vendor => $replacements ) {

                        if ( "-$fn_vendor-" !== $vendor ) {
                            unset( $this->aliases[ 'function_groups' ][ $func_group ][ $fn_vendor ] );
                        }
                    }
                }
            }

            // Everything else.
            else {
                foreach ( $group_array as $alias_keyword => $prefix_array ) {

                    // Skip over pointers to function groups.
                    if ( $prefix_array[0] === ':' ) {
                        continue;
                    }

                    $result = array();

                    foreach ( $prefix_array as $prefix ) {
                        if ( strpos( $prefix, $vendor ) === 0 ) {
                            $result[] = $prefix;
                        }
                    }
                    // Prune the whole alias keyword if there is no result.
                    if ( empty( $result ) ) {
                        unset( $this->aliases[ $section ][ $alias_keyword ] );
                    }
                    else {
                        $this->aliases[ $section ][ $alias_keyword ] = $result;
                    }
                }
            }
        }
    }


    #############################
    #  Plugins.

    protected function filterPlugins ()
    {
        $options = $this->options;
        $config = CssCrush::$config;

        // Checking for table keys is more convenient than array searching.
        $disable = array_flip( $options->disable );
        $enable = array_flip( $options->enable );

        // Disable has the special 'all' option.
        if ( isset( $disable[ 'all' ] ) ) {
            $disable = $config->plugins;
        }

        // Remove option disabled plugins from the list, and disable them.
        if ( $disable ) {
            foreach ( $disable as $plugin_name => $index ) {
                CssCrush_Plugin::disable( $plugin_name );
                unset( $this->plugins[ $plugin_name ] );
            }
        }

        // Secondly add option enabled plugins to the list.
        if ( $enable ) {
            foreach ( $enable as $plugin_name => $index ) {
                $this->plugins[ $plugin_name ] = true;
            }
        }

        // Enable all plugins in the remaining list.
        foreach ( $this->plugins as $plugin_name => $bool ) {
            CssCrush_Plugin::enable( $plugin_name );
        }
    }


    #############################
    #  Variables.

    protected function calculateVariables ()
    {
        $config = CssCrush::$config;
        $regex = CssCrush_Regex::$patt;
        $option_vars = $this->options->vars;

        $this->stream->pregReplaceCallback( $regex->variables,
            array( 'CssCrush_Process', 'cb_extractVariables' ) );

        // In-file variables override global variables.
        $this->variables = array_merge( $config->vars, $this->variables );

        // Runtime variables override in-file variables.
        if ( ! empty( $option_vars ) ) {
            $this->variables = array_merge( $this->variables, $option_vars );
        }

        // Place variables referenced inside variables. Excecute custom functions.
        foreach ( $this->variables as $name => &$value ) {

            // Referenced variables.
            $value = preg_replace_callback( $regex->varFunction, array( 'self', 'cb_placeVariables' ), $value );

            // Variable values can be escaped from function parsing with a tilde prefix.
            if ( strpos( $value, '~' ) !== 0 ) {
                CssCrush_Function::executeOnString( $value );
            }
        }
    }

    protected function placeAllVariables ()
    {
        // Place variables in main stream.
        self::placeVariables( $this->stream->raw );

        // Repeat above steps for variables embedded in string tokens.
        foreach ( $this->tokens->s as $label => &$value ) {
            self::placeVariables( $value );
        }

        // Repeat above steps for variables embedded in URL tokens.
        foreach ( $this->tokens->u as $label => $url ) {
            if ( self::placeVariables( $url->value ) ) {
                // Re-evaluate $url->value if anything has been interpolated.
                $url->evaluate();
            }
        }
    }

    static protected function placeVariables ( &$value )
    {
        $regex = CssCrush_Regex::$patt;

        // Variables with no default value.
        $value = preg_replace_callback( $regex->varFunction,
            array( 'CssCrush_Process', 'cb_placeVariables' ), $value, -1, $count );

        if ( strpos( $value, '$(' ) !== false ) {

            // Variables with default value.
            CssCrush_Function::executeOnString( $value, '~(\$)\(~',
                array( '$' => array( 'CssCrush_Process', 'cb_placeVariablesWithDefault' ) ) );

            // Assume at least 1 replace.
            $count = 1;
        }

        // If we know replacements have been made we may want to update $value. e.g URL tokens.
        return $count;
    }

    static public function cb_extractVariables ( $m )
    {
        $regex = CssCrush_Regex::$patt;

        // Strip comment markers.
        $block = trim(CssCrush_Util::stripCommentTokens($m[1]));

        CssCrush::$process->variables =
            array_merge( CssCrush::$process->variables, CssCrush_Util::parseBlock( $block, true ) );
    }

    static protected function cb_placeVariables ( $m )
    {
        $variable_name = $m[1];
        if ( isset( CssCrush::$process->variables[ $variable_name ] ) ) {
            return CssCrush::$process->variables[ $variable_name ];
        }
    }

    static public function cb_placeVariablesWithDefault ( $raw_arg )
    {
        list( $name, $default_value ) = CssCrush_Function::parseArgsSimple( $raw_arg );

        if ( isset( CssCrush::$process->variables[ $name ] ) ) {
            return CssCrush::$process->variables[ $name ];
        }
        else {
            return $default_value;
        }
    }


    #############################
    #  @ifdefine blocks.

    protected function resolveIfDefines ()
    {
        $matches = $this->stream->matchAll( CssCrush_Regex::$patt->ifDefine );

        // Move through the matches last to first.
        while ( $match = array_pop( $matches ) ) {

            $curly_match = new CssCrush_BalancedMatch( $this->stream, $match[0][1] );

            if ( ! $curly_match->match ) {
                // Couldn't match the block.
                continue;
            }

            $negate = $match[1][1] != -1;
            $name = $match[2][0];
            $name_defined = isset( $this->variables[ $name ] );

            if ( ! $negate && $name_defined || $negate && ! $name_defined ) {
                // Test resolved true so include the innards.
                $curly_match->unWrap();
            }
            else {
                // Recontruct the stream without the innards.
                $curly_match->replace( '' );
            }
        }
    }


    #############################
    #  Mixins.

    protected function extractMixins ()
    {
        static $callback;
        if ( ! $callback ) {
            $callback = create_function( '$m', '
                $name = trim( $m[1] );
                $block = trim( $m[2] );
                if ( ! empty( $name ) && ! empty( $block ) ) {
                    CssCrush::$process->mixins[ $name ] = new CssCrush_Mixin( $block );
                }
            ' );
        }

        $this->stream->pregReplaceCallback( CssCrush_Regex::$patt->mixin, $callback );
    }


    #############################
    #  Fragments.

    protected function resolveFragments ()
    {
        $regex = CssCrush_Regex::$patt;
        $matches = $this->stream->matchAll( $regex->fragmentDef );
        $fragments = array();

        // Move through the matches last to first.
        while ( $match = array_pop( $matches ) ) {

            $match_start_pos = $match[0][1];
            $fragment_name = $match[1][0];

            $curly_match = new CssCrush_BalancedMatch( $this->stream, $match_start_pos );

            if ( ! $curly_match->match ) {
                // Couldn't match the block.
                continue;
            }
            else {
                // Reconstruct the stream without the fragment.
                $curly_match->replace( '' );

                // Create the fragment and store it.
                $fragments[ $fragment_name ] = new CssCrush_Fragment( $curly_match->inside() );
            }
        }

        // Now find all the fragment calls.
        $matches = $this->stream->matchAll( $regex->fragmentCall );

        // Move through the matches last to first.
        while ( $match = array_pop( $matches ) ) {

            list( $match_string, $match_start_pos ) = $match[0];

            // The matched fragment name.
            $fragment_name = $match[1][0];

            // The fragment object, or null if name not present.
            $fragment = isset( $fragments[ $fragment_name ] ) ? $fragments[ $fragment_name ] : null;

            // Fragment may be called without any argument list.
            $with_arguments = $match[2][0] === '(';

            if ( $with_arguments ) {
                $paren_match = new CssCrush_BalancedMatch( $this->stream, $match_start_pos, '()' );
                // Get offset of statement terminating semi-colon.
                $match_end = $paren_match->nextIndexOf( ';' ) + 1;
                $match_length = $match_end - $match_start_pos;
            }
            else {
                $match_length = strlen( $match_string );
            }

            if ( ! $fragment || ( $with_arguments && ! $paren_match->match ) ) {

                // Invalid fragment or malformed argument list.
                $this->stream->splice( '', $match_start_pos, $match_length );
                continue;
            }
            else {

                $args = array();
                if ( $with_arguments ) {
                    // Get the argument array to pass to the fragment.
                    $args = CssCrush_Util::splitDelimList( $paren_match->inside() );
                }

                // Execute the fragment and get the return value.
                $fragment_return = $fragment->call( $args );

                // Recontruct the stream with the fragment return value.
                $this->stream->splice( $fragment_return, $match_start_pos, $match_length );
            }
        }
    }


    #############################
    #  Rules.

    public function extractRules ()
    {
        $this->stream->pregReplaceCallback( CssCrush_Regex::$patt->rule, array( 'CssCrush_Process', 'cb_extractRules' ) );
    }

    protected function processRules ()
    {
        $aliases =& $this->aliases;

        foreach ( $this->tokens->r as $rule ) {

            $rule->processDeclarations();

            CssCrush_Hook::run( 'rule_prealias', $rule );

            if ( $aliases[ 'properties' ] ) {
                $rule->addPropertyAliases();
            }
            if ( $aliases[ 'functions' ] ) {
                $rule->addFunctionAliases();
            }
            if ( $aliases[ 'declarations' ] ) {
                $rule->addDeclarationAliases();
            }

            CssCrush_Hook::run( 'rule_postalias', $rule );

            $rule->expandSelectors();

            // Find previous selectors and apply them.
            $rule->applyExtendables();

            CssCrush_Hook::run( 'rule_postprocess', $rule );
        }
    }

    static public function cb_extractRules ( $m )
    {
        $rule = (object) array();
        $rule->selector_raw = trim( $m[1] );
        $rule->declaration_raw = trim( $m[2] );

        // Run rule_preprocess hook.
        CssCrush_Hook::run( 'rule_preprocess', $rule );

        $rule = new CssCrush_Rule( $rule->selector_raw, $rule->declaration_raw );

        // Store rules if they have declarations or extend arguments.
        if ( ! empty( $rule->declarations ) || $rule->extendArgs ) {

            CssCrush::$process->tokens->r[ $rule->label ] = $rule;

            // If only using extend still return a label.
            return $rule->label;
        }
    }


    #############################
    #  @in blocks.

    protected function prefixSelectors ()
    {
        $matches = $this->stream->matchAll( '~@in\s+([^{]+)\{~iS' );

        // Move through the matches in reverse order.
        while ( $match = array_pop( $matches ) ) {

            $match_start_pos = $match[0][1];
            $raw_argument = trim( $match[1][0] );

            CssCrush_Process::applySelectorAliases( $raw_argument );

            $this->captureParens( $raw_argument );
            $arguments = CssCrush_Util::splitDelimList( $raw_argument );

            $curly_match = new CssCrush_BalancedMatch( $this->stream, $match_start_pos );

            if ( ! $curly_match->match || empty( $raw_argument ) ) {
                // Couldn't match the block.
                continue;
            }

            // Match all the rule tokens.
            $rule_matches = CssCrush_Regex::matchAll(
                CssCrush_Regex::$patt->r_token, $curly_match->inside() );

            foreach ( $rule_matches as $rule_match ) {

                // Get the rule instance.
                $rule = CssCrush_Rule::get( $rule_match[0][0] );

                // Using arguments create new selector list for the rule.
                $new_selector_list = array();

                foreach ( $arguments as $arg_selector ) {

                    foreach ( $rule->selectors as $rule_selector ) {

                        $use_parent_symbol = strpos( $rule_selector->value, '&' ) !== false;

                        // Skipping the prefix.
                        if ( ! $rule_selector->allowPrefix && ! $use_parent_symbol ) {

                            $new_selector_list[ $rule_selector->readableValue ] = $rule_selector;
                        }

                        // Positioning the prefix with parent symbol "&".
                        elseif ( $use_parent_symbol ) {

                            // Find and replace the ampersand (only once).
                            $new_value = preg_replace(
                                    '~&~',
                                    $arg_selector,
                                    $rule_selector->value,
                                    1 );

                            // Not storing the selector as named.
                            $new_selector_list[] = new CssCrush_Selector( $new_value );
                        }

                        // Prepending the prefix.
                        else {

                            // Not storing the selector as named.
                            $new_selector_list[]
                                = new CssCrush_Selector( "$arg_selector {$rule_selector->value}" );
                        }
                    }
                }
                $rule->selectors = $new_selector_list;
            }

            $curly_match->unWrap();
        }
    }


    #############################
    #  @-rule aliasing.

    protected function aliasAtRules ()
    {
        if ( empty( $this->aliases[ 'at-rules' ] ) ) {
            return;
        }

        $aliases = $this->aliases[ 'at-rules' ];
        $regex = CssCrush_Regex::$patt;

        foreach ( $aliases as $at_rule => $at_rule_aliases ) {

            $matches = $this->stream->matchAll( "~@$at_rule" . '[\s{]~i' );

            // Find at-rules that we want to alias.
            while ( $match = array_pop( $matches ) ) {

                $curly_match = new CssCrush_BalancedMatch( $this->stream, $match[0][1] );

                if ( ! $curly_match->match ) {
                    // Couldn't match the block.
                    continue;
                }

                // Build up string with aliased blocks for splicing.
                $original_block = $curly_match->whole();
                $new_blocks = array();

                foreach ( $at_rule_aliases as $alias ) {

                    // Copy original block, replacing at-rule with alias name.
                    $copy_block = str_replace( "@$at_rule", "@$alias", $original_block );

                    // Aliases are nearly always prefixed, capture the current vendor name.
                    preg_match( $regex->vendorPrefix, $alias, $vendor );

                    $vendor = $vendor ? $vendor[1] : null;

                    // Duplicate rules.
                    if ( preg_match_all( $regex->r_token, $copy_block, $copy_matches ) ) {

                        $originals = array();
                        $replacements = array();

                        foreach ( $copy_matches[0] as $copy_match ) {

                            // Clone the matched rule.
                            $originals[] = $rule_label = $copy_match;
                            $cloneRule = clone $this->tokens->r[ $rule_label ];

                            // Set the vendor context.
                            $cloneRule->vendorContext = $vendor;

                            // Filter out declarations that have different vendor context.
                            $new_set = array();
                            foreach ( $cloneRule as $declaration ) {
                                if ( ! $declaration->vendor || $declaration->vendor === $vendor ) {
                                    $new_set[] = $declaration;
                                }
                            }
                            $cloneRule->setDeclarations( $new_set );

                            // Store the clone.
                            $replacements[] = $this->addToken( $cloneRule, 'r' );

                        }
                        // Finally replace the original labels with the cloned rule labels.
                        $copy_block = str_replace( $originals, $replacements, $copy_block );
                    }

                    // Add the copied block to the stack.
                    $new_blocks[] = $copy_block;
                }

                // The original version is always pushed last in the list.
                $new_blocks[] = $original_block;

                // Splice in the blocks.
                $curly_match->replace( implode( "\n", $new_blocks ) );
            }
        }
    }


    #############################
    #  Compile / collate.

    protected function collate ()
    {
        $options = $this->options;
        $minify = $options->minify;
        $regex = CssCrush_Regex::$patt;
        $regex_replacements = array();
        $EOL = $this->newline;

        // Strip newlines added during processing.
        $regex_replacements[ '~\n+~' ] = '';

        if ( $minify ) {
            // Strip whitespace around colons used in @-rule arguments.
            $regex_replacements[ '~ ?\: ?~' ] = ':';
        }
        else {
            // Pretty printing.
            $regex_replacements[ '~}~' ] = "$0$EOL$EOL";
            $regex_replacements[ '~([^\s])\{~' ] = "$1 {";
            $regex_replacements[ '~ ?(@[^{]+\{)~' ] = "$1$EOL";
            $regex_replacements[ '~ ?(@[^;]+\;)~' ] = "$1$EOL";
        }

        // Apply all replacements.
        $this->stream->pregReplaceHash( $regex_replacements )->lTrim();

        // Print out rules.
        $this->stream->replaceHash( $this->tokens->r );
        CssCrush::runStat( 'selector_count' );
        CssCrush::runStat( 'rule_count' );

        // Insert parens.
        $this->stream->replaceHash( $this->tokens->p );

        // Advanced minification parameters.
        if ( is_array( $minify ) ) {
            if ( in_array( 'colors', $minify ) ) {
                $this->minifyColors();
            }
        }

        // Compress hex-codes, collapse TRBL lists etc.
        $this->decruft();

        if ( $minify ) {
            // Trim whitespace around selector combinators.
            $this->stream->pregReplace( '~ ?([>\~+]) ?~S', '$1' );
        }
        else {

            // Add newlines after comments.
            foreach ( $this->tokens->c as $token => &$comment ) {
                $comment .= "$EOL$EOL";
            }

            // Insert comments and do final whitespace cleanup.
            $this->stream
                ->replaceHash( $this->tokens->c )
                ->trim()
                ->append( $EOL );
        }

        // Insert URLs.
        if ( $this->tokens->u ) {

            $link = CssCrush_Util::getLinkBetweenDirs( $this->output->dir, $this->input->dir );
            $make_urls_absolute = $options->rewrite_import_urls === 'absolute';

            foreach ( $this->tokens->u as $token => $url ) {

                if ( $url->isRelative ) {
                    // Optionally set the URLs to absolute.
                    if ( $make_urls_absolute ) {
                        $url->prepend( $this->input->dirUrl . '/' );
                    }
                    // If output dir is different to input dir prepend a link between the two.
                    elseif ( $link ) {
                        $url->prepend( $link );
                    }
                }

                if ( $url->convertToData ) {
                    $url->evaluate()->toData();
                }
                else {
                    $url->simplify();
                }
            }
            $this->stream->replaceHash( $this->tokens->u );
        }

        // Insert string literals.
        $this->stream->replaceHash( $this->tokens->s );

        // Add in boilerplate.
        if ( $options->boilerplate ) {
            $this->stream->prepend( $this->getBoilerplate() );
        }

        // Add @charset at top if set.
        if ( $this->charset ) {
            $this->stream->prepend( "@charset \"$this->charset\";$EOL" );
        }
    }

    public function compile ()
    {
        // Always store start time.
        $this->stat[ 'compile_start_time' ] = microtime( true );

        // Resolve active aliases and plugins.
        $this->filterPlugins();
        $this->filterAliases();

        // Create function matching regex.
        CssCrush_Function::setMatchPatt();

        // Collate hostfile and imports.
        $this->stream = new CssCrush_Stream( CssCrush_Importer::hostfile( $this->input ) );

        // Extract and calculate variables.
        $this->calculateVariables();

        // Place variables.
        $this->placeAllVariables();

        // Resolve @ifdefine blocks.
        $this->resolveIfDefines();

        // Get selector aliases.
        $this->resolveSelectorAliases();

        // Pull out @mixin definitions.
        $this->extractMixins();

        // Pull out @fragment blocks, and invoke.
        $this->resolveFragments();

        // Run extract phase hooks.
        CssCrush_Hook::run( 'process_extract', $this );

        // Adjust meta characters so we can extract the rules cleanly.
        $this->stream->replaceHash( array(
            '@' => "\n@",
            '}' => "}\n",
            '{' => "{\n",
            ';' => ";\n",
        ))->prepend( "\n" );

        // Parse rules.
        $this->extractRules();
        // csscrush::log(array_keys( $this->references) );

        // Process @in blocks.
        $this->prefixSelectors();

        // Main processing on the rule objects.
        $this->processRules();

        // Alias any @-rules.
        $this->aliasAtRules();

        // Print rules, optionally minify.
        $this->collate();

        // Release memory.
        $this->release();

        CssCrush::runStat( 'compile_time' );

        return $this->stream;
    }


    #############################
    #  Decruft.

    protected function decruft ()
    {
        $patt =& CssCrush_Regex::$patt;
        $classes =& CssCrush_Regex::$classes;

        return $this->stream->pregReplaceHash( array(

            // Strip leading zeros on floats.
            '~([: \(,])(-?)0(\.\d+)~S' => '$1$2$3',

            // Strip unnecessary units on zero values for length types.
            '~([: \(,])\.?0' . $classes->length_unit . '~iS' => '${1}0',

            // Collapse zero lists.
            '~(\: *)(?:0 0 0|0 0 0 0) *([;}])~S' => '${1}0$2',

            // Collapse zero lists 2nd pass.
            '~(padding|margin|border-radius) ?(\: *)0 0 *([;}])~iS' => '${1}${2}0$3',

            // Dropping redundant trailing zeros on TRBL lists.
            '~(\: *)(-?(?:\d+)?\.?\d+[a-z]{1,4}) 0 0 0 *([;}])~iS' => '$1$2 0 0$3',
            '~(\: *)0 0 (-?(?:\d+)?\.?\d+[a-z]{1,4}) 0 *([;}])~iS' => '${1}0 0 $2$3',

            // Compress hex codes.
            $patt->cruftyHex => '#$1$2$3',
        ));
    }


    #############################
    #  Advanced minification.

    protected function minifyColors ()
    {
        static $keywords_patt, $keywords_callback, $functions_patt, $functions_callback;

        if (! $keywords_patt) {

            $keywords =& CssCrush_Color::loadMinifyableKeywords();

            $keywords_patt = '~(?<![\w-\.#])(' . implode('|', array_keys($keywords)) . ')(?![\w-\.#\]])~iS';
            $keywords_callback = create_function('$m',
                'return CssCrush_Color::$minifyableKeywords[ strtolower( $m[0] ) ];');

            $functions_patt = CssCrush_Regex::create('<LB>(rgb|hsl)\(([^\)]{5,})\)', 'iS');
            $functions_callback = create_function('$m', '
                $args = CssCrush_Function::parseArgs( trim( $m[2] ) );
                if ( stripos( $m[1], \'hsl\' ) === 0 ) {
                    $args = CssCrush_Color::cssHslToRgb( $args );
                }
                return CssCrush_Color::rgbToHex( $args );
            ');
        }

        $this->stream->pregReplaceCallback($keywords_patt, $keywords_callback);
        $this->stream->pregReplaceCallback($functions_patt, $functions_callback);
    }
}
