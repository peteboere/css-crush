<?php
/**
 *
 * IO class for command line file watching.
 *
 */
namespace CssCrush\IO;

use CssCrush\Crush;
use CssCrush\IO;

class Watch extends IO
{
    public static $cacheData = [];

    public function getOutputFileName()
    {
        $process = $this->process;
        $options = $process->options;

        $input_basename = $output_basename = basename($process->input->filename, '.css');

        if (! empty($options->output_file)) {
            $output_basename = basename($options->output_file, '.css');
        }

        $suffix = '.crush';
        if (($process->input->dir !== $process->output->dir) || ($input_basename !== $output_basename)) {
            $suffix = '';
        }

        return "$output_basename$suffix.css";
    }

    public function getCacheData()
    {
        // Clear results from earlier processes.
        clearstatcache();
        $this->process->cacheData = [];

        return self::$cacheData;
    }

    public function saveCacheData()
    {
        self::$cacheData = $this->process->cacheData;
    }
}
