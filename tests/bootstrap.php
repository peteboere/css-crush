<?php

namespace {

    if (! $loader = @include __DIR__ . '/../vendor/autoload.php') {
        die('You must set up the project dependencies, run the following commands:'.PHP_EOL.
            'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
            'php composer.phar install'.PHP_EOL);
    }

    $loader->add('CssCrush\UnitTest', __DIR__ . '/unit');
}

namespace CssCrush\UnitTest
{

    function bootstrap_process($options = array())
    {
        $process = \CssCrush\Crush::$process = new \CssCrush\Process($options);
        $process->preCompile();
        $process->resolveContext();
        return $process;
    }

    function temp_file($contents = '')
    {
        $temporary_file = tempnam(sys_get_temp_dir(), 'crush');
        if ($contents) {
            file_put_contents($temporary_file, $contents);
        }
        return $temporary_file;
    }

    function stdout($message, $prepend_newline = false)
    {
        if (! is_string($message)) {
            $buffer = ob_start();
            print_r($message);
            $message = ob_get_clean();
        }
        fwrite(STDOUT, ($prepend_newline ? "\n" : '') . $message . "\n");
    }
}
