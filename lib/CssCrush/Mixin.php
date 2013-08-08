<?php
/**
 *
 *  Mixin objects.
 *
 */
namespace CssCrush;

class Mixin
{
    public $template;

    public function __construct ($block)
    {
        $this->template = new Template($block);
    }

    public function call ( array $args )
    {
        return Rule::parseBlock($this->template->apply($args));
    }

    static public function parseSingleValue ($message)
    {
        $message = ltrim($message);
        $mixin = null;
        $non_mixin = null;

        // e.g.
        //   - named-mixin( 50px, rgba(0,0,0,0), left 100% )
        //   - abstract-rule
        //   - #foo

        // Test for leading name
        if (preg_match('~^[\w-]+~', $message, $name_match)) {

            $name = $name_match[0];

            if (isset(CssCrush::$process->mixins[$name])) {

                // Mixin match
                $mixin = CssCrush::$process->mixins[$name];
            }
            elseif (isset(CssCrush::$process->references[$name])) {

                // Abstract rule match
                $non_mixin = CssCrush::$process->references[$name];
            }
        }

        // If no mixin or abstract rule matched, look for matching selector
        if (! $mixin && ! $non_mixin) {

            $selector_test = Selector::makeReadable($message);

            if (isset(CssCrush::$process->references[$selector_test])) {
                $non_mixin = CssCrush::$process->references[$selector_test];
            }
        }

        // If no mixin matched, but matched alternative, use alternative
        if (! $mixin) {

            if ($non_mixin) {

                // Return expected format
                $result = array();
                foreach ($non_mixin as $declaration) {
                    $result[] = array(
                        $declaration->property,
                        $declaration->value,
                    );
                }

                return $result;
            }

            // Nothing matches
            return false;
        }

        // We have a valid mixin.
        // Discard the name part and any wrapping parens and whitespace
        $message = substr($message, strlen($name));
        $message = preg_replace('~^\s*\(?\s*|\s*\)?\s*$~', '', $message);

        // e.g. "value, rgba(0,0,0,0), left 100%"

        // Determine what raw arguments there are to pass to the mixin
        $args = array();
        if ($message !== '') {
            $args = Util::splitDelimList($message);
        }

        return $mixin->call($args);
    }

    static public function parseValue ($message)
    {
        // Call the mixin and return the list of declarations
        $declarations = array();

        foreach (Util::splitDelimList($message) as $item) {

            if ($result = self::parseSingleValue($item)) {
                $declarations = array_merge($declarations, $result);
            }
        }

        return $declarations;
    }
}
