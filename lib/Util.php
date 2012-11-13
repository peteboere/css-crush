<?php
/**
 *
 *  General utilities.
 *
 */
class csscrush_util {

    // Create html attribute string from array.
    static public function htmlAttributes ( array $attributes ) {

        $attr_string = '';
        foreach ( $attributes as $name => $value ) {
            $value = htmlspecialchars( $value, ENT_COMPAT, 'UTF-8', false );
            $attr_string .= " $name=\"$value\"";
        }
        return $attr_string;
    }

    static public function normalizePath ( $path, $strip_drive_letter = false ) {

        if ( $strip_drive_letter ) {
            $path = preg_replace( '!^[a-z]\:!i', '', $path );
        }
        // Backslashes and repeat slashes to a single forward slash.
        $path = rtrim( preg_replace( '![\\\\/]+!', '/', $path ), '/' );

        // Removing redundant './'.
        $path = str_replace( '/./', '/', $path );
        if ( strpos( $path, './' ) === 0 ) {
            $path = substr( $path, 2 );
        }

        return csscrush_util::simplifyPath( $path );
    }

    static public function simplifyPath ( $path ) {

        // Reduce redundant path segments (issue #32):
        // e.g 'foo/../bar' => 'bar'
        $patt = '~[^/.]+/\.\./~S';
        while ( preg_match( $patt, $path ) ) {
            $path = preg_replace( $patt, '', $path );
        }
        return $path;
    }

    static public function find () {

        foreach ( func_get_args() as $file ) {
            $file_path = csscrush::$config->location . '/' . $file;
            if ( file_exists( $file_path ) ) {
                return $file_path;
            }
        }
        return false;
    }

    static public function stripCommentTokens ( $str ) {

        return preg_replace( csscrush_regex::$patt->cToken, '', $str );
    }

    static public function normalizeWhiteSpace ( $str ) {

        $replacements = array(
            // Convert all whitespace sequences to a single space.
            '!\s+!S' => ' ',
            // Trim bracket whitespace where it's safe to do it.
            '!([\[(]) | ([\])])| ?([{}]) ?!S' => '${1}${2}${3}',
            // Trim whitespace around delimiters and special characters.
            '! ?([;,]) ?!S' => '$1',
        );
        return preg_replace(
            array_keys( $replacements ), array_values( $replacements ), $str );
    }

    static public function splitDelimList ( $str, $delim = ',', $trim = true ) {

        $do_preg_split = strlen( $delim ) > 1 ? true : false;

        if ( ! $do_preg_split && strpos( $str, $delim ) === false ) {
            if ( $trim ) {
                $str = trim( $str );
            }
            return array( $str );
        }

        if ( strpos( $str, '(' ) !== false ) {
            $match_count
                = preg_match_all( csscrush_regex::$patt->balancedParens, $str, $matches );
        }
        else {
            $match_count = 0;
        }

        if ( $match_count ) {
            $keys = array();
            foreach ( $matches[0] as $index => &$value ) {
                $keys[] = "?$index?";
            }
            $str = str_replace( $matches[0], $keys, $str );
        }

        if ( $do_preg_split ) {
            $list = preg_split( '!' . $delim . '!', $str );
        }
        else {
            $list = explode( $delim, $str );
        }

        if ( $match_count ) {
            foreach ( $list as &$value ) {
                $value = str_replace( $keys, $matches[0], $value );
            }
        }

        if ( $trim ) {
            $list = array_map( 'trim', $list );
        }

        return $list;
    }

    static public function getLinkBetweenDirs ( $dir1, $dir2 ) {

        // Normalise the paths.
        $dir1 = trim( csscrush_util::normalizePath( $dir1, true ), '/' );
        $dir2 = trim( csscrush_util::normalizePath( $dir2, true ), '/' );

        // The link between.
        $link = '';

        if ( $dir1 != $dir2 ) {

            // Split the directory paths into arrays so we can compare segment by segment.
            $dir1_segs = explode( '/', $dir1 );
            $dir2_segs = explode( '/', $dir2 );

            // Shift the segments until they are on different branches.
            while ( isset( $dir1_segs[0] ) && isset( $dir2_segs[0] ) && ( $dir1_segs[0] === $dir2_segs[0] ) ) {
                array_shift( $dir1_segs );
                array_shift( $dir2_segs );
            }

            $link = str_repeat( '../', count( $dir1_segs ) ) . implode( '/', $dir2_segs );
        }

        // Add closing slash.
        return $link !== '' ? rtrim( $link, '/' ) . '/' : '';
    }
}


/**
 *
 *  Balanced bracket matching on the main stream.
 *
 */
