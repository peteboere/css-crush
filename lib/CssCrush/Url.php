<?php
/**
 *
 *  URL tokens.
 *
 */
namespace CssCrush;

class Url
{
    public $protocol;

    public $isAbsolute;
    public $isRelative;
    public $isRooted;
    public $isData;

    public $noRewrite;
    public $convertToData;
    public $value;
    public $originalValue;

    public function __construct($raw_value)
    {
        if (preg_match(Regex::$patt->s_token, $raw_value)) {
            $this->value = trim(Crush::$process->tokens->pop($raw_value), '\'"');
        }
        else {
            $this->value = $raw_value;
        }

        $this->originalValue = $this->value;
        $this->evaluate();
    }

    public function __toString()
    {
        if ($this->convertToData) {
            $this->toData();
        }

        if ($this->isRelative || $this->isRooted) {
            $this->simplify();
        }

        if ($this->isData) {
            return 'url("' . preg_replace('~(?<!\x5c)"~', '\\"', $this->value) . '")';
        }

        // Only wrap url with quotes if it contains tricky characters.
        $quote = '';
        if (preg_match('~[()*\s]~S', $this->value)) {
            $quote = '"';
        }

        return "url($quote$this->value$quote)";
    }

    public function update($new_value)
    {
        $this->value = $new_value;

        return $this->evaluate();
    }

    public function evaluate()
    {
        // Protocol, protocol-relative (//) or fragment URL.
        if (preg_match('~^(?: (?<protocol>[a-z]+)\: | \/{2} | \# )~ix', $this->value, $m)) {

            $this->protocol = ! empty($m['protocol']) ? strtolower($m['protocol']) : 'relative';

            switch ($this->protocol) {
                case 'data':
                    $type = 'data';
                    break;
                default:
                    $type = 'absolute';
                    break;
            }
        }
        // Relative and rooted URLs.
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

    public function isRelativeImplicit()
    {
        return $this->isRelative && preg_match('~^([\w$-]|\.[^\/.])~', $this->originalValue);
    }

    public function getAbsolutePath()
    {
        $path = false;
        if ($this->protocol) {
            $path = $this->value;
        }
        elseif ($this->isRelative || $this->isRooted) {
            $path = Crush::$process->docRoot .
                ($this->isRelative ? $this->toRoot()->simplify()->value : $this->value);
        }
        return $path;
    }

    public function prepend($path_fragment)
    {
        if ($this->isRelative) {
            $this->value = $path_fragment . $this->value;
        }

        return $this;
    }

    public function toRoot()
    {
        if ($this->isRelative) {
            $this->prepend(Crush::$process->input->dirUrl . '/');
            $this->setType('rooted');
        }

        return $this;
    }

    public function toData()
    {
        // Only make one conversion attempt.
        $this->convertToData = false;

        $file = Crush::$process->docRoot . $this->toRoot()->value;

        // File not found.
        if (! file_exists($file)) {

            return $this;
        }

        $file_ext = pathinfo($file, PATHINFO_EXTENSION);

        // Only allow certain extensions
        static $allowed_file_extensions = [
            'woff' => 'application/x-font-woff;charset=utf-8',
            'ttf'  => 'font/truetype;charset=utf-8',
            'svg'  => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'gif'  => 'image/gif',
            'jpeg' => 'image/jpg',
            'jpg'  => 'image/jpg',
            'png'  => 'image/png',
        ];

        if (! isset($allowed_file_extensions[$file_ext])) {

            return $this;
        }

        $mime_type = $allowed_file_extensions[$file_ext];
        $base64 = base64_encode(file_get_contents($file));
        $this->value = "data:$mime_type;base64,$base64";

        $this->setType('data')->protocol = 'data';

        return $this;
    }

    public function setType($type = 'absolute')
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

    public function simplify()
    {
        if ($this->isRelative || $this->isRooted) {
            $this->value = Util::simplifyPath($this->value);
        }
        return $this;
    }
}
