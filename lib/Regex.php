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

		$patt->import = '!@import (\?u\d+\?) ?([^;]*);!iS';

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
		$patt->varFunction = '!\$\(\s*([a-z0-9_-]+)\s*\)!iS';
		$patt->function = '!(^|[^a-z0-9_-])([a-z_-]+)(\?p\d+\?)!iS';

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
		$patt->charset       = '!@charset\s+(\?s\d+\?)\s*;!iS';
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
		return '#(?<![\w-])(' . implode( '|', $list ) . ')' . $question . '\(#iS';
	}
}