class csscrush_balancedMatch {

    public function __construct ( csscrush_stream $stream, $offset, $brackets = '{}' ) {

        $this->stream = $stream;
        $this->offset = $offset;
        $this->match = null;
        $this->length = 0;

        list( $opener, $closer ) = str_split( $brackets, 1 );

        if ( strpos( $stream->raw, $opener, $this->offset ) === false ) {
            return;
        }

        if ( substr_count( $stream->raw, $opener ) !== substr_count( $stream->raw, $closer ) ) {
            $sample = substr( $stream->raw, $this->offset, 25 );
            trigger_error( __METHOD__ . ": Unmatched token near '$sample'.\n", E_USER_WARNING );
            return;
        }

        $patt = $opener === '{' ?
            csscrush_regex::$patt->balancedCurlies : csscrush_regex::$patt->balancedParens;

        if ( preg_match( $patt, $stream->raw, $m, PREG_OFFSET_CAPTURE, $this->offset ) ) {

            $this->match = $m;
            $this->matchLength = strlen( $m[0][0] );
            $this->matchStart = $m[0][1];
            $this->matchEnd = $this->matchStart + $this->matchLength;
            $this->length = $this->matchEnd - $this->offset;
        }
        else {
            trigger_error( __METHOD__ . ": Could not match '$opener'. Exiting.\n", E_USER_WARNING );
        }
    }

    public function inside () {

        return $this->match[1][0];
    }

    public function whole () {

        return substr( $this->stream->raw, $this->offset, $this->length );
    }

    public function replace ( $replacement ) {

        $this->stream->splice( $replacement, $this->offset, $this->length );
    }

    public function unWrap () {

        $this->stream->splice( $this->inside(), $this->offset, $this->length );
    }

    public function nextIndexOf ( $needle ) {

        return strpos( $this->stream->raw, $needle, $this->offset );
    }
}


/**
 *
 *  URL tokens.
 *
 */
class csscrush_url {

    public $protocol;
    public $isRelative;
    public $isRooted;
    public $convertToData;
    public $value;
    public $label;

    public function __construct ( $raw_value, $convert_to_data = false ) {

        $regex = csscrush_regex::$patt;
        $process = csscrush::$process;

        if ( preg_match( $regex->sToken, $raw_value ) ) {
            $this->value = trim( $process->fetchToken( $raw_value ), '\'"' );
            $process->releaseToken( $raw_value );
        }
        else {
            $this->value = $raw_value;
        }

        $this->evaluate();
        $this->label = $process->addToken( $this, 'u' );
    }

    public function __toString () {
        $quote = '';
        if ( preg_match( '![()*]!', $this->value ) || 'data' === $this->protocol ) {
            $quote = '"';
        }
        return "url($quote$this->value$quote)";
    }

    static public function get ( $token ) {
        return csscrush::$process->tokens->u[ $token ];
    }

    public function evaluate () {

        $leading_variable = strpos( $this->value, '$(' ) === 0;

        if ( preg_match( '!^([a-z]+)\:!i', $this->value, $m ) ) {
            $this->protocol = strtolower( $m[1] );
        }
        else {
            // Normalize './' led paths.
            $this->value = preg_replace( '!^\.\/+!i', '', $this->value );
            if ( $this->value !== '' && $this->value[0] === '/' ) {
                $this->isRooted = true;
            }
            elseif ( ! $leading_variable ) {
                $this->isRelative = true;
            }
            // Normalize slashes.
            $this->value = rtrim( preg_replace( '![\\\\/]+!', '/', $this->value ), '/' );
        }
        return $this;
    }

    public function toData () {

        if ( $this->isRooted ) {
            $file = csscrush::$process->docRoot . $this->value;
        }
        else {
            $file = csscrush::$process->input->dir . "/$this->value";
        }

        // File not found.
        if ( ! file_exists( $file ) ) {
            return;
        }

        $file_ext = pathinfo( $file, PATHINFO_EXTENSION );

        // Only allow certain extensions
        static $allowed_file_extensions = array(
            'woff' => 'application/x-font-woff;charset=utf-8',
            'ttf'  => 'font/truetype;charset=utf-8',
            'svg'  => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'gif'  => 'image/gif',
            'jpeg' => 'image/jpg',
            'jpg'  => 'image/jpg',
            'png'  => 'image/png',
        );

        if ( ! isset( $allowed_file_extensions[ $file_ext ] ) ) {
            return;
        }

        $mime_type = $allowed_file_extensions[ $file_ext ];
        $base64 = base64_encode( file_get_contents( $file ) );
        $this->value = "data:$mime_type;base64,$base64";
        $this->protocol = 'data';
    }

