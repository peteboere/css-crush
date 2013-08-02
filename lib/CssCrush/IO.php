<?php
/**
 *
 * Interface for writing files, retrieving files and checking caches
 *
 */
class CssCrush_IO
{
    // Any setup that needs to be done
    static public function init ()
    {
        $process = CssCrush::$process;
        $process->cacheFile = "{$process->output->dir}/.csscrush";
    }

    static public function getOutputDir ()
    {
        $process = CssCrush::$process;
        $output_dir = $process->options->output_dir;
        return $output_dir ? $output_dir : $process->input->dir;
    }

    static public function testOutputDir ()
    {
        $output_dir = CssCrush::$process->output->dir;
        $pathtest = true;
        $error = false;

        if (! file_exists($output_dir)) {

            $error = "Output directory '$output_dir' doesn't exist.";
            $pathtest = false;
        }
        else if (! is_writable($output_dir)) {

            CssCrush::log('Attempting to change permissions.');

            if (! @chmod($output_dir, 0755)) {

                $error = "Output directory '$output_dir' is unwritable.";
                $pathtest = false;
            }
            else {
                CssCrush::log('Permissions updated.');
            }
        }

        if ($error) {
            CssCrush::logError($error);
            trigger_error(__METHOD__ . ": $error\n", E_USER_WARNING);
        }

        return $pathtest;
    }

    static public function getOutputFileName ()
    {
        $process = CssCrush::$process;
        $options = $process->options;

        $output_basename = basename($process->input->filename, '.css');

        if (! empty($options->output_file)) {
            $output_basename = basename($options->output_file, '.css');
        }

        return "$output_basename.crush.css";
    }

    static public function validateExistingOutput ()
    {
        $process = CssCrush::$process;
        $options = $process->options;
        $config = CssCrush::$config;
        $input = $process->input;

        // Search base directory for an existing compiled file.
        foreach (scandir($process->output->dir) as $filename) {

            if ($process->output->filename != $filename) {
                continue;
            }

            // Cached file exists.
            CssCrush::log('Cached file exists.');

            $existingfile = (object) array();
            $existingfile->filename = $filename;
            $existingfile->path = "{$process->output->dir}/$existingfile->filename";
            $existingfile->URL = "{$process->output->dirUrl}/$existingfile->filename";

            // Start off with the input file then add imported files
            $all_files = array($input->mtime);

            if (file_exists($existingfile->path) && isset($process->cacheData[$process->output->filename])) {

                // File exists and has config
                CssCrush::log('Cached file is registered.');

                foreach ($process->cacheData[$existingfile->filename]['imports'] as $import_file) {

                    // Check if this is docroot relative or input dir relative.
                    $root = strpos($import_file, '/') === 0 ? $process->docRoot : $process->input->dir;
                    $import_filepath = realpath($root) . "/$import_file";

                    if (file_exists($import_filepath)) {
                        $all_files[] = filemtime($import_filepath);
                    }
                    else {
                        // File has been moved, remove old file and skip to compile.
                        CssCrush::log('Recompiling - an import file has been moved.');

                        return false;
                    }
                }

                // Cast because the cached options may be a stdClass if an IO adapter has been used.
                $cached_options = (array) $process->cacheData[$existingfile->filename]['options'];
                $active_options = $options->get();

                // Compare runtime options and cached options for differences.
                $options_changed = false;
                foreach ($cached_options as $key => &$value) {
                    if (isset($active_options[$key]) && $active_options[$key] !== $value) {
                        $options_changed = true;
                        break;
                    }
                }

                // Check if any of the files have changed.
                $existing_datesum = $process->cacheData[$existingfile->filename]['datem_sum'];
                $files_changed = $existing_datesum != array_sum($all_files);

                if (! $options_changed && ! $files_changed) {

                    // Files have not been modified and config is the same: return the old file.
                    CssCrush::log(
                        "Files and options have not been modified, returning existing file '$existingfile->URL'.");
                    return $existingfile->URL . ($options->versioning !== false  ? "?$existing_datesum" : '');
                }
                else {

                    if ($options_changed) {
                        CssCrush::log('Recompiling - options have been modified.');
                    }
                    if ($files_changed) {
                        CssCrush::log('Recompiling - files have been modified.');
                    }
                }
            }
            else if (file_exists($existingfile->path)) {

                CssCrush::log('Recompiling - file exists but no cache data.');
            }

            return false;

        } // foreach

        return false;
    }

