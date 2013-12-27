<?php

namespace CssCrush\UnitTest;

use CssCrush\Crush;
use CssCrush\Declaration;
use CssCrush\Rule;

class DeclarationTest extends \PHPUnit_Framework_TestCase
{
    protected $process;
    protected $rule;
    protected $declaration;

    public function setUp()
    {
        $this->process = bootstrap_process(array('minify' => false));
        $this->rule = new Rule('.foo', '-fOo-BAR: (10 + 10)px !important');
        $this->declaration = new Declaration('-fOo-BAR', 'baz !important');
    }

    public function test__construct()
    {
        $this->assertTrue($this->declaration->important);
        $this->assertTrue($this->declaration->valid);

        $this->assertEquals('bar', $this->declaration->canonicalProperty);
        $this->assertEquals('-foo-bar', $this->declaration->property);
        $this->assertEquals('foo', $this->declaration->vendor);
        $this->assertEquals('baz', $this->declaration->value);
    }

    public function test__toString()
    {
        $this->assertEquals('-foo-bar: baz !important', (string) $this->declaration);
    }

    public function testProcess()
    {
        foreach ($this->rule->declarations as $index => $declaration) {
            $declaration->process($this->rule);
            $this->assertEquals('20px', $declaration->value);
        }
    }

    public function testIndexFunctions()
    {
        $declaration = new Declaration('color', 'rgba(0,0,0,.5), calc(100px)');
        $declaration->indexFunctions();
        $this->assertEquals(array('rgba' => true, 'calc' => true), $declaration->functions);
    }
}
