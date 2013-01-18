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

    static public function init ()
    {
        self::$patt = $patt = new stdclass();
        self::$classes = $classes = new stdclass();

        // Character classes.
        $classes->ident = '[a-zA-Z0-9_-]+';
        $classes->number = '[+-]?\d*\.?\d+';

        // Patterns.
        $patt->ident = '~^' . $classes->ident . '$~';
        $patt->number = '~^' . $classes->number . '$~';

        // @-rule blocks.
        $patt->import        = '~@import\s+(\?u\d+\?)\s?([^;]*);~iS';
        $patt->variables     = '~@(?:define|variables) *([^\{]*)\{ *(.*?) *\};?~iS';
        $patt->mixin         = '~@mixin *([^\{]*)\{ *(.*?) *\};?~iS';
        $patt->abstract      = CssCrush_Regex::create( '^@abstract\s+(<ident>)', 'i' );
        $patt->selectorAlias = CssCrush_Regex::create( '@selector-alias +\:(<ident>) +([^;]+) *;', 'iS' );
        $patt->ifDefine      = CssCrush_Regex::create( '@ifdefine +(not +)?(<ident>) *\{', 'iS' );
        $patt->fragmentDef   = CssCrush_Regex::create( '@fragment +(<ident>) *\{', 'iS' );
        $patt->fragmentCall  = CssCrush_Regex::create( '@fragment +(<ident>) *(\(|;)', 'iS' );

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
                (?: [^@{}]+ )? @(?: font-face|page|abstract ) (?!-)\b [^{]*
            )
            # The declaration block.
            \{ ([^{}]*) \}
        ~xiS';

        // Balanced bracket matching.
        $patt->balancedParens  = '!\(\s* ( (?: (?>[^()]+) | (?R) )* ) \s*\)!xS';
        $patt->balancedCurlies = '!\{\s* ( (?: (?>[^{}]+) | (?R) )* ) \s*\}!xS';

        // Tokens.
        $patt->cToken = '!\?c\d+\?!'; // Comments
        $patt->sToken = '!\?s\d+\?!'; // Strings
        $patt->rToken = '!\?r\d+\?!'; // Rules
        $patt->pToken = '!\?p\d+\?!'; // Parens
        $patt->uToken = '!\?u\d+\?!'; // URLs
        $patt->tToken = '!\?t\d+\?!'; // Traces
        $patt->aToken = '!\?arg(\d+)\?!'; // Args

        // Functions.
        $patt->function = '!(^|[^a-z0-9_-])([a-z_-]+)(\?p\d+\?)!iS';
        $patt->varFunction = CssCrush_Regex::create( '\$\(\s*(<ident>)\s*\)', 'iS' );
        $patt->varFunctionStart = '!(\$)\(!';
        $patt->argFunction = CssCrush_Regex::createFunctionMatchPatt( array( 'arg' ) );
        $patt->queryFunction = CssCrush_Regex::createFunctionMatchPatt( array( 'query' ) );
        $patt->thisFunction = CssCrush_Regex::createFunctionMatchPatt( array( 'this' ) );

        // Misc.
        $patt->vendorPrefix  = '!^-([a-z]+)-([a-z-]+)!iS';
        $patt->mixinExtend   = '!^(?:(@include|mixin)|(@?extends?))[\s\:]+!iS';
        $patt->argListSplit  = '!\s*[,\s]\s*!S';
        $patt->mathBlacklist = '![^\.0-9\*\/\+\-\(\)]!S';
        $patt->charset       = '!@charset\s+(\?s\d+\?)\s*;!iS';
        $patt->cruftyHex     = '!\#([[:xdigit:]])\1([[:xdigit:]])\2([[:xdigit:]])\3!S';
    }

    static public function create ( $pattern_template, $flags = '', $delim = '!' )
    {
        // Sugar.
        $pattern = str_replace(
                        array( '<ident>' ),
                        array( self::$classes->ident ),
                        $pattern_template );
        return "$delim{$pattern}$delim{$flags}";
    }

    static public function matchAll ( $patt, $subject, $preprocess_patt = false, $offset = 0 )
    {
        if ( $preprocess_patt ) {
            // Assume case-insensitive.
            $patt = self::create( $patt, 'i' );
        }

        $count = preg_match_all( $patt, $subject, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $offset );
        return $count ? $matches : array();
    }

    static public function createFunctionMatchPatt ( $list, $include_math_function = false )
    {
        $question = '';
        if ( $include_math_function ) {
            $question = '?';
            // Signing on math bare parens.
            $list[] = '-';
        }

        foreach ( $list as &$fn_name ) {
            $fn_name = preg_quote( $fn_name );
        }
        return '~(?<![\w-])(' . implode( '|', $list ) . ')' . $question . '\(~iS';
    }
}

CssCrush_Regex::init();

