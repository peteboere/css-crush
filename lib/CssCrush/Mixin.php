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

    public function __construct($block)
    {
        $this->template = new Template($block);
    }

    public static function call($message, $context = null)
    {
        $process = Crush::$process;
        $mixable = null;
        $message = trim($message);

        // Test for mixin or abstract rule. e.g:
        //   named-mixin( 50px, rgba(0,0,0,0), left 100% )
        //   abstract-rule
        if (preg_match(Regex::make('~^(?<name>{{ident}}) {{parens}}?~xS'), $message, $message_match)) {

            $name = $message_match['name'];

            if (isset($process->mixins[$name])) {

                $mixable = $process->mixins[$name];
            }
            elseif (isset($process->references[$name])) {

                $mixable = $process->references[$name];
            }
        }

        // If no mixin or abstract rule matched, look for matching selector
        if (! $mixable) {

            $selector_test = Selector::makeReadable($message);

            if (isset($process->references[$selector_test])) {
                $mixable = $process->references[$selector_test];
            }
        }

        // Avoid infinite recursion.
        if (! $mixable || $mixable === $context) {

            return false;
        }
        elseif ($mixable instanceof Mixin) {

            $args = array();
            $raw_args = isset($message_match['parens_content']) ? trim($message_match['parens_content']) : null;
            if ($raw_args) {
                $args = Util::splitDelimList($raw_args);
            }

            return DeclarationList::parse($mixable->template->__invoke($args), array(
                'flatten' => true,
                'context' => $mixable,
            ));
        }
        elseif ($mixable instanceof Rule) {

            return $mixable->declarations->store;
        }
    }

    public static function merge(array $input, $message_list, $options = array())
    {
        $context = isset($options['context']) ? $options['context'] : null;

        $mixables = array();
        foreach (Util::splitDelimList($message_list) as $message) {
            if ($result = self::call($message, $context)) {
                $mixables = array_merge($mixables, $result);
            }
        }

        while ($mixable = array_shift($mixables)) {
            if ($mixable instanceof Declaration) {
                $input[] = $mixable;
            }
            else {
                list($property, $value) = $mixable;
                if ($property === 'mixin') {
                    $input = Mixin::merge($input, $value, $options);
                }
                elseif (! empty($options['keyed'])) {
                    $input[$property] = $value;
                }
                else {
                    $input[] = array($property, $value);
                }
            }
        }

        return $input;
    }
}
