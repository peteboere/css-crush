<?php
/**
 *
 * Recursive file importing
 *
 */
namespace CssCrush;

class Importer
{
    public static function hostfile()
    {
        $config = CssCrush::$config;
        $process = CssCrush::$process;
        $options = $process->options;
        $regex = Regex::$patt;
        $input = $process->input;

        $str = '';

        // Keep track of all import file info for cache data.
        $mtimes = array();
        $filenames = array();

        // Resolve main input; a string of css or a file.
        if (isset($input->string)) {
            $str .= $input->string;
            $process->sources[] = 'Inline CSS';
        }
        else {
            $str .= file_get_contents($input->path);
            $process->sources[] = $input->path;
        }

        // If there's a parsing error go no further.
        if (! self::prepareForStream($str)) {

            return $str;
        }

        // This may be set non-zero during the script if an absolute @import URL is encountered.
        $search_offset = 0;

        // Recurses until the nesting heirarchy is flattened and all import files are inlined.
        while (preg_match($regex->import, $str, $match, PREG_OFFSET_CAPTURE, $search_offset)) {

            $match_len = strlen($match[0][0]);
            $match_start = $match[0][1];
            $match_end = $match_start + $match_len;

            // If just stripping the import statements.
            if (isset($input->importIgnore)) {
                $str = substr_replace($str, '', $match_start, $match_len);
                continue;
            }

            // Create import object for convenience.
            $import = new \stdClass();
            $import->url = $process->tokens->get($match[1][0]);
            $import->media = trim($match[2][0]);

            // Skip import if the import URL is protocoled.
            if ($import->url->protocol) {
                $search_offset = $match_end;
                continue;
            }

            // Resolve import path information.
            if ($import->url->isRooted) {
                $import->path = realpath($process->docRoot . $import->url->value);
            }
            else {
                $import->path = realpath("$input->dir/{$import->url->value}");
            }
            $import->dir = dirname($import->path);

            // If unsuccessful getting import contents continue with the import line removed.
            $import->content = @file_get_contents($import->path);
            if ($import->content === false) {

                $config->logger->debug("Import file '{$import->url->value}' not found");
                $str = substr_replace($str, '', $match_start, $match_len);
                continue;
            }

            // Import file exists so register it.
            $process->sources[] = $import->path;
            $mtimes[] = filemtime($import->path);
            $filenames[] = $import->url->value;

            // If the import content doesn't pass syntax validation skip to next import.
            if (! self::prepareForStream($import->content)) {

                $str = substr_replace($str, '', $match_start, $match_len);
                continue;
            }

            // Resolve a relative link between the import file and the host-file.
            if ($import->url->isRooted) {
                $import->relativeDir = Util::getLinkBetweenPaths($import->dir, $input->dir);
            }
            else {
                $import->relativeDir = dirname($import->url->value);
            }

            // Alter all embedded import URLs to be relative to the host-file.
            foreach (Regex::matchAll($regex->import, $import->content) as $m) {

                $nested_url = $process->tokens->get($m[1][0]);

                // Resolve rooted paths.
                if ($nested_url->isRooted) {
                    $link = Util::getLinkBetweenPaths(dirname($nested_url->getAbsolutePath()), $import->dir);
                    $nested_url->update($link . basename($nested_url->value));
                }
                elseif (strlen($import->relativeDir)) {
                    $nested_url->prepend("$import->relativeDir/");
                }
            }

            // Optionally rewrite relative url and custom function data-uri references.
            if ($options->rewrite_import_urls) {
                self::rewriteImportedUrls($import);
            }

            if ($import->media) {
                $import->content = "@media $import->media {{$import->content}}";
            }

            $str = substr_replace($str, $import->content, $match_start, $match_len);
        }

        // Save only if caching is on and the hostfile object is associated with a real file.
        if ($input->path && $options->cache) {

            $process->cacheData[$process->output->filename] = array(
                'imports'   => $filenames,
                'datem_sum' => array_sum($mtimes) + $input->mtime,
                'options'   => $options->get(),
            );

            // Save config changes.
            $process->io('saveCacheData');
        }

        return $str;
    }

    static protected function rewriteImportedUrls($import)
    {
        $link = Util::getLinkBetweenPaths(
            CssCrush::$process->input->dir, dirname($import->path));

        if (empty($link)) {

            return;
        }

        // Match all urls that are not imports.
        preg_match_all(Regex::make('~(?<!@import ){{u-token}}~iS'), $import->content, $matches);

        foreach ($matches[0] as $token) {

            $url = CssCrush::$process->tokens->get($token);

            if ($url->isRelative) {
                // Prepend the relative url prefix.
                $url->prepend($link);
            }
        }
    }

