<?php

namespace CssCrush\UnitTest;

use CssCrush\Regex;

class RegexTest extends \PHPUnit_Framework_TestCase
{
    public function testMake()
    {
        $this->assertEquals('~(?<parens>\(\s*(?<parens_content>(?:(?>[^()]+)|(?&parens))*)\))~S',
            Regex::make('~{{ parens }}~S'));

        $this->assertEquals('~ #[[:xdigit:]]{3} ~xS', Regex::make('~ #{{hex}}{3} ~xS'));
    }

    public function testMakeFunctionPatt()
    {
        $patt = Regex::makeFunctionPatt(array('foo', 'bar'));
        $this->assertEquals('~((?<![\w-])(?:foo|bar))\(~iS', $patt);

        $patt = Regex::makeFunctionPatt(array('foo', 'bar'), array('bare_paren' => true));
        $this->assertEquals('~((?<![\w-])(?:foo|bar|\-)?)\(~iS', $patt);

        $patt = Regex::makeFunctionPatt(array('foo', 'bar'), array('templating' => true));
        $this->assertEquals('~(#|(?<![\w-])(?:foo|bar))\(~iS', $patt);
    }

    public function testMatchAll()
    {
        $expected = array(
            array(array('foo', 0)),
            array(array('foo', 12)),
        );
        $matches = Regex::matchAll('~foo~', 'foo bar baz foo bar baz');

        $this->assertEquals($expected, $matches);
    }
}
