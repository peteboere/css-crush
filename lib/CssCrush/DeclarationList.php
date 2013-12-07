<?php
/**
 *
 * Declaration lists.
 *
 */
namespace CssCrush;

class DeclarationList extends Iterator
{
    public $properties = array();
    public $canonicalProperties = array();

    public function __construct()
    {
        parent::__construct();
    }

    public function add($property, $value, $contextIndex = 0)
    {
        $declaration = new Declaration($property, $value, $contextIndex);

        if (empty($declaration->inValid)) {

            $this->index($declaration);
            $this->store[] = $declaration;
            return $declaration;
        }

        return false;
    }

    public function reset(array $declaration_stack)
    {
        $this->store = $declaration_stack;

        $this->updateIndex();
    }

    public function index($declaration)
    {
        $property = $declaration->property;

        if (isset($this->properties[$property])) {
            $this->properties[$property]++;
        }
        else {
            $this->properties[$property] = 1;
        }
        $this->canonicalProperties[$declaration->canonicalProperty] = true;
    }

    public function updateIndex()
    {
        $this->properties = array();
        $this->canonicalProperties = array();

        foreach ($this->store as $declaration) {
            $this->index($declaration);
        }
    }

    public function propertyCount($property)
    {
        return isset($this->properties[$property]) ? $this->properties[$property] : 0;
    }

    public function join($glue = ';')
    {
        return implode($glue, $this->store);
    }
}
