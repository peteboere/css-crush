<?php
/**
 *
 * Extend argument objects.
 *
 */
namespace CssCrush;

class ExtendArg
{
    public $pointer;
    public $name;
    public $raw;
    public $pseudo;

    public function __construct($name)
    {
        $this->name =
        $this->raw = $name;

        if (! preg_match(Regex::$patt->rooted_ident, $this->name)) {

            // Not a regular name: Some kind of selector so normalize it for later comparison.
            $this->name =
            $this->raw = Selector::makeReadable($this->name);

            // If applying the pseudo on output store.
            if (substr($this->name, -1) === '!') {

                $this->name = rtrim($this->name, ' !');
                if (preg_match('~\:\:?[\w-]+$~', $this->name, $m)) {
                    $this->pseudo = $m[0];
                }
            }
        }
    }
}
