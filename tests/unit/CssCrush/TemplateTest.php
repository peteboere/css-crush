<?php

namespace CssCrush\UnitTest;

use CssCrush\Template;

class TemplateTest extends \PHPUnit\Framework\TestCase
{
    protected $template;
    protected $template_raw;
    protected $template_string;

    public function setUp(): void
    {
        bootstrap_process();

        $this->template_raw = <<<TPL
foo: #(0 100%);
bar: #(0);
baz: #(1);
TPL;
        $this->template_string = <<<TPL
foo: ?a0?;
bar: ?a0?;
baz: ?a1?;
TPL;
        $this->template = new Template($this->template_raw);
    }

    public function test__construct()
    {
        $this->assertEquals($this->template_string, $this->template->string);
    }

    public function testGetArgValue()
    {
        $args = ['default'];
        $this->assertEquals('100%', $this->template->getArgValue(0, $args));

        $args = ['foo', 'bar'];
        $this->assertEquals('bar', $this->template->getArgValue(1, $args));
    }

    public function testPrepare()
    {
        $this->template->prepare(['one', 'two']);
        $this->assertEquals(
            [['?a0?', '?a1?'], ['one', 'two']],
            $this->template->substitutions);
    }

    public function testApply()
    {
        $actual = $this->template->__invoke(['one', 'two']);
        $expected = <<<TPL
foo: one;
bar: one;
baz: two;
TPL;
        $this->assertEquals($expected, $actual);

        $actual = $this->template->__invoke(['default', 'colanut']);
        $expected = <<<TPL
foo: 100%;
bar: 100%;
baz: colanut;
TPL;
        $this->assertEquals($expected, $actual);
    }

    public function testTokenize()
    {
        $original_sample = <<<TPL
[foo="bar"] {baz: url(image.png);}
TPL;
        $sample = Template::tokenize($original_sample);
        $this->assertStringContainsStringIgnoringCase('[foo=?s', $sample);
        $this->assertStringContainsStringIgnoringCase('{baz: ?u', $sample);

        $sample = Template::unTokenize($sample);
        $this->assertEquals($original_sample, $sample);
    }
}