    static public function clearCache ($dir)
    {
        if (empty($dir)) {
            $dir = dirname(__FILE__);
        }
        else if (! file_exists($dir)) {
            return;
        }

        $configPath = $dir . '/' . CssCrush::$process->cacheFile;
        if (file_exists($configPath)) {
            unlink($configPath);
        }

        // Remove any compiled files
        $suffix = '.crush.css';
        $suffixLength = strlen($suffix);

        foreach (scandir($dir) as $file) {
            if (
                strpos($file, $suffix) === strlen($file) - $suffixLength
            ) {
                unlink($dir . "/{$file}");
            }
        }
    }

    static public function getCacheData ()
    {
        $config = CssCrush::$config;
        $process = CssCrush::$process;

        if (
            file_exists($process->cacheFile) &&
            $process->cacheData
        ) {
            // Already loaded and config file exists in the current directory
            return;
        }

        $cache_data_exists = file_exists($process->cacheFile);
        $cache_data_file_is_writable = $cache_data_exists ? is_writable($process->cacheFile) : false;
        $cache_data = array();

        if (
            $cache_data_exists &&
            $cache_data_file_is_writable &&
            $cache_data = json_decode(file_get_contents($process->cacheFile), true)
        ) {
            // Successfully loaded config file.
            CssCrush::log('Cache data loaded.');
        }
        else {
            // Config file may exist but not be writable (may not be visible in some ftp situations?)
            if ($cache_data_exists) {
                if (! @unlink($process->cacheFile)) {

                    $error = "Could not delete config data file.";
                    CssCrush::logError($error);
                    trigger_error(__METHOD__ . ": $error\n", E_USER_NOTICE);
                }
            }
            else {
                CssCrush::log('Creating cache data file.');
            }
            CssCrush_Util::filePutContents($process->cacheFile, json_encode(array()), __METHOD__);
        }

        return $cache_data;
    }

    static public function saveCacheData ()
    {
        $process = CssCrush::$process;

        CssCrush::log('Saving config.');
        CssCrush_Util::filePutContents($process->cacheFile, json_encode($process->cacheData), __METHOD__);
    }

    static public function write (CssCrush_Stream $stream)
    {
        $process = CssCrush::$process;
        $target = "{$process->output->dir}/{$process->output->filename}";

        if ($process->sourceMap) {
            $source_map_filename = $process->output->filename . '.map';
            $stream->append($process->newline . "/*# sourceMappingURL=$source_map_filename */");
        }

        if (CssCrush_Util::filePutContents($target, $stream, __METHOD__)) {

            if ($process->sourceMap) {
                if (defined('JSON_PRETTY_PRINT')) {
                    $data = json_encode($process->sourceMap, JSON_PRETTY_PRINT);
                }
                else {
                    $data = json_encode($process->sourceMap);
                }
                CssCrush_Util::filePutContents("{$process->output->dir}/$source_map_filename", $data, __METHOD__);
            }

            return "{$process->output->dirUrl}/{$process->output->filename}";
        }

        return false;
    }

    static final function registerInputFile ($file)
    {
        $input = CssCrush::$process->input;

        $input->filename = basename($file);
        $input->path = "$input->dir/$input->filename";

        if (! file_exists($input->path)) {

            // On failure return false.
            $error = "Input file '$input->filename' not found.";
            CssCrush::logError($error);
            trigger_error(__METHOD__ . ": $error\n", E_USER_WARNING);
            return false;
        }
        else {
            // Capture the modified time.
            $input->mtime = filemtime($input->path);
            return true;
        }
    }
}
