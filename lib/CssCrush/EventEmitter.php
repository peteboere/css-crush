<?php
/**
 *
 * Event Emitter trait.
 *
 */
namespace CssCrush;

trait EventEmitter {

    private $eventEmitterStorage = [];
    private $eventEmitterUid = 0;

    public function on($event, callable $function)
    {
        if (! isset($this->eventEmitterStorage[$event])) {
            $this->eventEmitterStorage[$event] = [];
        }

        $id = ++$this->eventEmitterUid;
        $this->eventEmitterStorage[$event][$id] = $function;

        return function () use ($event, $id) {
            unset($this->eventEmitterStorage[$event][$id]);
        };
    }

    public function emit($event, $data = null)
    {
        if (isset($this->eventEmitterStorage[$event])) {
            foreach ($this->eventEmitterStorage[$event] as $function) {
                $function($data);
            }
        }
    }
}
