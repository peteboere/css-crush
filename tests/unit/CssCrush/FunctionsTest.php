<?php

namespace CssCrush\UnitTest;

use CssCrush\Functions;

class FunctionsTest extends \PHPUnit_Framework_TestCase
{
    public function testMakePattern()
    {
        $patt = Functions::makePattern(array('foo', 'bar'));
        $this->assertEquals('~(?<function>(?<![\w-])(?:foo|bar))\(~iS', $patt);

        $patt = Functions::makePattern(array('foo', 'bar', '#'));
        $this->assertEquals('~(?<function>(?:#|(?<![\w-])(?:foo|bar)))\(~iS', $patt);

        $patt = Functions::makePattern(array('$', '#'));
        $this->assertEquals('~(?<function>\$|#)\(~iS', $patt);
    }
}
