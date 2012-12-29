<?php
/**
 *
 *  Version string.
 *
 */
class CssCrush_Version
{
    public $major = 0;
    public $minor = 0;
    public $revision = 0;
    public $extra;

    public function __construct ( $version_string )
    {
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

    public function __toString ()
    {
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

    public function compare ( $version_string )
    {
        $LESS  = -1;
        $MORE  = 1;
        $EQUAL = 0;

        $test = new CssCrush_Version( $version_string );

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
