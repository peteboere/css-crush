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
        self::$patt = $patt = new stdclass();
        self::$classes = $classes = new stdclass();

        // CSS type classes.
        $classes->ident = '[a-zA-Z0-9_-]+';
        $classes->number = '[+-]?\d*\.?\d+';
        $classes->percentage = $classes->number . '%';
        $classes->length_unit = '(?i)(?:e[mx]|c[hm]|rem|v[hwm]|in|p[tcx])(?-i)';
        $classes->length = $classes->number . $classes->length_unit;
        $classes->color_hex = '#[[:xdigit:]]{3}(?:[[:xdigit:]]{3})?';

        // Tokens.
        $classes->c_token = '\?c\d+\?'; // Comments.
        $classes->s_token = '\?s\d+\?'; // Strings.
        $classes->r_token = '\?r\d+\?'; // Rules.
        $classes->p_token = '\?p\d+\?'; // Parens.
        $classes->u_token = '\?u\d+\?'; // URLs.
        $classes->t_token = '\?t\d+\?'; // Traces.
        $classes->a_token = '\?a(\d+)\?'; // Args.

        // Boundries.
        $classes->LB = '(?<![\w-])'; // Left ident boundry.
        $classes->RB = '(?![\w-])'; // Right ident boundry.
        $classes->RTB = '(?=\?[a-z])'; // Right token boundry.

        // Misc.
        $classes->vendor = '-[a-zA-Z]+-';
        $classes->newline = '(?:\r\n?|\n)';

        // Create standalone class patterns, add classes as class swaps.
        foreach ($classes as $name => $class) {
            self::$classSwaps['<' . str_replace('_', '-', $name) . '>'] = $class;
            $patt->{$name} = '~' . $class . '~';
        }

        // Rooted classes.
        $patt->rooted_ident = '~^' . $classes->ident . '$~';
        $patt->rooted_number = '~^' . $classes->number . '$~';

        // @-rules.
        $patt->import = CssCrush_Regex::create('@import\s+(<u-token>)\s?([^;]*);', 'iS');
        $patt->charset = CssCrush_Regex::create('@charset\s+(<s-token>)\s*;', 'iS');
        $patt->variables = CssCrush_Regex::create('@(?:define|variables) *\{ *(.*?) *\};?', 'iS');
        $patt->mixin = CssCrush_Regex::create('@mixin +(<ident>) *\{ *(.*?) *\};?', 'iS');
        $patt->abstract = CssCrush_Regex::create('^@abstract +(<ident>)', 'i');
        $patt->ifDefine = CssCrush_Regex::create('@ifdefine +(not +)?(<ident>) *\{', 'iS');
        $patt->fragmentDef = CssCrush_Regex::create('@fragment +(<ident>) *\{', 'iS');
        $patt->fragmentCall = CssCrush_Regex::create('@fragment +(<ident>) *(\(|;)', 'iS');

        // Functions.
        $patt->function = CssCrush_Regex::create('<LB>(<ident>)(<p-token>)', 'S');
        $patt->varFunction = CssCrush_Regex::create('\$\( *(<ident>) *\)', 'S');
        $patt->thisFunction = CssCrush_Regex::createFunctionPatt(array('this'));

        $patt->string = '~(\'|")(?:\\\\\1|[^\1])*?\1~xS';
        $patt->commentAndString = '~
            # Quoted string (to EOF if unmatched).
            (\'|")(?:\\\\\1|[^\1])*?(?:\1|$)
            |
            # Block comment (to EOF if unmatched).
            /\*(?:.*?)(?:\*/|$)
        ~xsS';

        // As an exception we treat some @-rules like standard rule blocks.
        $patt->rule = '~
            # The selector.
            \n(
                [^@{}]+
                |
                (?: [^@{}]+ )? @(?: font-face|abstract|page ) (?!-)\b [^{]*
            )
            # The declaration block.
            \{ ([^{}]*) \}
        ~xiS';

        // Balanced bracket matching.
        $patt->balancedParens  = '~\(\s* ( (?: (?>[^()]+) | (?R) )* ) \s*\)~xS';
        $patt->balancedCurlies = '~\{\s* ( (?: (?>[^{}]+) | (?R) )* ) \s*\}~xS';

        // Misc.
        $patt->vendorPrefix = '~^-([a-z]+)-([a-z-]+)~iS';
        $patt->ruleDirective = '~^(?:(@include)|(@extends?)|(@name))[\s]+~iS';
        $patt->argListSplit = '~\s*[,\s]\s*~S';
        $patt->mathBlacklist = '~[^\.0-9\*\/\+\-\(\)]~S';
        $patt->cruftyHex = '~\#([[:xdigit:]])\1([[:xdigit:]])\2([[:xdigit:]])\3~S';
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

        return CssCrush_Regex::create("($template<LB>(?:$flat_list)$question)\(", 'iS');
    }
}

CssCrush_Regex::init();
