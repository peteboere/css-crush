<?php
/**
 *
 * Selector lists.
 *
 */
namespace CssCrush;

class SelectorList extends Iterator
{
    public $store;

    public function __construct()
    {
        parent::__construct();
    }

    public function add(Selector $selector)
    {
        $this->store[$selector->readableValue] = $selector;
    }

    public function join($glue = ',')
    {
        return implode($glue, $this->store);
    }
}
