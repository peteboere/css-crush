<?php
/**
 *
 * Regex management.
 *
 */

class csscrush_regex {

	public static $patt;

	// Character classes.
	public static $classes;

	public static function init () {

		self::$patt = $patt = (object) array();
		self::$classes = $classes = (object) array();

		// Character classes.
		$classes->ident = '[a-zA-Z0-9_-]+';

		// Patterns.
		$patt->ident = '!^' . $classes->ident . '$!';

		$patt->import = '!
			@import\s+             # import at-rule
			(?:
			url\(\s*([^\)]+)\s*\)  # url function
			|                      # or
			([_s\d]+)              # string token
			)
			\s*([^;]*);            # media argument
		!xiS';

		$patt->variables = '!@(?:variables|define)\s*([^\{]*)\{\s*(.*?)\s*\};?!s';
		$patt->mixin     = '!@mixin\s*([^\{]*)\{\s*(.*?)\s*\};?!s';
		$patt->abstract  = csscrush_regex::create( '^@abstract\s+(<ident>)', 'i' );

		$patt->commentAndString = '!
			# Quoted string (to EOF if unmatched).
			(\'|")(?:\\\\\1|[^\1])*?(?:\1|$)
			|
			# Block comment (to EOF if unmatched).
			/\*(?:.*?)(?:\*/|$)
		!xsS';

		// As an exception we treat some @-rules like standard rule blocks.
		$patt->rule = '~
			# The selector.
			\n(
				[^@{}]+
				|
				(?: [^@{}]+ )? @(?: font-face|page|abstract ) (?!-)\b [^{]*
			)
			# The declaration block.
			\{ ( [^{}]* ) \}
		~xiS';

		// Balanced bracket matching.
		$patt->balancedParens  = '!\( (?: (?: (?>[^()]+) | (?R) )* ) \)!xS';
		$patt->balancedCurlies = '!\{ (?: (?: (?>[^{}]+) | (?R) )* ) \}!xS';

		// Tokens.
		$patt->commentToken = '!___c\d+___!';
		$patt->stringToken  = '!___s\d+___!';
		$patt->ruleToken    = '!___r\d+___!';
		$patt->parenToken   = '!___p\d+___!';
		$patt->urlToken     = '!___u\d+___!';
		$patt->traceToken   = '!___t\d+___!';
		$patt->argToken     = '!___arg(\d+)___!';

		// Functions.
		$patt->varFunction = '!\$\(\s*([a-z0-9_-]+)\s*\)!iS';
		$patt->function = '!(^|[^a-z0-9_-])([a-z_-]+)(___p\d+___)!i';

		// Specific functions.
		$patt->argFunction = csscrush_regex::createFunctionMatchPatt( array( 'arg' ) );
		$patt->queryFunction = csscrush_regex::createFunctionMatchPatt( array( 'query' ) );
		$patt->thisFunction = csscrush_regex::createFunctionMatchPatt( array( 'this' ) );

		// Misc.
		$patt->vendorPrefix  = '!^-([a-z]+)-([a-z-]+)!iS';
		$patt->mixinExtend   = '!^(?:(@include|mixin)|(@?extends?))[\s\:]+!iS';
		$patt->absoluteUrl   = '!^https?://!';
		$patt->argListSplit  = '!\s*[,\s]\s*!S';
		$patt->mathBlacklist = '![^\.0-9\*\/\+\-\(\)]!S';
		$patt->charset       = '!@charset\s+(___s\d+___)\s*;!i';
	}


	public static function create ( $pattern_template, $flags = '' ) {

		// Sugar.
		$pattern = str_replace(
						array( '<ident>' ),
						array( self::$classes->ident ),
						$pattern_template );
		return '!' . $pattern . "!$flags";
	}


	public static function matchAll ( $patt, $subject, $preprocess_patt = false, $offset = 0 ) {

		if ( $preprocess_patt ) {
			// Assume case-insensitive.
			$patt = self::create( $patt, 'i' );
		}

		$count = preg_match_all( $patt, $subject, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $offset );
		return $count ? $matches : array();
	}


	public static function createFunctionMatchPatt ( $list, $include_unnamed_function = false ) {

		$question = $include_unnamed_function ? '?' : '';

		foreach ( $list as &$fn_name ) {
			$fn_name = preg_quote( $fn_name );
		}
		return '!(^|[^a-z0-9_-])(' . implode( '|', $list ) . ')' . $question . '\(!iS';
	}
}

