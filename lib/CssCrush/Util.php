<?php
/**
 *
 *  General utilities.
 *
 */
class CssCrush_Util
{
    /*
     * Create html attribute string from array.
     */
    static public function htmlAttributes (array $attributes)
    {
        $attr_string = '';
        foreach ($attributes as $name => $value) {
            $value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false);
            $attr_string .= " $name=\"$value\"";
        }
        return $attr_string;
    }

    static public function normalizePath ($path, $strip_drive_letter = false)
    {
        if ($strip_drive_letter) {
            $path = preg_replace('~^[a-z]\:~i', '', $path);
        }
        // Backslashes and repeat slashes to a single forward slash.
        $path = rtrim(preg_replace('~[\\\\/]+~', '/', $path), '/');

        // Removing redundant './'.
        $path = str_replace('/./', '/', $path);
        if (strpos($path, './') === 0) {
            $path = substr($path, 2);
        }

        return CssCrush_Util::simplifyPath($path);
    }

    static public function simplifyPath ($path)
    {
        // Reduce redundant path segments (issue #32):
        // e.g 'foo/../bar' => 'bar'
        $patt = '~[^/.]+/\.\./~S';
        while (preg_match($patt, $path)) {
            $path = preg_replace($patt, '', $path);
        }
        return $path;
    }

    static public function find ()
    {
        foreach (func_get_args() as $file) {
            $file_path = CssCrush::$config->location . '/' . $file;
            if (file_exists($file_path)) {
                return $file_path;
            }
        }
        return false;
    }

    static public function stripCommentTokens ($str)
    {
        return preg_replace(CssCrush_Regex::$patt->c_token, '', $str);
    }

    static public function normalizeWhiteSpace ($str)
    {
        static $find, $replace;
        if (! $find) {
            $replacements = array(
                // Convert all whitespace sequences to a single space.
                '~\s+~S' => ' ',
                // Trim bracket whitespace where it's safe to do it.
                '~([\[(]) | ([\])])| ?([{}]) ?~S' => '${1}${2}${3}',
                // Trim whitespace around delimiters and special characters.
                '~ ?([;,]) ?~S' => '$1',
            );
            $find = array_keys($replacements);
            $replace = array_values($replacements);
        }

        return preg_replace($find, $replace, $str);
    }

    static public function splitDelimList ($str, $delim = ',')
    {
        $do_preg_split = strlen($delim) > 1;
        $str = trim($str);

        if (! $do_preg_split && strpos($str, $delim) === false) {
            return strlen($str) ? array($str) : array();
        }

        if ($match_count = preg_match_all(CssCrush_Regex::$patt->balancedParens, $str, $matches)) {
            $keys = array();
            foreach ($matches[0] as $index => &$value) {
                $keys[] = "?$index?";
            }
            $str = str_replace($matches[0], $keys, $str);
        }

        $list = $do_preg_split ? preg_split('~' . $delim . '~', $str) : explode($delim, $str);

        if ($match_count) {
            foreach ($list as &$value) {
                $value = str_replace($keys, $matches[0], $value);
            }
        }

        // Trim items and remove empty strings before returning.
        return array_filter(array_map('trim', $list), 'strlen');
    }

    static public function getLinkBetweenPaths ($path1, $path2, $directories = true)
    {
        // Normalise the paths.
        $path1 = trim(CssCrush_Util::normalizePath($path1, true), '/');
        $path2 = trim(CssCrush_Util::normalizePath($path2, true), '/');

        // The link between.
        $link = '';

        if ($path1 != $path2) {

            // Split the directory paths into arrays so we can compare segment by segment.
            $path1_segs = explode('/', $path1);
            $path2_segs = explode('/', $path2);

            // Shift the segments until they are on different branches.
            while (isset($path1_segs[0]) && isset($path2_segs[0]) && ($path1_segs[0] === $path2_segs[0])) {
                array_shift($path1_segs);
                array_shift($path2_segs);
            }

            $link = str_repeat('../', count($path1_segs)) . implode('/', $path2_segs);
        }

        $link = $link !== '' ? rtrim($link, '/') : '';

        // Add end slash if getting a link between directories.
        if ($link && $directories) {
            $link .= '/';
        }

        return $link;
    }

    static public function filePutContents ($file, $str, $caller = null)
    {
        if (@file_put_contents($file, $str, LOCK_EX)) {

            return true;
        }
        $error = "Could not write file '$file'.";
        CssCrush::logError($error);
        trigger_error(($caller ? $caller : __METHOD__) . ": $error\n", E_USER_WARNING);

        return false;
    }

    /*
     * Encode integer to Base64 VLQ.
     */
    static public function vlqEncode ($value)
    {
        static $VLQ_BASE_SHIFT, $VLQ_BASE, $VLQ_BASE_MASK, $VLQ_CONTINUATION_BIT, $BASE64_MAP;
        if (! $VLQ_BASE_SHIFT) {
            $VLQ_BASE_SHIFT = 5;
            $VLQ_BASE = 1 << $VLQ_BASE_SHIFT;
            $VLQ_BASE_MASK = $VLQ_BASE - 1;
            $VLQ_CONTINUATION_BIT = $VLQ_BASE;
            $BASE64_MAP = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/');
        }

        $vlq = $value < 0 ? ((-$value) << 1) + 1 : ($value << 1) + 0;

        $encoded = "";
        do {
          $digit = $vlq & $VLQ_BASE_MASK;
          $vlq >>= $VLQ_BASE_SHIFT;
          if ($vlq > 0) {
            $digit |= $VLQ_CONTINUATION_BIT;
          }
          $encoded .= $BASE64_MAP[$digit];

        } while ($vlq > 0);

        return $encoded;
    }
}
