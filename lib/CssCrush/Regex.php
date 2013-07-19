<?php
/**
 *
 * Regex management.
 *
 */
class CssCrush_Regex
{
    // Patterns.
    static public $patt;

    // Character classes.
    static public $classes;

    static public $classSwaps = array();

    static public function init ()
    {
        self::$patt = $patt = new stdClass();
        self::$classes = $classes = new stdClass();

        // CSS type classes.
        $classes->ident = '[a-zA-Z0-9_-]+';
        $classes->number = '[+-]?\d*\.?\d+';
        $classes->percentage = $classes->number . '%';
        $classes->length_unit = '(?i)(?:e[mx]|c[hm]|rem|v[hwm]|in|p[tcx])(?-i)';
        $classes->length = $classes->number . $classes->length_unit;
        $classes->color_hex = '#[[:xdigit:]]{3}(?:[[:xdigit:]]{3})?';

        // Tokens.
        $classes->token_id = '[0-9a-z]+';
        $classes->c_token = '\?c' . $classes->token_id . '\?'; // Comments.
        $classes->s_token = '\?s' . $classes->token_id . '\?'; // Strings.
        $classes->r_token = '\?r' . $classes->token_id . '\?'; // Rules.
        $classes->p_token = '\?p' . $classes->token_id . '\?'; // Parens.
        $classes->u_token = '\?u' . $classes->token_id . '\?'; // URLs.
        $classes->t_token = '\?t' . $classes->token_id . '\?'; // Traces.
        $classes->a_token = '\?a(' . $classes->token_id . ')\?'; // Args.

        // Boundries.
        $classes->LB = '(?<![\w-])'; // Left ident boundry.
        $classes->RB = '(?![\w-])'; // Right ident boundry.
        $classes->RTB = '(?=\?[a-z])'; // Right token boundry.

        // Recursive block matching.
        $classes->block = '(?<block>\{\s*(?<block_content>(?:(?>[^{}]+)|(?&block))*)\})';
        $classes->parens = '(?<parens>\(\s*(?<parens_content>(?:(?>[^()]+)|(?&parens))*)\))';

        // Misc.
        $classes->vendor = '-[a-zA-Z]+-';
        $classes->hex = '[[:xdigit:]]';
        $classes->newline = '(\r\n?|\n)';

        // Create standalone class patterns, add classes as class swaps.
        foreach ($classes as $name => $class) {
            self::$classSwaps['{{' . str_replace('_', '-', $name) . '}}'] = $class;
            $patt->{$name} = '~' . $class . '~S';
        }

        // Rooted classes.
        $patt->rooted_ident = '~^' . $classes->ident . '$~';
        $patt->rooted_number = '~^' . $classes->number . '$~';

        // @-rules.
        $patt->import = CssCrush_Regex::create('@import \s+ ({{u-token}}) \s? ([^;]*);', 'ixS');
        $patt->charset = CssCrush_Regex::create('@charset \s+ ({{s-token}}) \s*;', 'ixS');
        $patt->vars = CssCrush_Regex::create('@define \s* {{block}}', 'ixS');
        $patt->mixin = CssCrush_Regex::create('@mixin \s+ (?<name>{{ident}}) \s* {{block}}', 'ixS');
        $patt->ifDefine = CssCrush_Regex::create('@ifdefine \s+ (not \s+)? ({{ident}}) \s* \{', 'ixS');
        $patt->fragmentCapture = CssCrush_Regex::create('@fragment \s+ (?<name>{{ident}}) \s* {{block}}', 'ixS');
        $patt->fragmentInvoke = CssCrush_Regex::create('@fragment \s+ (?<name>{{ident}}) {{parens}}? \s* ;', 'ixS');
        $patt->abstract = CssCrush_Regex::create('^@abstract \s+ (?<name>{{ident}})', 'ixS');

        // Functions.
        $patt->function = CssCrush_Regex::create('{{LB}} ({{ident}}) ({{p-token}})', 'xS');
        $patt->varFunction = CssCrush_Regex::create('\$\( \s* ({{ident}}) \s* \)', 'xS');
        $patt->thisFunction = CssCrush_Regex::createFunctionPatt(array('this'));

        // Strings and comments.
        $patt->string = '~(\'|")(?:\\\\\1|[^\1])*?\1~xS';
        $patt->commentAndString = '~
            # Quoted string (to EOF if unmatched).
            (\'|")(?:\\\\\1|[^\1])*?(?:\1|$)
            |
            # Block comment (to EOF if unmatched).
            /\*(?:.*?)(?:\*/|$)
        ~xsS';

        // Rules.
        $patt->ruleFirstPass = CssCrush_Regex::create('
            (?:^|(?<=[;{}]))
            (?<before>
                (?: \s | {{c-token}} )*
            )
            (?<selector>
                (?:
                    # Some @-rules are treated like standard rule blocks.
                    @(?: (?i)page|abstract|font-face(?-i) ) {{RB}} [^{]*
                    |
                    [^@;{}]+
                )
            )
            {{block}}', 'xS');

        $patt->rule = CssCrush_Regex::create('
            (?<trace_token> {{t-token}} )
            \s*
            (?<selector> [^{]+ )
            \s*
            {{block}}', 'xiS');

        // Balanced bracket matching.
        $patt->balancedParens  = '~\(\s* ( (?: (?>[^()]+) | (?R) )* ) \s*\)~xS';
        $patt->balancedCurlies = '~\{\s* ( (?: (?>[^{}]+) | (?R) )* ) \s*\}~xS';

        // Misc.
        $patt->vendorPrefix = '~^-([a-z]+)-([a-z-]+)~iS';
        $patt->ruleDirective = '~^(?:(@include)|(@extends?)|(@name))[\s]+~iS';
        $patt->argListSplit = '~\s*[,\s]\s*~S';
        $patt->mathBlacklist = '~[^\.0-9\*\/\+\-\(\)]~S';
        $patt->cruftyHex = CssCrush_Regex::create('\#({{hex}})\1({{hex}})\2({{hex}})\3', 'S');
    }

    static public function create ($pattern_template, $flags = '', $delim = '~')
    {
        static $find, $replace;
        if (! $find) {
            $find = array_keys(self::$classSwaps);
            $replace = array_values(self::$classSwaps);
        }

        $pattern = str_replace($find, $replace, $pattern_template);

        return "$delim{$pattern}$delim{$flags}";
    }

    static public function matchAll ($patt, $subject, $preprocess_patt = false, $offset = 0)
    {
        if ($preprocess_patt) {
            // Assume case-insensitive.
            $patt = self::create($patt, 'i');
        }

        $count = preg_match_all($patt, $subject, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $offset);

        return $count ? $matches : array();
    }

    static public function createFunctionPatt ($list, $options = array())
    {
        // Bare parens.
        $question = '';
        if (! empty($options['bare_paren'])) {
            $question = '?';
            // Signing on math bare parens.
            $list[] = '-';
        }

        // Escape function names.
        foreach ($list as &$fn_name) {
            $fn_name = preg_quote($fn_name);
        }

        // Templating func.
        $template = '';
        if (! empty($options['templating'])) {
            $template = '#|';
        }

        $flat_list = implode('|', $list);

        return CssCrush_Regex::create("($template{{LB}}(?:$flat_list)$question)\(", 'iS');
    }
}

CssCrush_Regex::init();
