<?php
/**
 *
 *  Fragments.
 *
 */
namespace CssCrush;

class Fragment extends Template
{
    public $name;

    public function __construct($str, $options = [])
    {
        parent::__construct($str, $options);
        $this->name = $options['name'];
    }

    public function __invoke(array $args = null, $str = null)
    {
        $str = parent::__invoke($args);

        // Flatten all fragment calls within the template string.
        while (preg_match(Regex::$patt->fragmentInvoke, $str, $m, PREG_OFFSET_CAPTURE)) {

            $name = strtolower($m['name'][0]);
            $fragment = isset(Crush::$process->fragments[$name]) ? Crush::$process->fragments[$name] : null;

            $replacement = '';
            $start = $m[0][1];
            $length = strlen($m[0][0]);

            // Skip over same named fragments to avoid infinite recursion.
            if ($fragment && $name !== $this->name) {
                $args = [];
                if (isset($m['parens'][1])) {
                    $args = Functions::parseArgs($m['parens_content'][0]);
                }
                $replacement = $fragment($args);
            }
            $str = substr_replace($str, $replacement, $start, $length);
        }

        return $str;
    }
}
