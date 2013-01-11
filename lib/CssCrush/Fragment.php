<?php
/**
 *
 *  Fragment objects.
 *
 */
class CssCrush_Fragment
{
    public $template = array();

    public $arguments;

    public function __construct ( $block )
    {
        // Prepare the arguments object
        $this->arguments = new CssCrush_ArgList( $block );

        // Re-assign with the parsed arguments string
        $this->template = $this->arguments->string;
    }

    public function call ( array $args )
    {
        // Copy the template
        $template = $this->template;

        if ( count( $this->arguments ) ) {

            list( $find, $replace ) = $this->arguments->getSubstitutions( $args );
            $template = str_replace( $find, $replace, $template );
        }

        // Return fragment css
        return $template;
    }
}
