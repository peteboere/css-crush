<?php
/**
 *
 *  URL tokens.
 *
 */
class CssCrush_Url
{
    public $protocol;

    public $isAbsolute;
    public $isRelative;
    public $isRooted;
    public $isData;

    public $noRewrite;
    public $convertToData;
    public $value;
    public $label;

    public function __construct ($raw_value, $convert_to_data = false)
    {
        $regex = CssCrush_Regex::$patt;
        $process = CssCrush::$process;

        if (preg_match($regex->s_token, $raw_value)) {
            $this->value = trim($process->fetchToken($raw_value), '\'"');
            $process->releaseToken($raw_value);
        }
        else {
            $this->value = $raw_value;
        }

        $this->evaluate();
        $this->label = $process->addToken($this, 'u');
    }

    public function __toString ()
    {
        if ($this->convertToData) {
            $this->toData();
        }

        if ($this->isRelative || $this->isRooted) {
            $this->simplify();
        }

        // Only wrap url with quotes if it contains tricky characters.
        $quote = '';
        if ($this->isData || preg_match('~[()*]~S', $this->value)) {
            $quote = '"';
        }

        return "url($quote$this->value$quote)";
    }

    static public function get ($token)
    {
        return CssCrush::$process->tokens->u[$token];
    }

    public function evaluate ()
    {
        // Protocol based url.
        if (preg_match('~^([a-z]+)\:~i', $this->value, $m)) {

            $this->protocol = strtolower($m[1]);
            switch ($this->protocol) {
                case 'data':
                    $type = 'data';
                    break;
                default:
                    $type = 'absolute';
                    break;
            }
        }

        // Relative and rooted urls.
        else {
            $type = 'relative';
            $leading_variable = strpos($this->value, '$(') === 0;

            // Normalize './' led paths.
            $this->value = preg_replace('~^\.\/+~i', '', $this->value);

            if ($leading_variable || ($this->value !== '' && $this->value[0] === '/')) {
                $type = 'rooted';
            }

            // Normalize slashes.
            $this->value = rtrim(preg_replace('~[\\\\/]+~', '/', $this->value), '/');
        }

        $this->setType($type);

        return $this;
    }

    public function getAbsolutePath ()
    {
        $path = false;
        if ($this->protocol) {
            $path = $this->value;
        }
        elseif ($this->isRelative || $this->isRooted) {
            $path = CssCrush::$config->docRoot .
                ($this->isRelative ? $this->toRoot()->simplify()->value : $this->value);
        }
        return $path;
    }

    public function resolveRootedPath ()
    {
        $process = CssCrush::$process;

        if (! file_exists ($process->docRoot . $this->value)) {
            return false;
        }

        // Move upwards '..' by the number of slashes in baseURL to get a relative path.
        $this->value = str_repeat('../', substr_count($process->input->dirUrl, '/')) .
            substr($this->value, 1);
    }

    public function prepend ($path_fragment)
    {
        if ($this->isRelative) {
            $this->value = $path_fragment . $this->value;
        }

        return $this;
    }

    public function toRoot ()
    {
        if ($this->isRelative) {
            $this->prepend(CssCrush::$process->input->dirUrl . '/');
            $this->setType('rooted');
        }

        return $this;
    }

    public function toData ()
    {
        // Only make one conversion attempt.
        $this->convertToData = false;

        $file = CssCrush::$process->docRoot . $this->toRoot()->value;

        // File not found.
        if (! file_exists($file)) {

            return $this;
        }

        $file_ext = pathinfo($file, PATHINFO_EXTENSION);

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

        if (! isset($allowed_file_extensions[$file_ext])) {

            return $this;
        }

        $mime_type = $allowed_file_extensions[$file_ext];
        $base64 = base64_encode(file_get_contents($file));
        $this->value = "data:$mime_type;base64,$base64";

        $this->setType('data')->protocol = 'data';

        return $this;
    }

    public function setType ($type = 'absolute')
    {
        $this->isAbsolute = false;
        $this->isRooted = false;
        $this->isRelative = false;
        $this->isData = false;

        switch ($type) {
            case 'absolute':
                $this->isAbsolute = true;
                break;
            case 'relative':
                $this->isRelative = true;
                break;
            case 'rooted':
                $this->isRooted = true;
                break;
            case 'data':
                $this->isData = true;
                $this->convertToData = false;
                break;
        }

        return $this;
    }

    public function simplify ()
    {
        if (! $this->isData) {
            $this->value = CssCrush_Util::simplifyPath($this->value);
        }
        return $this;
    }
}
