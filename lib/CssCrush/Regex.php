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
        $classes->u_token = '\?u' . $classes->token_id . '\?'; // URLs.
        $classes->t_token = '\?t' . $classes->token_id . '\?'; // Traces.
        $classes->a_token = '\?a(' . $classes->token_id . ')\?'; // Args.

        // Boundries.
        $classes->LB = '(?<![\w-])'; // Left ident boundry.
        $classes->RB = '(?![\w-])'; // Right ident boundry.

        // Recursive block matching.
        $classes->block = '(?<block>\{\s*(?<block_content>(?:(?>[^{}]+)|(?&block))*)\})';
        $classes->parens = '(?<parens>\(\s*(?<parens_content>(?:(?>[^()]+)|(?&parens))*)\))';

        // Misc.
        $classes->vendor = '-[a-zA-Z]+-';
        $classes->hex = '[[:xdigit:]]';
        $classes->newline = '(\r\n?|\n)';

        // Create standalone class patterns, add classes as class swaps.
        foreach ($classes as $name => $class) {
            $patt->{$name} = '~' . $class . '~S';
        }

        // Rooted classes.
        $patt->rooted_ident = '~^' . $classes->ident . '$~';
        $patt->rooted_number = '~^' . $classes->number . '$~';

        // @-rules.
        $patt->import = Regex::make('~@import \s+ ({{u_token}}) \s? ([^;]*);~ixS');
        $patt->charset = Regex::make('~@charset \s+ ({{s_token}}) \s*;~ixS');
        $patt->mixin = Regex::make('~@mixin \s+ (?<name>{{ident}}) \s* {{block}}~ixS');
        $patt->fragmentCapture = Regex::make('~@fragment \s+ (?<name>{{ident}}) \s* {{block}}~ixS');
        $patt->fragmentInvoke = Regex::make('~@fragment \s+ (?<name>{{ident}}) {{parens}}? \s* ;~ixS');
        $patt->abstract = Regex::make('~^@abstract \s+ (?<name>{{ident}})~ixS');

        // Functions.
        $patt->functionTest = Regex::make('~{{ LB }} (?<func_name>{{ ident }}) \(~xS');
        $patt->thisFunction = Functions::makePattern(array('this'));

        // Strings and comments.
        $patt->string = '~(\'|")(?:\\\\\1|[^\1])*?\1~xS';
        $patt->commentAndString = '~
            # Quoted string (to EOF if unmatched).
            (\'|")(?:\\\\\1|[^\1])*?(?:\1|$)
            |
            # Block comment (to EOF if unmatched).
            /\*(?:[^*]*\*+(?:[^/*][^*]*\*+)*/|.*)
        ~xsS';

        // Misc.
        $patt->vendorPrefix = '~^-([a-z]+)-([a-z-]+)~iS';
        $patt->ruleDirective = '~^(?:(@include)|(@extends?)|(@name))[\s]+~iS';
        $patt->argListSplit = '~\s*[,\s]\s*~S';
        $patt->cruftyHex = Regex::make('~\#({{hex}})\1({{hex}})\2({{hex}})\3~S');
        $patt->token = Regex::make('~^ \? (?<type>[a-zA-Z]) {{token_id}} \? $~xS');
    }

    public static function make($pattern)
    {
        static $cache = array();

        if (isset($cache[$pattern])) {
            return $cache[$pattern];
        }

        return $cache[$pattern] = preg_replace_callback('~\{\{ *(?<name>\w+) *\}\}~S', function ($m) {
            return Regex::$classes->{ $m['name'] };
        }, $pattern);
    }

    public static function matchAll($patt, $subject, $offset = 0)
    {
        $count = preg_match_all($patt, $subject, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $offset);

        return $count ? $matches : array();
    }
}

Regex::init();
