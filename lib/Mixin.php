<?php
/**
 *
 *  Mixin objects.
 *
 */
class CssCrush_Mixin
{
    public $declarationsTemplate = array();

    public $arguments;

    public $data = array();

    public function __construct ( $block )
    {
        // Strip comment markers
        $block = CssCrush_Util::stripCommentTokens( $block );

        // Prepare the arguments object
        $this->arguments = new CssCrush_ArgList( $block );

        // Re-assign with the parsed arguments string
        $block = $this->arguments->string;

        // Split the block around semi-colons.
        $declarations = preg_split( '!\s*;\s*!', trim( $block ), null, PREG_SPLIT_NO_EMPTY );

        foreach ( $declarations as $raw_declaration ) {

            $colon = strpos( $raw_declaration, ':' );
            if ( $colon === -1 ) {
                continue;
            }

            // Store template declarations as arrays as they are copied by value not reference
            $declaration = array();

            $declaration['property'] = trim( substr( $raw_declaration, 0, $colon ) );
            $declaration['value'] = trim( substr( $raw_declaration, $colon + 1 ) );

            if ( $declaration['property'] === 'mixin' ) {

                // Mixin can contain other mixins if they are available
                if ( $mixin_declarations = CssCrush_Mixin::parseValue( $declaration['value'] ) ) {

                    // Add mixin result to the stack
                    $this->declarationsTemplate = array_merge( $this->declarationsTemplate, $mixin_declarations );
                }
            }
            elseif ( ! empty( $declaration['value'] ) ) {
                $this->declarationsTemplate[] = $declaration;
            }
        }

        // Create data table for the mixin.
        // Values that use arg() are excluded
        foreach ( $this->declarationsTemplate as &$declaration ) {
            if ( ! preg_match( CssCrush_Regex::$patt->aToken, $declaration['value'] ) ) {
                $this->data[ $declaration['property'] ] = $declaration['value'];
            }
        }
        return '';
    }

    public function call ( array $args )
    {
        // Copy the template
        $declarations = $this->declarationsTemplate;

        if ( count( $this->arguments ) ) {

            list( $find, $replace ) = $this->arguments->getSubstitutions( $args );

            // Place the arguments
            foreach ( $declarations as &$declaration ) {
                $declaration['value'] = str_replace( $find, $replace, $declaration['value'] );
            }
        }

        // Return mixin declarations
        return $declarations;
    }

    static public function parseSingleValue ( $message )
    {
        $message = ltrim( $message );
        $mixin = null;
        $non_mixin = null;

        // e.g.
        //   - mymixin( 50px, rgba(0,0,0,0), left 100% )
        //   - abstract-rule
        //   - #selector

        // Test for leading name
        if ( preg_match( '!^[\w-]+!', $message, $name_match ) ) {

            $name = $name_match[0];

            if ( isset( CssCrush::$process->mixins[ $name ] ) ) {

                // Mixin match
                $mixin = CssCrush::$process->mixins[ $name ];
            }
            elseif ( isset( CssCrush::$process->abstracts[ $name ] ) ) {

                // Abstract rule match
                $non_mixin = CssCrush::$process->abstracts[ $name ];
            }
        }

        // If no mixin or abstract rule matched, look for matching selector
        if ( ! $mixin && ! $non_mixin ) {

            $selector_test = CssCrush_Selector::makeReadableSelector( $message );

            if ( isset( CssCrush::$process->selectorRelationships[ $selector_test ] ) ) {
                $non_mixin = CssCrush::$process->selectorRelationships[ $selector_test ];
            }
        }

        // If no mixin matched, but matched alternative, use alternative
        if ( ! $mixin ) {

            if ( $non_mixin ) {

                // Return expected format
                $result = array();
                foreach ( $non_mixin as $declaration ) {
                    $result[] = array(
                        'property' => $declaration->property,
                        'value'    => $declaration->value,
                    );
                }
                return $result;
            }
            else {

                // Nothing matches
                return false;
            }
        }

        // We have a valid mixin.
        // Discard the name part and any wrapping parens and whitespace
        $message = substr( $message, strlen( $name ) );
        $message = preg_replace( '!^\s*\(?\s*|\s*\)?\s*$!', '', $message );

        // e.g. "value, rgba(0,0,0,0), left 100%"

        // Determine what raw arguments there are to pass to the mixin
        $args = array();
        if ( $message !== '' ) {
            $args = CssCrush_Util::splitDelimList( $message );
        }

        return $mixin->call( $args );
    }

    static public function parseValue ( $message )
    {
        // Call the mixin and return the list of declarations
        $declarations = array();

        foreach ( CssCrush_Util::splitDelimList( $message ) as $item ) {

            if ( $result = self::parseSingleValue( $item ) ) {

                $declarations = array_merge( $declarations, $result );
            }
        }
        return $declarations;
    }
}
