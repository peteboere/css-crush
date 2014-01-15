<?php

namespace CssCrush\UnitTest;

use CssCrush\Hooks;

class HookTest extends \PHPUnit_Framework_TestCase
{
    public function testAll()
    {
        $hooks = new Hooks();

        $dummy_hook = __NAMESPACE__ . '\dummy_hook';

        $hooks->add('foo', $dummy_hook);
        $hooks->add('foo', 'strtoupper');

        $this->assertEquals(array('foo' => array($dummy_hook=>true, 'strtoupper'=>true)), $hooks->get());

        $hooks->remove('foo', 'strtoupper');

        $this->assertEquals(array('foo' => array($dummy_hook=>true)), $hooks->get());

        $hooks->run('foo', $this);

        $this->assertTrue($this->hookRan);
    }
}

function dummy_hook(HookTest $test)
{
    $test->hookRan = true;
}
