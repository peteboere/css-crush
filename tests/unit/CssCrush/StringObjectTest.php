<?php

namespace CssCrush\UnitTest;

use CssCrush\StringObject;

class StringObjectTest extends \PHPUnit\Framework\TestCase
{
    protected $sample = " Lorem ipsum dolor sit amet ";

    public function test__toString()
    {
        $string = new StringObject($this->sample);
        $this->assertEquals($this->sample, (string) $string);
    }

    public function testEndsWith()
    {
        $this->assertTrue(StringObject::endsWith('amet', 'et'));
    }

    public function testUpdate()
    {
        $string = new StringObject($this->sample);
        $updated_text = 'foo';
        $string->update($updated_text);
        $this->assertEquals($updated_text, (string) $string);
    }

    public function testTrim()
    {
        $string = new StringObject($this->sample);
        $this->assertEquals(trim($this->sample), (string) $string->trim());
    }

    public function testRTrim()
    {
        $string = new StringObject($this->sample);
        $this->assertEquals(rtrim($this->sample), (string) $string->rTrim());
    }

    public function testLTrim()
    {
        $string = new StringObject($this->sample);
        $this->assertEquals(ltrim($this->sample), (string) $string->lTrim());
    }

    public function testAppend()
    {
        $string = new StringObject($this->sample);
        $append_text = 'foo';
        $this->assertEquals($this->sample . $append_text, (string) $string->append($append_text));
    }

    public function testPrepend()
    {
        $string = new StringObject($this->sample);
        $prepend_text = 'foo';
        $this->assertEquals($prepend_text . $this->sample, (string) $string->prepend($prepend_text));
    }

    public function testSubstr()
    {
        $string = new StringObject($this->sample);
        $this->assertEquals(substr($this->sample, 1), (string) $string->substr(1));
    }
}

// matchAll
// replaceHash
// pregReplaceCallback
// pregReplaceHash
// splice
