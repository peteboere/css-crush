<?php

namespace CssCrush\UnitTest;

use CssCrush\Crush;

class CliTest extends \PHPUnit\Framework\TestCase
{
    protected $path;
    protected $sample;

    public function setUp(): void
    {
        $this->path = Crush::$dir . '/cli.php';
        $this->sample = 'p {color: red; position: absolute; opacity: 1;}';
    }

    public function testHelp()
    {
        exec("php \"$this->path\"", $lines);
        $help_text = implode("\n", $lines);

        $this->assertStringContainsStringIgnoringCase('USAGE:', $help_text);
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

        $this->assertStringContainsStringIgnoringCase($expected, file_get_contents($out_path));
    }

    public function testStats()
    {
        exec("echo '$this->sample' | php \"$this->path\" --stats --test", $lines);
        $output = implode('', $lines);

        $this->assertStringContainsStringIgnoringCase('Selector count: 1', $output);
        $this->assertStringContainsStringIgnoringCase('Rule count: 1', $output);
        $this->assertStringContainsStringIgnoringCase('Compile time:', $output);
        $this->assertStringContainsStringIgnoringCase('p{color:red;position:absolute;opacity:1}', $output);
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

        $sample = '@import "import.css"; baz {color: #111;}';
        exec("echo '$sample' | php \"$this->path\"", $lines);

        $this->assertEquals('foo{bar:baz}baz{color:#111}', implode('', $lines));

        chdir($currentDirectory);
    }
}
