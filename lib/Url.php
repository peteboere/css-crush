<?php
/**
 *
 *  URL tokens.
 *
 */
class CssCrush_Url
{
    public $protocol;
    public $isRelative;
    public $isRooted;
    public $convertToData;
    public $value;
    public $label;

    public function __construct ( $raw_value, $convert_to_data = false )
    {
        $regex = CssCrush_Regex::$patt;
        $process = CssCrush::$process;

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

    public function __toString ()
    {
        $quote = '';
        if ( preg_match( '![()*]!', $this->value ) || 'data' === $this->protocol ) {
            $quote = '"';
        }
        return "url($quote$this->value$quote)";
    }

    static public function get ( $token )
    {
        return CssCrush::$process->tokens->u[ $token ];
    }

    public function evaluate ()
    {
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

    public function toData ()
    {
        if ( $this->isRooted ) {
            $file = CssCrush::$process->docRoot . $this->value;
        }
        else {
            $file = CssCrush::$process->input->dir . "/$this->value";
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

    public function prepend ( $path_fragment )
    {
        $this->value = $path_fragment . $this->value;
    }

    public function resolveRootedPath ()
    {
        $process = CssCrush::$process;

        if ( ! file_exists ( $process->docRoot . $this->value ) ) {
            return false;
        }

        // Move upwards '..' by the number of slashes in baseURL to get a relative path.
        $this->value = str_repeat( '../', substr_count( $process->input->dirUrl, '/' ) ) .
            substr( $this->value, 1 );
    }

    public function simplify ()
    {
        $this->value = CssCrush_Util::simplifyPath( $this->value );
    }
}
