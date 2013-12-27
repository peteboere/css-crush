<?php

namespace CssCrush\UnitTest;

use CssCrush\Hook;

class HookTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        Hook::$register = array();
        $this->dummy_hook = __NAMESPACE__ . '\dummy_hook';
    }

    public function tearDown()
    {
        Hook::$register = array();
    }

    public function testAdd()
    {
        Hook::add('foo', $this->dummy_hook);
        Hook::add('foo', 'strtoupper');

        $this->assertEquals(array('foo' => array($this->dummy_hook=>true, 'strtoupper'=>true)), Hook::$register);

        return Hook::$register;
    }

    /**
     * @depends testAdd
     */
    public function testRemove($register)
    {
        Hook::$register = $register;

        Hook::remove('foo', 'strtoupper');

        $this->assertEquals(array('foo' => array($this->dummy_hook=>true)), Hook::$register);

        return Hook::$register;
    }

    /**
     * @depends testRemove
     */
    public function testRun($register)
    {
        Hook::$register = $register;

        Hook::run('foo', $this);

        $this->assertTrue($this->hookRan);

        return Hook::$register;
    }

    /**
     * @depends testRun
     */
    public function testReset($register)
    {
        Hook::$register = $register;

        Hook::reset();

        $this->assertEquals(array(), Hook::$register);
    }
}

function dummy_hook(HookTest $test)
{
    $test->hookRan = true;
}
