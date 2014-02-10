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
        $out_path = temp_file();

        file_put_contents($in_path, $this->sample);
        exec("php \"$this->path\" -i '$in_path' -o '$out_path' --enable property-sorter");
        $expected = 'p{position:absolute;opacity:1;color:red}';

        $this->assertEquals($expected, file_get_contents($out_path));
    }

    public function testContext()
    {
        $sample = '@import "context/import.css"; baz {bar: foo;}';
        $context = __DIR__;

        exec("echo '$sample' | php \"$this->path\" --context '$context'", $lines);
        $expected = 'foo{bar:baz}baz{bar:foo}';

        $this->assertEquals($expected, implode('',$lines));
    }
}
