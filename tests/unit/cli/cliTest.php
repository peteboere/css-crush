<?php

namespace CssCrush\UnitTest;

use CssCrush\Crush;

class CliTest extends \PHPUnit_Framework_TestCase
{
    protected $path;
    protected $sample;

    public function setUp()
    {
        $this->path = Crush::$dir . '/cli.php';
        $this->sample = 'p {color: red; position: absolute; opacity: 1;}';
    }

    public function testHelp()
    {
        exec("php \"$this->path\"", $lines);
        $help_text = implode("\n", $lines);

        $this->assertContains('USAGE:', $help_text);
    }

    public function testPlugin()
    {
        exec("echo '$this->sample' | php \"$this->path\" --enable property-sorter", $lines);
        $expected = 'p{position:absolute;opacity:1;color:red}';

        $this->assertEquals($expected, implode('',$lines));
    }

    public function testIO()
    {
        $in_path = temp_file();
        $out_path = temp_file() . '.css';

        file_put_contents($in_path, $this->sample);
        exec("php \"$this->path\" -i '$in_path' -o '$out_path' --enable property-sorter --test");
        $expected = 'p{position:absolute;opacity:1;color:red}';

        $this->assertContains($expected, file_get_contents($out_path));
    }

    public function testContext()
    {
        $sample = '@import "context/import.css"; baz {bar: foo;}';
        $context = __DIR__;

        exec("echo '$sample' | php \"$this->path\" --context '$context'", $lines);

        $this->assertEquals('foo{bar:baz}baz{bar:foo}', implode('', $lines));
    }

    public function testConfigFile()
    {
        $currentDirectory = getcwd();
        chdir(__DIR__ . '/context');

        $sample = '@import "import.css"; @color dark #111; baz {color: dark;}';
        exec("echo '$sample' | php \"$this->path\"", $lines);

        $this->assertEquals('foo{bar:baz}baz{color:#111}', implode('', $lines));

        chdir($currentDirectory);
    }
}
