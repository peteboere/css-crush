<?php
/**
 * 
 * Regex management
 * 
 */


class csscrush_regex {
	
	public static $patt;

	// Character classes
	public static $class;

	public static function init () {

		self::$patt = $patt = new stdclass();
		self::$class = $class = new stdclass();

		// Character classes
		$class->name = '[a-zA-Z0-9_-]+';
		$class->notName = '[^a-zA-Z0-9_-]+';

		// Patterns
		$patt->name = '!^' . $class->name . '$!';
		$patt->notName = '!^' . $class->notName . '$!';

		$patt->import = '!
			@import\s+    # import at-rule
			(?:
			url\(\s*([^\)]+)\s*\) # url function
			|                     # or
			([_s\d]+)             # string token
			)
			\s*([^;]*);   # media argument
		!x';

		$patt->variables = '!@(?:variables|define)\s*([^\{]*)\{\s*(.*?)\s*\};?!s';
		$patt->mixin     = '!@mixin\s*([^\{]*)\{\s*(.*?)\s*\};?!s';
		$patt->abstract  = csscrush_regex::create( '^@abstract\s+(<name>)', 'i' );
		$patt->comment   = '!/\*(.*?)\*/!sS';
		$patt->string    = '!(\'|")(?:\\1|[^\1])*?\1!S';

		// As an exception we treat some @-rules like standard rule blocks
		$patt->rule       = '!
			(\n(?:[^@{}]+|@(?:font-face|page|abstract)[^{]*)) # The selector
			\{([^{}]*)\}  # The declaration block
		!x';

		// Tokens
		$patt->commentToken = '!___c\d+___!';
		$patt->stringToken  = '!___s\d+___!';
		$patt->ruleToken    = '!___r\d+___!';
		$patt->parenToken   = '!___p\d+___!';
		$patt->urlToken     = '!___u\d+___!';

		// Functions
		$patt->varFunction  = '!(?:
			([^a-z0-9_-])
			var\(\s*([a-z0-9_-]+)\s*\)
			|
			\$\(\s*([a-z0-9_-]+)\s*\)  # Dollar syntax
		)!ix';
		$patt->function = '!(^|[^a-z0-9_-])([a-z_-]+)(___p\d+___)!i';
		
		// Misc.
		$patt->vendorPrefix = '!^-([a-z]+)-([a-z-]+)!';
		$patt->absoluteUrl  = '!^https?://!';
	}


	public static function create ( $pattern_template, $flags = '' ) {

		// Sugar
		$pattern = str_replace( 
						array( '<name>', '<!name>' ), 
						array( self::$class->name, self::$class->notName ), 
						$pattern_template );
		return '!' . $pattern . "!$flags";
	}
	
	
	public static function matchAll ( $patt, $subject, $preprocess_patt = false, $offset = 0 ) {
		
		if ( $preprocess_patt ) {
			// Assume case-insensitive
			$patt = self::create( $patt, 'i' );
		}

		preg_match_all( $patt, $subject, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $offset );
		return $matches;
	}
}

