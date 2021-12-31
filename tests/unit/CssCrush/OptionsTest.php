<?php

namespace CssCrush\UnitTest;

use CssCrush\Options;
use CssCrush\Version;

class OptionsTest extends \PHPUnit\Framework\TestCase
{
    public $testFile;

    public function setUp(): void
    {
        bootstrap_process();
        $this->testFile = temp_file("\n foo {bar: baz;} \n\n baz {bar: foo;}");
    }

    public function testDefaults()
    {
        $options = new Options();
        $standardOptions = Options::filter();

        $this->assertEquals($standardOptions, $options->get());

        $testOptions = ['plugins' => ['foo', 'bar'], 'minify' => false];
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

        $result = csscrush_string('foo { bar: baz; }', [
            'boilerplate' => temp_file($boilerplate),
            'newlines' => 'unix',
        ]);

        $this->assertStringContainsStringIgnoringCase(' * ' . Version::detect(), (string) $result);
        $this->assertStringContainsStringIgnoringCase(" * Line breaks\n * preserved\n *", (string) $result);
    }

    public function testFormatters()
    {
        $sample = '/* A comment */ foo {bar: baz;}';

        $single_line_expected = <<<TPL
/* A comment */
foo { bar: baz; }

TPL;
        $single_line = csscrush_string($sample, ['formatter' => 'single-line']);
        $this->assertEquals($single_line_expected, $single_line);

        $padded_expected = <<<TPL
/* A comment */
foo                                      { bar: baz; }

TPL;
        $padded = csscrush_string($sample, ['formatter' => 'padded']);
        $this->assertEquals($padded_expected, $padded);

        $block_expected = <<<TPL
/* A comment */
foo {
    bar: baz;
    }

TPL;
        $block = csscrush_string($sample, ['formatter' => 'block']);
        $this->assertEquals($block_expected, $block);
    }

    public function testSourceMaps()
    {
        csscrush_file($this->testFile, ['source_map' => true]);
        $source_map_contents = file_get_contents("$this->testFile.crush.css.map");

        $this->assertRegExp('~"version": ?3,~', $source_map_contents);
    }

    public function testAdvancedMinify()
    {
        $sample = "foo { color: papayawhip; color: #cccccc;}";
        $output = csscrush_string($sample, ['minify' => ['colors']]);

        $this->assertEquals('foo{color:#ffefd5;color:#ccc}', $output);
    }
}
