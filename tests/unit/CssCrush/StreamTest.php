<?php

namespace CssCrush\UnitTest;

use CssCrush\Stream;

class StreamTest extends \PHPUnit_Framework_TestCase
{
    protected $sample = " Lorem ipsum dolor sit amet ";

    public function test__toString()
    {
        $stream = new Stream($this->sample);
        $this->assertEquals($this->sample, (string) $stream);
    }

    public function testEndsWith()
    {
        $this->assertTrue(Stream::endsWith('amet', 'et'));
    }

    public function testUpdate()
    {
        $stream = new Stream($this->sample);
        $updated_text = 'foo';
        $stream->update($updated_text);
        $this->assertEquals($updated_text, (string) $stream);
    }

    public function testTrim()
    {
        $stream = new Stream($this->sample);
        $this->assertEquals(trim($this->sample), (string) $stream->trim());
    }

    public function testRTrim()
    {
        $stream = new Stream($this->sample);
        $this->assertEquals(rtrim($this->sample), (string) $stream->rTrim());
    }

    public function testLTrim()
    {
        $stream = new Stream($this->sample);
        $this->assertEquals(ltrim($this->sample), (string) $stream->lTrim());
    }

    public function testAppend()
    {
        $stream = new Stream($this->sample);
        $append_text = 'foo';
        $this->assertEquals($this->sample . $append_text, (string) $stream->append($append_text));
    }

    public function testPrepend()
    {
        $stream = new Stream($this->sample);
        $prepend_text = 'foo';
        $this->assertEquals($prepend_text . $this->sample, (string) $stream->prepend($prepend_text));
    }

    public function testSubstr()
    {
        $stream = new Stream($this->sample);
        $prepend_text = 'foo';
        $this->assertEquals(substr($this->sample, 1), (string) $stream->substr(1));
    }
}

// matchAll
// replaceHash
// pregReplaceCallback
// pregReplaceHash
// splice
