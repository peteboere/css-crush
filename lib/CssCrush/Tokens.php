<?php
/**
 *
 * Token API.
 *
 */
class CssCrush_Tokens
{
    public $store;
    protected $ids;

    public function __construct ()
    {
        $types = array(
            's', // Strings
            'c', // Comments
            'r', // Rules
            'p', // Parens
            'u', // URLs
            't', // Traces
        );

        $this->store = new stdClass;
        $this->ids = new stdClass;

        foreach ($types as $type) {
            $this->store->{$type} = array();
            $this->ids->{$type} = 0;
        }
    }

    public function get ($label)
    {
        $path =& $this->store->{$label[1]};
        return isset($path[$label]) ? $path[$label] : null;
    }

    public function pop ($label)
    {
        $value = $this->get($label);
        $this->release($label);
        return $value;
    }

    public function release ($label)
    {
        unset($this->store->{$label[1]}[$label]);
    }

    public function add ($value, $type, $existing_label = null)
    {
        $label = $existing_label ? $existing_label : $this->createLabel($type);
        $this->store->{$type}[$label] = $value;
        return $label;
    }

    public function createLabel ($type)
    {
        $counter = base_convert(++$this->ids->{$type}, 10, 36);
        return "?$type$counter?";
    }

    public function restore ($str, $type, $release = false)
    {
        switch ($type) {
            case 'u':
                // Currently this always releases URLs
                // may need to refactor later.
                static $url_revert_callback;
                if (! $url_revert_callback) {
                    $url_revert_callback = create_function('$m', '
                        $url = CssCrush::$process->tokens->pop($m[0]);
                        return $url ? $url->getOriginalValue() : \'\';
                    ');
                }

                $str = preg_replace_callback(CssCrush_Regex::$patt->u_token, $url_revert_callback, $str);
                break;
            default:
                $token_table =& $this->store->{$type};

                // Find matching tokens.
                foreach (CssCrush_Regex::matchAll(CssCrush_Regex::$patt->{"{$type}_token"}, $str) as $m) {
                    $label = $m[0][0];
                    if (isset($token_table[$label])) {
                        $str = str_replace($label, $token_table[$label], $str);
                        if ($release) {
                            unset($token_table[$label]);
                        }
                    }
                }
                break;
        }

        return $str;
    }

    public function capture ($str, $type)
    {
        switch ($type) {
            case 'u':
                return $this->captureUrls($str);
                break;
            case 's':
                return $this->captureStrings($str);
                break;
            case 'p':
                return $this->captureParens($str);
                break;
        }
    }

    public function captureParens ($str)
    {
        static $callback;
        if (! $callback) {
            $callback = create_function('$m', 'return CssCrush::$process->tokens->add($m[0], \'p\');');
        }
        return preg_replace_callback(CssCrush_Regex::$patt->balancedParens, $callback, $str);
    }

    public function captureStrings ($str, $add_padding = false)
    {
        static $callback;
        if (! $callback) {
            $callback = create_function('$m', 'return CssCrush::$process->tokens->add($m[0], \'s\');');
        }
        return preg_replace_callback(CssCrush_Regex::$patt->string, $callback, $str);
    }

    public function captureUrls ($str, $add_padding = false)
    {
        static $url_patt;
        if (! $url_patt) {
            $url_patt = CssCrush_Regex::create(
                '@import \s+ (?<import>{{s-token}}) | {{LB}} (?<func>url|data-uri) {{parens}}', 'ixS');
        }

        $count = preg_match_all($url_patt, $str, $m, PREG_OFFSET_CAPTURE);

        while ($count--) {

            list($full_text, $full_offset) = $m[0][$count];
            list($import_text, $import_offset) = $m['import'][$count];

            // @import directive.
            if ($import_offset !== -1) {

                $url = new CssCrush_Url(trim($import_text));
                $str = str_replace($import_text, $add_padding ? str_pad($url->label, strlen($import_text)) : $url->label, $str);
            }

            // A URL function.
            else {

                $func_name = strtolower($m['func'][$count][0]);

                $url = new CssCrush_Url(trim($m['parens_content'][$count][0]));
                $url->convertToData = 'data-uri' === $func_name;
                $str = substr_replace($str, $add_padding ? CssCrush_Tokens::pad($url->label, $full_text) : $url->label, $full_offset, strlen($full_text));
            }
        }

        return $str;
    }

    static public function pad ($label, $replaced_text)
    {
        // Padding token labels to maintain whitespace and newlines.

        // Match contains newlines.
        if (($last_newline_pos = strrpos($replaced_text, "\n")) !== false) {
            $label .= str_repeat("\n", substr_count($replaced_text, "\n")) . str_repeat(' ', strlen(substr($replaced_text, $last_newline_pos))-1);
        }
        // Match contains no newlines.
        else {
            $label = str_pad($label, strlen($replaced_text));
        }

        return $label;
    }

    static public function is ($label, $of_type)
    {
        static $type_patt;
        if (! $type_patt) {
            $type_patt = CssCrush_Regex::create('^ \? (?<type>[a-z]) {{token-id}} \? $', 'xS');
        }
        if (preg_match($type_patt, $label, $m)) {
            return $of_type ? ($of_type === $m['type']) : true;
        }
        return false;
    }
}
