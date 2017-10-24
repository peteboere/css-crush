<?php

namespace CssCrush\UnitTest;

use CssCrush\Options;
use CssCrush\Version;

class OptionsTest extends \PHPUnit_Framework_TestCase
{
    public $testFile;

    public function setUp()
    {
        bootstrap_process();
        $this->testFile = temp_file("\n foo {bar: baz;} \n\n baz {bar: foo;}");
    }

    public function testDefaults()
    {
        $options = new Options();
        $standardOptions = Options::filter();

        $this->assertEquals($standardOptions, $options->get());

        $testOptions = array('plugins' => array('foo', 'bar'), 'minify' => false);
        $options = new Options($testOptions);

        $initialOptionsCopy = $testOptions + $standardOptions;
        $this->assertEquals($initialOptionsCopy, $options->get());
    }

    public function testBoilerplate()
    {
        $boilerplate = <<<TPL
Line breaks
preserved

{{version}}
TPL;

        $result = csscrush_string('foo { bar: baz; }', array(
            'boilerplate' => temp_file($boilerplate),
            'newlines' => 'unix',
        ));

        $this->assertContains(' * ' . Version::detect(), (string) $result);
        $this->assertContains(" * Line breaks\n * preserved\n *", (string) $result);
    }

    public function testFormatters()
    {
        $sample = '/* A comment */ foo {bar: baz;}';

        $single_line_expected = <<<TPL
/* A comment */
foo { bar: baz; }

TPL;
        $single_line = csscrush_string($sample, array('formatter' => 'single-line'));
        $this->assertEquals($single_line_expected, $single_line);

        $padded_expected = <<<TPL
/* A comment */
foo                                      { bar: baz; }

TPL;
        $padded = csscrush_string($sample, array('formatter' => 'padded'));
        $this->assertEquals($padded_expected, $padded);

        $block_expected = <<<TPL
/* A comment */
foo {
    bar: baz;
    }

TPL;
        $block = csscrush_string($sample, array('formatter' => 'block'));
        $this->assertEquals($block_expected, $block);
    }

    public function testSourceMaps()
    {
        csscrush_file($this->testFile, array('source_map' => true));
        $source_map_contents = file_get_contents("$this->testFile.crush.css.map");

        $this->assertRegExp('~"version": ?3,~', $source_map_contents);
    }

    public function testAdvancedMinify()
    {
        $sample = "foo { color: papayawhip; color: #cccccc;}";
        $output = csscrush_string($sample, array('minify' => array('colors')));

        $this->assertEquals('foo{color:#ffefd5;color:#ccc}', $output);
    }
}
