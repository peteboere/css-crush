<?php
/**
 *
 * Recursive file importing
 *
 */
namespace CssCrush;

class Importer
{
    protected $process;

    public function __construct(Process $process)
    {
        $this->process = $process;
    }

    public function collate()
    {
        $process = $this->process;
        $options = $process->options;
        $regex = Regex::$patt;
        $input = $process->input;

        $str = '';

        // Keep track of all import file info for cache data.
        $mtimes = [];
        $filenames = [];

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
        if (! $this->prepareImport($str)) {

            return $str;
        }

        // This may be set non-zero during the script if an absolute @import URL is encountered.
        $search_offset = 0;

        // Recurses until the nesting heirarchy is flattened and all import files are inlined.
        while (preg_match($regex->import, $str, $match, PREG_OFFSET_CAPTURE, $search_offset)) {

            $match_len = strlen($match[0][0]);
            $match_start = $match[0][1];

            $import = new \stdClass();
            $import->url = $process->tokens->get($match[1][0]);
            $import->media = trim($match[2][0]);

            // Protocoled import urls are not processed. Stash for prepending to output.
            if ($import->url->protocol) {
                $str = substr_replace($str, '', $match_start, $match_len);
                $process->absoluteImports[] = $import;
                continue;
            }

            // Resolve import path information.
            $import->path = null;
            if ($import->url->isRooted) {
                $import->path = realpath($process->docRoot . $import->url->value);
            }
            else {
                $url =& $import->url;
                $candidates = ["$input->dir/$url->value"];

                // If `import_path` option is set implicit relative urls
                // are additionally searched under specified import path(s).
                if (is_array($options->import_path) && $url->isRelativeImplicit()) {
                    foreach ($options->import_path as $importPath) {
                        $candidates[] = "$importPath/$url->originalValue";
                    }
                }
                foreach ($candidates as $candidate) {
                    if (file_exists($candidate)) {
                        $import->path = realpath($candidate);
                        break;
                    }
                }
            }

            // If unsuccessful getting import contents continue with the import line removed.
            $import->content = $import->path ? @file_get_contents($import->path) : false;
            if ($import->content === false) {
                $errDesc = 'was not found';
                if ($import->path && ! is_readable($import->path)) {
                    $errDesc = 'is not readable';
                }
                if (! empty($process->sources)) {
                    $errDesc .= " (from within {$process->sources[0]})";
                }
                notice("@import '{$import->url->value}' $errDesc");
                $str = substr_replace($str, '', $match_start, $match_len);
                continue;
            }

            $import->dir = dirname($import->path);
            $import->relativeDir = Util::getLinkBetweenPaths($input->dir, $import->dir);

            // Import file exists so register it.
            $process->sources[] = $import->path;
            $mtimes[] = filemtime($import->path);
            $filenames[] = $import->relativeDir . basename($import->path);

            // If the import content doesn't pass syntax validation skip to next import.
            if (! $this->prepareImport($import->content)) {

                $str = substr_replace($str, '', $match_start, $match_len);
                continue;
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
                $this->rewriteImportedUrls($import);
            }

            if ($import->media) {
                $import->content = "@media $import->media {{$import->content}}";
            }

            $str = substr_replace($str, $import->content, $match_start, $match_len);
        }

        // Save only if caching is on and the hostfile object is associated with a real file.
        if ($input->path && $options->cache) {

            $process->cacheData[$process->output->filename] = [
                'imports' => $filenames,
                'datem_sum' => array_sum($mtimes) + $input->mtime,
                'options' => $options->get(),
            ];
            $process->io->saveCacheData();
        }

        return $str;
    }

    protected function rewriteImportedUrls($import)
    {
        $link = Util::getLinkBetweenPaths($this->process->input->dir, dirname($import->path));

        if (empty($link)) {
            return;
        }

        // Match all urls that are not imports.
        preg_match_all(Regex::make('~(?<!@import ){{u_token}}~iS'), $import->content, $matches);

        foreach ($matches[0] as $token) {

            $url = $this->process->tokens->get($token);

            if ($url->isRelative) {
                $url->prepend($link);
            }
        }
    }

