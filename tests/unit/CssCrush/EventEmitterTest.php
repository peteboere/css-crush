<?php

namespace CssCrush\UnitTest;

use CssCrush\EventEmitter;

class EventEmitterTest extends \PHPUnit_Framework_TestCase
{
    public function testAll()
    {
        $emitter = new EventEmitterHost();

        $foo = null;
        $cancelEvent = $emitter->on('foo', function ($data) use (&$foo) {
            $foo = $data;
        });

        $this->assertEquals($foo, null);

        $emitter->emit('foo', 10);

        $this->assertEquals($foo, 10);

        $cancelEvent();

        $emitter->emit('foo', 20);

        $this->assertEquals($foo, 10);
    }
}

class EventEmitterHost { use EventEmitter; }