    public function prepend ( $path_fragment ) {
        $this->value = $path_fragment . $this->value;
    }

    public function resolveRootedPath () {

        $process = csscrush::$process;

        if ( ! file_exists ( $process->docRoot . $this->value ) ) {
            return false;
        }

        // Move upwards '..' by the number of slashes in baseURL to get a relative path.
        $this->value = str_repeat( '../', substr_count( $process->input->dirUrl, '/' ) ) .
            substr( $this->value, 1 );
    }

    public function simplify () {

        $this->value = csscrush_util::simplifyPath( $this->value );
    }
}


/**
 *
 *  Stream sugar.
 *
 */
class csscrush_stream {

    public function __construct ( $str ) {
        $this->raw = $str;
    }

    public function __toString () {
        return $this->raw;
    }

    static public function endsWith ( $haystack, $needle ) {

        return substr( $haystack, -strlen( $needle ) ) === $needle;
    }

    public function update ( $str ) {
        $this->raw = $str;
        return $this;
    }

    public function substr ( $start, $length = null ) {
        if ( is_null( $length ) ) {
            return substr( $this->raw, $start );
        }
        else {
            return substr( $this->raw, $start, $length );
        }
    }

    public function matchAll ( $patt, $preprocess_patt = false ) {
        return csscrush_regex::matchAll( $patt, $this->raw, $preprocess_patt );
    }

    public function replace ( $find, $replacement ) {
        $this->raw = str_replace( $find, $replacement, $this->raw );
        return $this;
    }

    public function replaceHash ( $replacements ) {
        if ( $replacements ) {
            $this->raw = str_replace(
                array_keys( $replacements ),
                array_values( $replacements ),
                $this->raw );
        }
        return $this;
    }

    public function pregReplace ( $patt, $replacement ) {
        $this->raw = preg_replace( $patt, $replacement, $this->raw );
        return $this;
    }

    public function pregReplaceCallback ( $patt, $callback ) {
        $this->raw = preg_replace_callback( $patt, $callback, $this->raw );
        return $this;
    }

    public function pregReplaceHash ( $replacements ) {
        if ( $replacements ) {
            $this->raw = preg_replace(
                array_keys( $replacements ),
                array_values( $replacements ),
                $this->raw );
        }
        return $this;
    }

    public function append ( $append ) {
        $this->raw .= $append;
        return $this;
    }

    public function prepend ( $prepend ) {
        $this->raw = $prepend . $this->raw;
        return $this;
    }

    public function splice ( $replacement, $offset, $length = null ) {
        $this->raw = substr_replace( $this->raw, $replacement, $offset, $length );
        return $this;
    }

    public function trim () {
        $this->raw = trim( $this->raw );
        return $this;
    }

    public function rTrim () {
        $this->raw = rtrim( $this->raw );
        return $this;
    }

    public function lTrim () {
        $this->raw = ltrim( $this->raw );
        return $this;
    }
}


/**
 *
 *  Version string.
 *
 */
class csscrush_version {

    public $major = 0;
    public $minor = 0;
    public $revision = 0;
    public $extra;

    public function __construct ( $version_string ) {

        if ( ( $hyphen_pos = strpos( $version_string, '-' ) ) !== false ) {
            $this->extra = substr( $version_string, $hyphen_pos + 1 );
            $version_string = substr( $version_string, 0, $hyphen_pos );
        }

        $parts = explode( '.', $version_string );

        if ( ( $major = array_shift( $parts ) ) !== null ) {
            $this->major = (int) $major;
        }
        if ( ( $minor = array_shift( $parts ) ) !== null ) {
            $this->minor = (int) $minor;
        }
        if ( ( $revision = array_shift( $parts ) ) !== null ) {
            $this->revision = (int) $revision;
        }
    }

    public function __toString () {

        $out = (string) $this->major;

        if ( ! is_null( $this->minor ) ) {
            $out .= ".$this->minor";
        }
        if ( ! is_null( $this->revision ) ) {
            $out .= ".$this->revision";
        }
        if ( ! is_null( $this->extra ) ) {
            $out .= "-$this->extra";
        }

        return $out;
    }

    public function compare ( $version_string ) {

        $LESS  = -1;
        $MORE  = 1;
        $EQUAL = 0;

        $test = new csscrush_version( $version_string );

        foreach ( array( 'major', 'minor', 'revision' ) as $level ) {

            if ( $this->{ $level } < $test->{ $level } ) {
                return $LESS;
            }
            elseif ( $this->{ $level } > $test->{ $level } ) {
                return $MORE;
            }
        }

        return $EQUAL;
    }
}

