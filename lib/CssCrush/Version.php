<?php
/**
 *
 *  Version string.
 *
 */
namespace CssCrush;

class Version
{
    public $major;
    public $minor;
    public $patch;
    public $extra;

    public function __construct($version_string)
    {
        // Ideally expecting `git describe --long` (e.g. v2.0.0-5-gb28cdb5)
        // but also accepting simpler formats.
        preg_match('~^
                v?
                (?<major>\d+)
                (?:\.(?<minor>\d+))?
                (?:\.(?<patch>\d+))?
                (?:-(?<extra>.+))?
            $~ix',
            $version_string,
            $version);

        if ($version) {
            $this->major = (int) $version['major'];
            $this->minor = isset($version['minor']) ? (int) $version['minor'] : 0;
            $this->patch = isset($version['patch']) ? (int) $version['patch'] : 0;
            $this->extra = isset($version['extra']) ? $version['extra'] : null;
        }
    }

    public function __toString()
    {
        $out = (string) $this->major;

        if (isset($this->minor)) {
            $out .= ".$this->minor";
        }
        if (isset($this->patch)) {
            $out .= ".$this->patch";
        }
        if (isset($this->extra)) {
            $out .= "-$this->extra";
        }

        return "v$out";
    }

    public function compare($version_string)
    {
        $LESS = -1;
        $MORE = 1;
        $EQUAL = 0;

        $test = new Version($version_string);

        foreach (['major', 'minor', 'patch'] as $level) {

            if ($this->{$level} < $test->{$level}) {

                return $LESS;
            }
            elseif ($this->{$level} > $test->{$level}) {

                return $MORE;
            }
        }

        return $EQUAL;
    }

    public static function detect() {
        return self::gitDescribe() ?: self::packageDescribe();
    }

    public static function gitDescribe()
    {
        static $attempted, $version;
        if (! $attempted && file_exists(Crush::$dir . '/.git')) {
            $attempted = true;
            $command = 'cd ' . escapeshellarg(Crush::$dir) . ' && git describe --tag --long';
            @exec($command, $lines);
            if ($lines) {
                $version = new Version(trim($lines[0]));
                if (is_null($version->major)) {
                    $version = null;
                }
            }
        }

        return $version;
    }

    public static function packageDescribe()
    {
        static $attempted, $version;
        if (! $attempted && file_exists(Crush::$dir . '/package.json')) {
            $attempted = true;
            $package = json_decode(file_get_contents(Crush::$dir . '/package.json'));
            if ($package->version) {
                $version = new Version($package->version);
                if (is_null($version->major)) {
                    $version = null;
                }
            }
        }

        return $version;
    }
}
