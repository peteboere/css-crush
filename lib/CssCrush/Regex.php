<?php
/**
 *
 * Regex management.
 *
 */
namespace CssCrush;

class Regex
{
    // Patterns.
    public static $patt;

    // Character classes.
    public static $classes;

    public static $swaps = array();

    public static function init()
    {
        self::$patt = $patt = new \stdClass();
        self::$classes = $classes = new \stdClass();

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
            self::$swaps['{{' . str_replace('_', '-', $name) . '}}'] = $class;
            $patt->{$name} = '~' . $class . '~S';
        }

        // Rooted classes.
        $patt->rooted_ident = '~^' . $classes->ident . '$~';
        $patt->rooted_number = '~^' . $classes->number . '$~';

        // @-rules.
        $patt->import = Regex::make('~@import \s+ ({{u-token}}) \s? ([^;]*);~ixS');
        $patt->charset = Regex::make('~@charset \s+ ({{s-token}}) \s*;~ixS');
        $patt->mixin = Regex::make('~@mixin \s+ (?<name>{{ident}}) \s* {{block}}~ixS');
        $patt->ifDefine = Regex::make('~@ifdefine \s+ (not \s+)? ({{ident}}) \s* \{~ixS');
        $patt->fragmentCapture = Regex::make('~@fragment \s+ (?<name>{{ident}}) \s* {{block}}~ixS');
        $patt->fragmentInvoke = Regex::make('~@fragment \s+ (?<name>{{ident}}) {{parens}}? \s* ;~ixS');
        $patt->abstract = Regex::make('~^@abstract \s+ (?<name>{{ident}})~ixS');

        // Functions.
        $patt->function = Regex::make('~{{LB}} ({{ident}}) ({{p-token}})~xS');
        $patt->varFunction = Regex::make('~\$\( \s* ({{ident}}) \s* \)~xS');
        $patt->thisFunction = Regex::makeFunctionPatt(array('this'));

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
        $patt->ruleFirstPass = Regex::make('~
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
            {{block}}
        ~xS');

        $patt->rule = Regex::make('~
            (?<trace_token> {{t-token}} )
            \s*
            (?<selector> [^{]+ )
            \s*
            {{block}}
        ~xiS');

        // Misc.
        $patt->vendorPrefix = '~^-([a-z]+)-([a-z-]+)~iS';
        $patt->ruleDirective = '~^(?:(@include)|(@extends?)|(@name))[\s]+~iS';
        $patt->argListSplit = '~\s*[,\s]\s*~S';
        $patt->mathBlacklist = '~[^\.0-9\*\/\+\-\(\)]~S';
        $patt->cruftyHex = Regex::make('~\#({{hex}})\1({{hex}})\2({{hex}})\3~S');
    }

    public static function make($pattern)
    {
        static $cache = array(), $find, $replace;
        if (isset($cache[$pattern])) {

            return $cache[$pattern];
        }
        elseif (! $find) {
            $find = array_keys(self::$swaps);
            $replace = array_values(self::$swaps);
        }

        return $cache[$pattern] = str_replace($find, $replace, $pattern);
    }

    public static function matchAll($patt, $subject, $offset = 0)
    {
        $count = preg_match_all($patt, $subject, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $offset);

        return $count ? $matches : array();
    }

    public static function makeFunctionPatt($list, $options = array())
    {
        // Bare parens.
        $question = '';
        if (! empty($options['bare_paren'])) {
            $question = '?';
            // Signing on math bare parens.
            $list[] = '-';
        }

        // Templating func.
        $template = '';
        if (! empty($options['templating'])) {
            $template = '#|';
        }

        $flat_list = implode('|', array_map('preg_quote', $list));

        return Regex::make("~($template{{LB}}(?:$flat_list)$question)\(~iS");
    }
}

Regex::init();
