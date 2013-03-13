<?php
/**
 *
 *  Mixin objects.
 *
 */
class CssCrush_Mixin
{
    public $declarationsTemplate = array();

    public $template;

    public function __construct ($block)
    {
        $this->template = new CssCrush_Template($block);

        // Parse into mixin template.
        foreach (CssCrush_Util::parseBlock($this->template->string) as $pair) {

            list($property, $value) = $pair;
            $property = strtolower($property);

            if ($property === 'mixin') {

                // Mixin can contain other mixins if they are available.
                if ($mixin_declarations = CssCrush_Mixin::parseValue($value)) {

                    // Add mixin result to the stack.
                    $this->declarationsTemplate = array_merge(
                        $this->declarationsTemplate, $mixin_declarations);
                }
            }
            elseif ($value !== '') {

                // Store template declarations as arrays as they are copied by
                // value not reference.
                $this->declarationsTemplate[] = array(
                    'property' => $property,
                    'value' => $value,
                );
            }
        }
    }

    public function call ( array $args )
    {
        // Copy the template.
        $declarations = $this->declarationsTemplate;

        if (count($this->template)) {

            $this->template->prepare($args);

            // Place the arguments.
            foreach ($declarations as &$declaration) {
                $declaration['value'] = $this->template->apply(null, $declaration['value']);
            }
        }

        // Return mixin declarations.
        return $declarations;
    }

    static public function parseSingleValue ($message)
    {
        $message = ltrim($message);
        $mixin = null;
        $non_mixin = null;

        // e.g.
        //   - mymixin( 50px, rgba(0,0,0,0), left 100% )
        //   - abstract-rule
        //   - #selector

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

            $selector_test = CssCrush_Selector::makeReadable($message);

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
                        'property' => $declaration->property,
                        'value'    => $declaration->value,
                    );
                }

                return $result;
            }

            // Nothing matches
            else {

                return false;
            }
        }

        // We have a valid mixin.
        // Discard the name part and any wrapping parens and whitespace
        $message = substr($message, strlen($name));
        $message = preg_replace('~^\s*\(?\s*|\s*\)?\s*$~', '', $message);

        // e.g. "value, rgba(0,0,0,0), left 100%"

        // Determine what raw arguments there are to pass to the mixin
        $args = array();
        if ($message !== '') {
            $args = CssCrush_Util::splitDelimList($message);
        }

        return $mixin->call($args);
    }

    static public function parseValue ($message)
    {
        // Call the mixin and return the list of declarations
        $declarations = array();

        foreach (CssCrush_Util::splitDelimList($message) as $item) {

            if ($result = self::parseSingleValue($item)) {
                $declarations = array_merge($declarations, $result);
            }
        }

        return $declarations;
    }
}
