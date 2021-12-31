<?php

namespace CssCrush\UnitTest;

use CssCrush\Functions;

class FunctionsTest extends \PHPUnit\Framework\TestCase
{
    public function testMakePattern()
    {
        $patt = Functions::makePattern(['foo', 'bar']);
        $this->assertEquals('~(?<![\w-])-?(?<function>foo|bar)\(~iS', $patt);

        $hashChar = version_compare(PHP_VERSION, '7.3.0') >= 0
            ? '\\#'
            : '#';

        $patt = Functions::makePattern(['foo', 'bar', '#']);
        $this->assertEquals('~(?:(?<![\w-])-?(?<function>foo|bar)|(?<simple_function>' . $hashChar . '))\(~iS', $patt);

        $patt = Functions::makePattern(['$', '#']);
        $this->assertEquals('~(?<simple_function>\$|' . $hashChar . ')\(~iS', $patt);
    }
}
