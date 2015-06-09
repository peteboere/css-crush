<?php
/**
 *
 *  General utilities.
 *
 */
namespace CssCrush;

class Util
{
    public static function htmlAttributes(array $attributes, array $sort_order = null)
    {
        // Optionally sort attributes (for better readability).
        if ($sort_order) {
            uksort($attributes, function ($a, $b) use ($sort_order) {
                $a_index = array_search($a, $sort_order);
                $b_index = array_search($b, $sort_order);
                $a_found = is_int($a_index);
                $b_found = is_int($b_index);

                if ($a_found && $b_found) {
                    if ($a_index == $b_index) {
                        return 0;
                    }
                    return $a_index > $b_index ? 1 : -1;
                }
                elseif ($a_found && ! $b_found) {
                    return -1;
                }
                elseif ($b_found && ! $a_found) {
                    return 1;
                }

                return strcmp($a, $b);
            });
        }

        $str = '';
        foreach ($attributes as $name => $value) {
            $value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false);
            $str .= " $name=\"$value\"";
        }
        return $str;
    }

    public static function normalizePath($path, $strip_drive_letter = false)
    {
        if (! $path) {
            return '';
        }

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

        return Util::simplifyPath($path);
    }

    public static function simplifyPath($path)
    {
        // Reduce redundant path segments. e.g 'foo/../bar' => 'bar'
        $patt = '~[^/.]+/\.\./~S';
        while (preg_match($patt, $path)) {
            $path = preg_replace($patt, '', $path);
        }
        return $path;
    }

    public static function resolveUserPath($path, $recovery = null, $docRoot = null)
    {
        // System path.
        if ($realpath = realpath($path)) {
            $path = $realpath;
        }
        else {
            if (! $docRoot) {
                $docRoot = isset(Crush::$process->docRoot) ? Crush::$process->docRoot : Crush::$config->docRoot;
            }

            // Absolute path.
            if (strpos($path, '/') === 0) {
                // If $path is not doc_root based assume it's doc_root relative and prepend doc_root.
                if (strpos($path, $docRoot) !== 0) {
                    $path = $docRoot . $path;
                }
            }
            // Relative path. Try resolving based on the directory of the executing script.
            else {
                $path = Crush::$config->scriptDir . '/' . $path;
            }

            if (! file_exists($path) && is_callable($recovery)) {
                $path = $recovery($path);
            }
            $path = realpath($path);
        }

        return $path ? Util::normalizePath($path) : false;
    }

    public static function stripCommentTokens($str)
    {
        return preg_replace(Regex::$patt->c_token, '', $str);
    }

    public static function normalizeWhiteSpace($str)
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

    public static function splitDelimList($str, $options = array())
    {
        extract($options + array(
            'delim' => ',',
            'regex' => false,
            'allow_empty_strings' => false,
        ));

        $str = trim($str);

        if (! $regex && strpos($str, $delim) === false) {
            return ! $allow_empty_strings && ! strlen($str) ? array() : array($str);
        }

        if ($match_count = preg_match_all(Regex::$patt->parens, $str, $matches)) {
            $keys = array();
            foreach ($matches[0] as $index => &$value) {
                $keys[] = "?$index?";
            }
            $str = str_replace($matches[0], $keys, $str);
        }

        $list = $regex ? preg_split($regex, $str) : explode($delim, $str);

        if ($match_count) {
            foreach ($list as &$value) {
                $value = str_replace($keys, $matches[0], $value);
            }
        }

        $list = array_map('trim', $list);

        return ! $allow_empty_strings ? array_filter($list, 'strlen') : $list;
    }

    public static function getLinkBetweenPaths($path1, $path2, $directories = true)
    {
        $path1 = trim(Util::normalizePath($path1, true), '/');
        $path2 = trim(Util::normalizePath($path2, true), '/');

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

        // Append end slash if getting a link between directories.
        if ($link && $directories) {
            $link .= '/';
        }

        return $link;
    }

    public static function filePutContents($file, $str)
    {
        if ($stream = fopen($file, 'w')) {
            fwrite($stream, $str);
            fclose($stream);

            return true;
        }

        warning("Could not write file '$file'.");

        return false;
    }

    public static function parseIni($path, $sections = false)
    {
        if (! ($result = @parse_ini_file($path, $sections))) {
            notice("Ini file '$path' could not be parsed.");

            return false;
        }
        return $result;
    }

    public static function readConfigFile($path)
    {
        require_once $path;
        return Options::filter(get_defined_vars());
    }

    /*
     * Get raw value (useful if testing values that may or may not be a token).
     */
    public static function rawValue($value)
    {
        if ($tokenType = Tokens::test($value)) {
            if ($tokenType == 'u') {
                $value = Crush::$process->tokens->get($value)->value;
            }
            elseif ($tokenType == 's') {
                $value = Crush::$process->tokens->get($value);
            }
        }

        return $value;
    }

    /*
     * Encode integer to Base64 VLQ.
     */
    public static function vlqEncode($value)
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
