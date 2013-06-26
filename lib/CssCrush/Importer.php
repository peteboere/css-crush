<?php
/**
 *
 * Recursive file importing
 *
 */
class CssCrush_Importer
{
    static public function hostfile ()
    {
        $config = CssCrush::$config;
        $process = CssCrush::$process;
        $options = $process->options;
        $regex = CssCrush_Regex::$patt;
        $input = $process->input;

        $str = '';

        // Keep track of all import file info for cache data.
        $mtimes = array();
        $filenames = array();

        // Resolve main input; a string of css or a file.
        if (isset($input->string)) {
            $str .= $input->string;
            $process->currentFile = 'inline css';
        }
        else {
            $str .= file_get_contents($input->path);
            $process->currentFile = 'file://' . $input->path;
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

            // Fetch the URL object.
            $url = $process->tokens->get($match[1][0]);

            // Pass over protocoled import urls.
            if ($url->protocol) {
                $search_offset = $match_end;
                continue;
            }

            // The media context (if specified).
            $media_context = trim($match[2][0]);

            // Create import object.
            $import = (object) array();
            $import->url = $url;
            $import->mediaContext = $media_context;

            // Resolve import realpath.
            if ($url->isRooted) {
                $import->path = realpath($process->docRoot . $import->url->value);
            }
            else {
                $import->path = realpath("$input->dir/{$import->url->value}");
            }

            // Get the import contents, if unsuccessful just continue with the import line removed.
            $import->content = @file_get_contents($import->path);
            if ($import->content === false) {

                CssCrush::log("Import file '{$import->url->value}' not found");
                $str = substr_replace($str, '', $match_start, $match_len);
                continue;
            }

            // Import file opened successfully so we process it:
            //   - We need to resolve import statement urls in all imported files since
            //     they will be brought inline with the hostfile
            $process->currentFile = 'file://' . $import->path;

            // If there are unmatched brackets inside the import, strip it.
            if (! self::prepareForStream($import->content)) {

                $str = substr_replace($str, '', $match_start, $match_len);
                continue;
            }

            $import->dir = dirname($import->url->value);

            // Store import file info for cache validation.
            $mtimes[] = filemtime($import->path);
            $filenames[] = $import->url->value;

            // Alter all the @import urls to be paths relative to the hostfile.
            foreach (CssCrush_Regex::matchAll($regex->import, $import->content) as $m) {

                // Fetch the matched URL.
                $url2 = $process->tokens->get($m[1][0]);

                // Try to resolve absolute paths.
                // On failure strip the @import statement.
                if ($url2->isRooted) {
                    $url2->resolveRootedPath();
                }
                else {
                    $url2->prepend("$import->dir/");
                }
            }

            // Optionally rewrite relative url and custom function data-uri references.
            if ($options->rewrite_import_urls) {
                self::rewriteImportedUrls($import);
            }

            // Add media context if it exists.
            if ($import->mediaContext) {
                $import->content = "@media $import->mediaContext {{$import->content}}";
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
            $process->ioCall('saveCacheData');
        }

        return $str;
    }

    static protected function rewriteImportedUrls ($import)
    {
        static $non_import_urls_patt;
        if (! $non_import_urls_patt) {
            $non_import_urls_patt = CssCrush_Regex::create('(?<!@import )<u-token>', 'iS');
        }

        $link = CssCrush_Util::getLinkBetweenDirs(
            CssCrush::$process->input->dir, dirname($import->path));

        if (empty($link)) {
            return;
        }

        // Match all urls that are not imports.
        preg_match_all($non_import_urls_patt, $import->content, $matches);

        foreach ($matches[0] as $token) {

            // Fetch the matched URL.
            $url = CssCrush::$process->tokens->get($token);

            if ($url->isRelative) {
                // Prepend the relative url prefix.
                $url->prepend($link);
            }
        }
    }

    static protected function prepareForStream (&$str)
    {
        $regex = CssCrush_Regex::$patt;
        $process = CssCrush::$process;
        $tokens = $process->tokens;

        // Convert all end-of-lines to unix style.
        $str = preg_replace('~\r\n?~', "\n", $str);

        // Capture all comments and string literals.
        $str = $tokens->captureCommentAndString($str);

        // Normalize double-colon pseudo elements for backwards compatability.
        $str = preg_replace('~::(after|before|first-(?:letter|line))~iS', ':$1', $str);

        // If @charset is set store it.
        if (preg_match($regex->charset, $str, $m)) {
            $replace = '';
            if (! $process->charset) {
                // Keep track of newlines for line numbering.
                $replace = str_repeat("\n", substr_count($m[0], "\n"));
                $process->charset = trim($tokens->get($m[1]), '"\'');
            }
            $str = preg_replace($regex->charset, $replace, $str);
        }

        // Catch obvious typing errors.
        $parse_errors = array();
        $current_file = $process->currentFile;
        $balanced_parens = substr_count($str, "(") === substr_count($str, ")");
        $balanced_curlies = substr_count($str, "{") === substr_count($str, "}");

        if (! $balanced_parens) {
            $parse_errors[] = "Unmatched '(' in $current_file.";
        }
        if (! $balanced_curlies) {
            $parse_errors[] = "Unmatched '{' in $current_file.";
        }

        if ($parse_errors) {
            foreach ($parse_errors as $error_msg) {
                CssCrush::logError($error_msg);
                trigger_error("$error_msg\n", E_USER_WARNING);
            }
            return false;
        }

        $str = $tokens->captureUrls($str);

        // Optionally add tracing stubs.
        if ($process->addTracingStubs) {
            self::addTracingStubs($str);
        }

        // Strip unneeded whitespace.
        $str = CssCrush_Util::normalizeWhiteSpace($str);

        return true;
    }

    static protected function addTracingStubs (&$str)
    {
        static $token_or_whitespace;
        if (! $token_or_whitespace) {
            $token_or_whitespace = CssCrush_Regex::create('(\s*<c-token>\s*|\s+)', 'S');
        }

        $selector_patt = '~ (^|;|\})+ ([^;{}]+) (\{) ~xmS';

        $matches = CssCrush_Regex::matchAll($selector_patt, $str);

        // Start from last match and move backwards.
        while ($m = array_pop($matches)) {

            // Shortcuts for readability.
            list($full_match, $before, $content, $after) = $m;
            $full_match_text  = $full_match[0];
            $full_match_start = $full_match[1];

            // The correct before string.
            $before = substr($full_match_text, 0, $content[1] - $full_match_start);

            // Split the matched selector part.
            $content_parts = preg_split($token_or_whitespace, $content[0], null,
                PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            foreach ($content_parts as $part) {

                if (! preg_match($token_or_whitespace, $part)) {

                    // Match to a valid selector.
                    if (preg_match('~^([^@]|@(?:abstract|page))~iS', $part)) {

                        // Count line breaks between the start of stream and
                        // the matched selector to get the line number.
                        $line_num = 1;
                        $str_before = "";
                        $selector_index = $full_match_start + strlen($before);
                        if ($selector_index) {
                            $str_before = substr($str, 0, $selector_index);
                            $line_num = substr_count($str_before, "\n") + 1;
                        }

                        // Get the currently processed file path, and escape it.
                        $current_file = str_replace(' ', '%20', CssCrush::$process->currentFile);
                        $current_file = preg_replace('~[^\w-]~', '\\\\$0', $current_file);

                        // Splice in tracing stub.
                        $label = CssCrush::$process->tokens->add("@media -sass-debug-info{filename{font-family:$current_file}line{font-family:\\00003$line_num}}", 't');

                        $str = $str_before . $label . substr($str, $selector_index);
                    }
                    else {
                        // Not matched as a valid selector, move on.
                        continue 2;
                    }
                    break;
                }

                // Append split segment to $before.
                $before .= $part;
            }
        }
    }
}
