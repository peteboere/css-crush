<?php
/**
 *
 *  Stream sugar.
 *
 */
class CssCrush_Stream
{
    public function __construct ($str)
    {
        $this->raw = $str;
    }

    public function __toString ()
    {
        return $this->raw;
    }

    static public function endsWith ($haystack, $needle)
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }

    public function update ($str)
    {
        $this->raw = $str;

        return $this;
    }

    public function substr ($start, $length = null)
    {
        if (! isset($length)) {

            return substr($this->raw, $start);
        }
        else {

            return substr($this->raw, $start, $length);
        }
    }

    public function matchAll ($patt, $preprocess_patt = false)
    {
        return CssCrush_Regex::matchAll($patt, $this->raw, $preprocess_patt);
    }

    public function replace ($find, $replacement)
    {
        $this->raw = str_replace($find, $replacement, $this->raw);

        return $this;
    }

    public function replaceHash ($replacements)
    {
        if ($replacements) {
            $this->raw = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $this->raw);
        }
        return $this;
    }

    public function pregReplace ($patt, $replacement)
    {
        $this->raw = preg_replace($patt, $replacement, $this->raw);
        return $this;
    }

    public function pregReplaceCallback ($patt, $callback)
    {
        $this->raw = preg_replace_callback($patt, $callback, $this->raw);
        return $this;
    }

    public function pregReplaceHash ($replacements)
    {
        if ($replacements) {
            $this->raw = preg_replace(
                array_keys($replacements),
                array_values($replacements),
                $this->raw);
        }
        return $this;
    }

    public function append ($append)
    {
        $this->raw .= $append;
        return $this;
    }

    public function prepend ($prepend)
    {
        $this->raw = $prepend . $this->raw;
        return $this;
    }

    public function splice ($replacement, $offset, $length = null)
    {
        $this->raw = substr_replace($this->raw, $replacement, $offset, $length);
        return $this;
    }

    public function trim ()
    {
        $this->raw = trim($this->raw);
        return $this;
    }

    public function rTrim ()
    {
        $this->raw = rtrim($this->raw);
        return $this;
    }

    public function lTrim ()
    {
        $this->raw = ltrim($this->raw);
        return $this;
    }
}