    static protected function prepareForStream(&$str)
    {
        $regex = Regex::$patt;
        $process = CssCrush::$process;
        $tokens = $process->tokens;

        // Convert all end-of-lines to unix style.
        $str = preg_replace('~\r\n?~', "\n", $str);

        // rtrim is necessary to avoid catastrophic backtracking in large files and some edge cases.
        $str = rtrim(self::captureCommentAndString($str));

        if (! self::checkSyntax($str)) {

            $str = '';
            return false;
        }

        // Normalize double-colon pseudo elements for backwards compatability.
        $str = preg_replace('~::(after|before|first-(?:letter|line))~iS', ':$1', $str);

        // Store @charset if set.
        if (preg_match($regex->charset, $str, $m)) {
            $replace = '';
            if (! $process->charset) {
                // Keep track of newlines for line numbering.
                $replace = str_repeat("\n", substr_count($m[0], "\n"));
                $process->charset = trim($tokens->get($m[1]), '"\'');
            }
            $str = preg_replace($regex->charset, $replace, $str);
        }

        $str = $tokens->captureUrls($str, true);

        self::addMarkers($str);

        $str = Util::normalizeWhiteSpace($str);

        return true;
    }

    static protected function checkSyntax(&$str)
    {
        // Catch obvious typing errors.
        $errors = false;
        $current_file = 'file://' . end(CssCrush::$process->sources);
        $balanced_parens = substr_count($str, "(") === substr_count($str, ")");
        $balanced_curlies = substr_count($str, "{") === substr_count($str, "}");

        $validate_pairings = function ($str, $pairing) use ($current_file)
        {
            if ($pairing === '{}') {
                $opener_patt = '~\{~';
                $balancer_patt = Regex::make('~^{{block}}~');
            }
            else {
                $opener_patt = '~\(~';
                $balancer_patt = Regex::make('~^{{parens}}~');
            }

            // Find unbalanced opening brackets.
            preg_match_all($opener_patt, $str, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $offset = $m[1];
                if (! preg_match($balancer_patt, substr($str, $offset), $m)) {
                    $substr = substr($str, 0, $offset);
                    $line = substr_count($substr, "\n") + 1;
                    $column = strlen($substr) - strrpos($substr, "\n");
                    return "Unbalanced '{$pairing[0]}' in $current_file, Line $line, Column $column.";
                }
            }

            // Reverse the stream (and brackets) to find stray closing brackets.
            $str = strtr(strrev($str), $pairing, strrev($pairing));

            preg_match_all($opener_patt, $str, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $offset = $m[1];
                $substr = substr($str, $offset);
                if (! preg_match($balancer_patt, $substr, $m)) {
                    $line = substr_count($substr, "\n") + 1;
                    $column = strpos($substr, "\n");
                    return "Stray '{$pairing[1]}' in $current_file, Line $line, Column $column.";
                }
            }

            return false;
        };

        if (! $balanced_curlies) {
            $errors = true;
            CssCrush::$config->logger->warning(
                '[[CssCrush]] - ' . $validate_pairings($str, '{}') ?: "Unbalanced '{' in $current_file.");
        }
        if (! $balanced_parens) {
            $errors = true;
            CssCrush::$config->logger->warning(
                '[[CssCrush]] - ' . $validate_pairings($str, '()') ?: "Unbalanced '(' in $current_file.");
        }

        return $errors ? false : true;
    }

    static protected function addMarkers(&$str)
    {
        $process = CssCrush::$process;
        $current_file_index = count($process->sources) -1;

        $count = preg_match_all(Regex::$patt->ruleFirstPass, $str, $matches, PREG_OFFSET_CAPTURE);
        while ($count--) {

            $selector_offset = $matches['selector'][$count][1];

            $line = 0;
            $before = substr($str, 0, $selector_offset);
            if ($selector_offset) {
                $line = substr_count($before, "\n");
            }

            $point_data = array($current_file_index, $line);

            // Source maps require column index too.
            if ($process->generateMap) {
                $point_data[] = strlen($before) - strrpos($before, "\n") - 1;
            }

            // Splice in marker token (packing point_data into string is more memory efficient).
            $str = substr_replace(
                $str,
                $process->tokens->add(implode(',', $point_data), 't'),
                $selector_offset,
                0);
        }
    }

    static protected function captureCommentAndString($str)
    {
        $callback = function ($m) {

            $full_match = $m[0];
            $process = CssCrush::$process;

            if (strpos($full_match, '/*') === 0) {

                // Bail without storing comment if output is minified or a private comment.
                if (
                    $process->minifyOutput ||
                    strpos($full_match, '/*$') === 0
                ) {
                    return Tokens::pad('', $full_match);
                }

                // Fix broken comments as they will break any subsquent
                // imported files that are inlined.
                if (! preg_match('~\*/$~', $full_match)) {
                    $full_match .= '*/';
                }
                $label = $process->tokens->add($full_match, 'c');
            }
            else {

                // Fix broken strings as they will break any subsquent
                // imported files that are inlined.
                if ($full_match[0] !== $full_match[strlen($full_match)-1]) {
                    $full_match .= $full_match[0];
                }
                $label = $process->tokens->add($full_match, 's');
            }

            return Tokens::pad($label, $full_match);
        };

        return preg_replace_callback(Regex::$patt->commentAndString, $callback, $str);
    }
}
