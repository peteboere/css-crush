<?php

namespace CssCrush\UnitTest;

use CssCrush\Selector;

class SelectorTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->process = bootstrap_process();
    }

    public function testMakeReadable()
    {
        $sample = '#foo+bar [data="baz"]~ p:first-child .foo  >bar::after';
        $sample = $this->process->tokens->capture($sample, 's');

        $this->assertEquals('#foo + bar [data="baz"] ~ p:first-child .foo > bar::after',
            Selector::makeReadable($sample));
    }

    public function testNormalizeWhiteSpace()
    {
        $sample = "#foo+bar [data=baz ]~ p:first-child .foo\n\n\t  >bar::after";

        $this->assertEquals('#foo + bar [data=baz] ~ p:first-child .foo > bar::after',
            Selector::normalizeWhiteSpace($sample));
    }

    public function testAppendPseudo()
    {
        $test = new Selector('.foo');
        $test->appendPseudo(':hover');

        $this->assertEquals('.foo:hover', $test->__toString());
    }

    public function testToString()
    {
        $this->process->minifyOutput = true;
        $test = new Selector('.foo > .bar + .baz');

        $this->assertEquals('.foo>.bar+.baz', $test->__toString());
    }
}
