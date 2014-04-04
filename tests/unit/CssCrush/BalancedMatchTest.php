<?php

namespace CssCrush\UnitTest;

use CssCrush\BalancedMatch;

class BalancedMatchTest extends \PHPUnit_Framework_TestCase
{
    public $process;

    public function setUp()
    {
        $this->process = bootstrap_process();
        $sample = '@foo; @bar {color: orange;} @baz';

        $this->process->string = new \CssCrush\StringObject($sample);
    }

    public function testMatch()
    {
        $matches = $this->process->string->matchAll('~@bar~');
        $match_offset = $matches[0][0][1];

        $match = new BalancedMatch($this->process->string, $match_offset);

        $this->assertEquals('color: orange;', $match->inside());
        $this->assertEquals('@bar {color: orange;}', $match->whole());

        $match = new BalancedMatch(clone $this->process->string, $match_offset);
        $match->unWrap();
        $this->assertEquals('@foo; color: orange; @baz', $match->string->__toString());

        $match = new BalancedMatch(clone $this->process->string, $match_offset);
        $match->replace('@boo;');
        $this->assertEquals('@foo; @boo; @baz', $match->string->__toString());
    }
}
