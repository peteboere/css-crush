<?php

namespace CssCrush\UnitTest;

use CssCrush\Tokens;

class TokensTest extends \PHPUnit_Framework_TestCase
{
    protected $tokens;

    public function setUp()
    {
        $this->process = bootstrap_process(array('minify' => false));

        $this->tokens = $this->process->tokens;
        $this->tokens->add('"foo"', 's');
        $this->tokens->add('"bar"', 's');
        $this->tokens->add('"baz"', 's');
    }

    public function test__construct()
    {
        $this->assertEmpty(
            array_diff_key(
                array_flip(array('s', 'c', 'r', 'u', 't')),
                (array) $this->tokens->store
            )
        );
    }

    public function testCreateLabel()
    {
        $type = 's';
        $this->assertRegExp("~^\?{$type}[a-z0-9]+\?$~", $this->tokens->createLabel($type));
    }

    public function testAdd()
    {
        $this->tokens->add('/*monkey*/', 'c');
        $this->assertContains('/*monkey*/', array_values($this->tokens->store->c));
    }

    public function testGet()
    {
        $value = reset($this->tokens->store->s);
        $key = key($this->tokens->store->s);
        $this->assertEquals($value, $this->tokens->get($key));
    }

    public function testRelease()
    {
        $label = $this->tokens->add('"foo"', 's');
        $this->assertTrue(isset($this->tokens->store->s[$label]));

        $this->tokens->pop($label);
        $this->assertFalse(isset($this->tokens->store->s[$label]));
    }

    public function testPop()
    {
        $label = $this->tokens->add('"foo"', 's');

        $this->assertEquals('"foo"', $this->tokens->pop($label));
        $this->assertFalse(isset($this->tokens->store->s[$label]));
    }

    public function testCapture()
    {
        $sample = '[class="foo"] {bar: url(baz.png);}';

        $sample = $this->tokens->capture($sample, 'u');
        $this->assertContains('?u', $sample);

        $sample = $this->tokens->capture($sample, 's');
        $this->assertContains('?s', $sample);
    }

    public function testCaptureUrls()
    {
        $sample = '[class="foo"] {bar: url(baz.png);}';

        $sample = $this->tokens->captureUrls($sample);
        $this->assertContains('?u', $sample);
    }

    public function testRestore()
    {
        $sample = '[class="foo"] {bar: url(baz.png);}';

        $modified = $this->tokens->captureUrls($sample);
        $this->assertContains('?u', $modified);

        $modified = $this->tokens->restore($modified, 'u');
        $this->assertEquals($sample, $modified);

        $modified = $this->tokens->capture($sample, 's');
        $this->assertContains('?s', $modified);

        $modified = $this->tokens->restore($modified, 's');
        $this->assertEquals($sample, $modified);
    }

    public function testPad()
    {
        $label = $this->tokens->createLabel('s');
        $padded_label = Tokens::pad($label, "\n lorem \n ipsum \n123");
        $this->assertEquals("$label\n\n\n   ", $padded_label);
    }

    public function testIs()
    {
        $this->assertTrue(Tokens::is($this->tokens->createLabel('s'), 's'));
    }
}
