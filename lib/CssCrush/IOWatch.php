<?php
/**
 *
 * IO class for command line file watching.
 *
 */
class CssCrush_IOWatch extends CssCrush_IO
{
    public static $cacheData = array();

    static public function getOutputFileName ()
    {
        $process = CssCrush::$process;
        $options = $process->options;

        $output_basename = basename($process->input->filename, '.css');

        if (! empty($options->output_file)) {
            $output_basename = basename($options->output_file, '.css');
        }

        $suffix = '.crush';
        if ($process->input->dir !== $process->output->dir) {
            $suffix = '';
        }

        return "$output_basename$suffix.css";
    }

    public static function getCacheData ()
    {
        // Clear results from earlier processes.
        clearstatcache();
        CssCrush::$process->cacheData = array();

        return self::$cacheData;
    }

    public static function saveCacheData ()
    {
        self::$cacheData = CssCrush::$process->cacheData;
    }
}
