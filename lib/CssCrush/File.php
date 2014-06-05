<?php
/**
 *
 *  Output file resources.
 *
 */
namespace CssCrush;

class File
{
    public $url;
    public $path;
    public $process;

    public function __construct(Process $process)
    {
        $this->process = $process;
        $io = $process->io;

        Crush::runStat('paths');

        if ($process->options->cache) {
            $process->cacheData = $io->getCacheData();
            if ($io->validateCache()) {
                $this->url = $io->getOutputUrl();
                $this->path = $io->getOutputDir() . '/' . $io->getOutputFilename();
                $process->release();

                return;
            }
        }

        $string = $process->compile();

        if ($io->write($string)) {
            $this->url = $io->getOutputUrl();
            $this->path = $io->getOutputDir() . '/' . $io->getOutputFilename();
        }
    }

    public function __toString()
    {
        return $this->url;
    }
}
