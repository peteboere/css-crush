<?php
/**
 *
 * Selector aliases.
 *
 */
namespace CssCrush;

class SelectorAlias
{
    public $type;
    public $handler;

    public function __construct($type, $handler)
    {
        $this->type = $type;

        switch ($this->type) {
            case 'alias':
                $this->handler = new Template($handler);
                break;
            case 'callback':
            case 'splat':
                $this->handler = $handler;
                break;
        }
    }

    public function __invoke($args)
    {
        $handler = $this->handler;

        switch ($this->type) {
            case 'callback':
                $template = new Template($handler($args));
                return $template($args);
            case 'alias':
            default:
                return $handler($args);
        }
    }
}