    protected function prepareImport(&$str)
    {
        $regex = Regex::$patt;
        $process = $this->process;
        $tokens = $process->tokens;

        // Convert all EOL to unix style.
        $str = preg_replace('~\r\n?~', "\n", $str);

        // Trimming to reduce regex backtracking.
        $str = rtrim($this->captureCommentAndString(rtrim($str)));

        if (! $this->syntaxCheck($str)) {

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

        $this->addMarkers($str);

        $str = Util::normalizeWhiteSpace($str);

        return true;
    }

    protected function syntaxCheck(&$str)
    {
        // Catch obvious typing errors.
        $errors = false;
        $current_file = 'file://' . end($this->process->sources);
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

            // Reverse the string (and brackets) to find stray closing brackets.
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
            warning($validate_pairings($str, '{}') ?: "Unbalanced '{' in $current_file.");
        }
        if (! $balanced_parens) {
            $errors = true;
            warning($validate_pairings($str, '()') ?: "Unbalanced '(' in $current_file.");
        }

        return $errors ? false : true;
    }

    protected function addMarkers(&$str)
    {
        $process = $this->process;
        $currentFileIndex = count($process->sources) - 1;

        static $patt;
        if (! $patt) {
            $patt = Regex::make('~
                (?:^|(?<=[;{}]))
                (?<before>
                    (?: \s | {{c_token}} )*
                )
                (?<selector>
                    (?:
                        # Some @-rules are treated like standard rule blocks.
                        @(?: (?i)page|abstract|font-face(?-i) ) {{RB}} [^{]*
                        |
                        [^@;{}]+
                    )
                )
                \{
            ~xS');
        }

        $count = preg_match_all($patt, $str, $matches, PREG_OFFSET_CAPTURE);
        while ($count--) {

            $selectorOffset = $matches['selector'][$count][1];

            $line = 0;
            $before = substr($str, 0, $selectorOffset);
            if ($selectorOffset) {
                $line = substr_count($before, "\n");
            }

            $pointData = [$currentFileIndex, $line];

            // Source maps require column index too.
            if ($process->generateMap) {
                $pointData[] = strlen($before) - (strrpos($before, "\n") ?: 0);
            }

            // Splice in marker token (packing point_data into string is more memory efficient).
            $str = substr_replace(
                $str,
                $process->tokens->add(implode(',', $pointData), 't'),
                $selectorOffset,
                0);
        }
    }

    protected function captureCommentAndString($str)
    {
        $process = $this->process;
        $callback = function ($m) use ($process) {

            $fullMatch = $m[0];

            if (strpos($fullMatch, '/*') === 0) {

                // Bail without storing comment if output is minified or a private comment.
                if ($process->minifyOutput || strpos($fullMatch, '/*$') === 0) {

                    $label = '';
                }
                else {
                    // Fix broken comments as they will break any subsquent
                    // imported files that are inlined.
                    if (! preg_match('~\*/$~', $fullMatch)) {
                        $fullMatch .= '*/';
                    }
                    $label = $process->tokens->add($fullMatch, 'c');
                }
            }
            else {
                // Fix broken strings as they will break any subsquent
                // imported files that are inlined.
                if ($fullMatch[0] !== $fullMatch[strlen($fullMatch)-1]) {
                    $fullMatch .= $fullMatch[0];
                }

                // Backticked literals may have been used for custom property values.
                if ($fullMatch[0] === '`') {
                    $fullMatch = preg_replace('~\x5c`~', '`', trim($fullMatch, '`'));
                }

                $label = $process->tokens->add($fullMatch, 's');
            }

            return $process->generateMap ? Tokens::pad($label, $fullMatch) : $label;
        };

        return preg_replace_callback(Regex::$patt->commentAndString, $callback, $str);
    }
}
