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

    public function testMatchAll()
    {
        $expected = [
            [['foo', 0]],
            [['foo', 12]],
        ];
        $matches = Regex::matchAll('~foo~', 'foo bar baz foo bar baz');

        $this->assertEquals($expected, $matches);
    }
}
