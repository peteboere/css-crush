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

    public function filter()
    {
        $args = func_get_args();

        $assoc_array = is_array($args[0]) ? $args[0] : false;

        if ($assoc_array) {

            $ops = array(
                '===' => function ($item) use ($assoc_array) {
                    foreach ($assoc_array as $property => $value) {
                        if (Collection::value($item, $property) !== $value) {
                            return false;
                        }
                    }
                    return true;
                },
                '!==' => function ($item) use ($assoc_array) {
                    foreach ($assoc_array as $property => $value) {
                        if (Collection::value($item, $property) === $value) {
                            return false;
                        }
                    }
                    return true;
                },
            );

            $op = isset($args[1]) ? $args[1] : '===';
            $callback = $ops[$op];
        }
        elseif (is_callable($args[0])) {
            $callback = $args[0];
        }

        if (isset($callback)) {
            $this->store = array_filter($this->store, $callback);
        }

        return $this;
    }
}
