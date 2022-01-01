<?php
/**
 *
 * Base iterator for Declaration and Selector lists.
 *
 */
namespace CssCrush;

class Iterator implements \IteratorAggregate, \ArrayAccess, \Countable
{
    public $store;

    public function __construct($items = [])
    {
        $this->store = $items;
    }

    /*
        IteratorAggregate implementation.
    */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->store);
    }

    /*
        ArrayAccess implementation.
    */
    public function offsetExists($index): bool
    {
        return array_key_exists($index, $this->store);
    }

    public function offsetGet($index): mixed
    {
        return isset($this->store[$index]) ? $this->store[$index] : null;
    }

    public function offsetSet($index, $value): void
    {
        $this->store[$index] = $value;
    }

    public function offsetUnset($index): void
    {
        unset($this->store[$index]);
    }

    public function getContents()
    {
        return $this->store;
    }

    /*
        Countable implementation.
    */
    public function count(): int
    {
        return count($this->store);
    }

    /*
        Collection interface.
    */
    public function filter($filterer, $op = '===')
    {
        $collection = new Collection($this->store);
        return $collection->filter($filterer, $op);
    }
}
