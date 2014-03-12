<?php
/**
 *
 * Selector aliases.
 *
 */
namespace CssCrush;

class SelectorAlias
{
    protected $type;
    protected $handler;

    public function __construct($handler, $type = 'alias')
    {
        $this->handler = $handler;
        $this->type = $type;

        switch ($this->type) {
            case 'alias':
                $this->handler = new Template($handler);
                break;
        }
    }

    public function __invoke($args)
    {
        $handler = $this->handler;
        $tokens = Crush::$process->tokens;

        $splat_arg_patt = Regex::make('~#\((?<fallback>{{ ident }})?\)~');

        switch ($this->type) {
            case 'alias':
                return $handler($args);
            case 'callback':
                $template = new Template($handler($args));
                return $template($args);
            case 'splat':
                $handler = $tokens->restore($handler, 's');
                if ($args) {
                    $list = array();
                    foreach ($args as $arg) {
                        $list[] = SelectorAlias::wrap(
                            $tokens->capture(preg_replace($splat_arg_patt, $arg, $handler), 's')
                        );
                    }
                    $handler = implode(',', $list);
                }
                else {
                    $handler = $tokens->capture(preg_replace_callback($splat_arg_patt, function ($m) {
                        return $m['fallback'];
                    }, $handler), 's');
                }
                return SelectorAlias::wrap($handler);
        }
    }

    public static function wrap($str)
    {
        return strpos($str, ',') !== false ? ":any($str)" : $str;
    }
}
