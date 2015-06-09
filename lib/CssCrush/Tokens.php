<?php
/**
 *
 * Token API.
 *
 */
namespace CssCrush;

class Tokens
{
    public $store;
    protected $ids;

    public function __construct(array $types = null)
    {
        $types = $types ?: array(
            's', // strings.
            'c', // comments.
            'r', // rules.
            'u', // URLs.
            't', // traces.
        );

        $this->store = new \stdClass;
        $this->ids = new \stdClass;

        foreach ($types as $type) {
            $this->store->$type = array();
            $this->ids->$type = 0;
        }
    }

    public function get($label)
    {
        $path =& $this->store->{$label[1]};

        return isset($path[$label]) ? $path[$label] : null;
    }

    public function pop($label)
    {
        $value = $this->get($label);
        if (isset($value)) {
            unset($this->store->{$label[1]}[$label]);
        }

        return $value;
    }

    public function add($value, $type = null, $existing_label = null)
    {
        if ($value instanceof Url) {
            $type = 'u';
        }
        elseif ($value instanceof Rule) {
            $type = 'r';
        }
        $label = $existing_label ? $existing_label : $this->createLabel($type);
        $this->store->{$type}[$label] = $value;

        return $label;
    }

    public function createLabel($type)
    {
        $counter = base_convert(++$this->ids->$type, 10, 36);

        return "?$type$counter?";
    }

    public function restore($str, $types, $release = false, $callback = null)
    {
        $types = implode('', (array) $types);
        $patt = Regex::make("~\?[$types]{{ token_id }}\?~S");
        $tokens = $this;
        $callback = $callback ?: function ($m) use ($tokens, $release) {
            return $release ? $tokens->pop($m[0]) : $tokens->get($m[0]);
        };

        return preg_replace_callback($patt, $callback, $str);
    }

    public function capture($str, $type)
    {
        switch ($type) {
            case 'u':
                return $this->captureUrls($str);
                break;
            case 's':
                return preg_replace_callback(Regex::$patt->string, function ($m) {
                    return Crush::$process->tokens->add($m[0], 's');
                }, $str);
        }
    }

    public function captureUrls($str, $add_padding = false)
    {
        $count = preg_match_all(
            Regex::make('~@import \s+ (?<import>{{s_token}}) | {{LB}} (?<func>url|data-uri) {{parens}}~ixS'),
            $str,
            $m,
            PREG_OFFSET_CAPTURE);

        while ($count--) {

            list($full_text, $full_offset) = $m[0][$count];
            list($import_text, $import_offset) = $m['import'][$count];

            // @import directive.
            if ($import_offset !== -1) {

                $label = $this->add(new Url(trim($import_text)));
                $str = str_replace($import_text, $add_padding ? str_pad($label, strlen($import_text)) : $label, $str);
            }

            // A URL function.
            else {
                $func_name = strtolower($m['func'][$count][0]);

                $url = new Url(trim($m['parens_content'][$count][0]));
                $url->convertToData = 'data-uri' === $func_name;
                $label = $this->add($url);
                $str = substr_replace(
                        $str,
                        $add_padding ? Tokens::pad($label, $full_text) : $label,
                        $full_offset,
                        strlen($full_text));
            }
        }

        return $str;
    }

    public static function pad($label, $replaced_text)
    {
        // Padding token labels to maintain whitespace and newlines.
        if (($last_newline_pos = strrpos($replaced_text, "\n")) !== false) {
            $label .= str_repeat("\n", substr_count($replaced_text, "\n")) . str_repeat(' ', strlen(substr($replaced_text, $last_newline_pos))-1);
        }
        else {
            $label = str_pad($label, strlen($replaced_text));
        }

        return $label;
    }

    public static function is($label, $of_type)
    {
        if (preg_match(Regex::$patt->token, $label, $m)) {

            return $of_type ? ($of_type === $m['type']) : true;
        }

        return false;
    }

    public static function test($value)
    {
        return preg_match(Regex::$patt->token, $value, $m) ? $m['type'] : false;
    }
}
