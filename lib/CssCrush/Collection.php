<?php
/*

*/
namespace CssCrush;

class Collection extends Iterator
{
    public $store;

    public function __construct(array $store)
    {
        $this->store = $store;
    }

    public function get($index = null)
    {
        return is_int($index) ? $this->store[$index] : $this->store;
    }

    static public function value($item, $property)
    {
        if (strpos($property, '|') !== false) {
            $filters = explode('|', $property);
            $property = array_shift($filters);
            $value = $item->$property;
            foreach ($filters as $filter) {
                switch ($filter) {
                    case 'lower':
                        $value = strtolower($value);
                        break;
                }
            }
            return $value;
        }
        return $item->$property;
    }

    public function filter($filterer, $op = '===')
    {
        if (is_array($filterer)) {

            $ops = array(
                '===' => function ($item) use ($filterer) {
                    foreach ($filterer as $property => $value) {
                        if (Collection::value($item, $property) !== $value) {
                            return false;
                        }
                    }
                    return true;
                },
                '!==' => function ($item) use ($filterer) {
                    foreach ($filterer as $property => $value) {
                        if (Collection::value($item, $property) === $value) {
                            return false;
                        }
                    }
                    return true;
                },
            );

            $callback = $ops[$op];
        }
        elseif (is_callable($filterer)) {
            $callback = $filterer;
        }

        if (isset($callback)) {
            $this->store = array_filter($this->store, $callback);
        }

        return $this;
    }
}
