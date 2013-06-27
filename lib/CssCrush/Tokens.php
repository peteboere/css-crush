<?php
/**
 *
 * Token API.
 *
 */
class CssCrush_Tokens
{
    public $store;
    protected $uid = 0;

    public function __construct ()
    {
        $this->store = (object) array(
            's' => array(), // Strings
            'c' => array(), // Comments
            'r' => array(), // Rules
            'p' => array(), // Parens
            'u' => array(), // URLs
            't' => array(), // Traces
        );
    }

    public function get ($label)
    {
        $path =& $this->store->{$label[1]};
        return isset($path[$label]) ? $path[$label] : null;
    }

    public function getOfType ($type)
    {
        return $this->store->{$type};
    }

    public function releaseOfType ($type)
    {
        $this->store->{$type} = array();
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
        $counter = base_convert(++$this->uid, 10, 36);
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

    public function captureStrings ($str)
    {
        static $callback;
        if (! $callback) {
            $callback = create_function('$m', 'return CssCrush::$process->tokens->add($m[0], \'s\');');
        }
        return preg_replace_callback(CssCrush_Regex::$patt->string, $callback, $str);
    }

    public function captureUrls ($str)
    {
        static $url_patt;
        if (! $url_patt) {
            $url_patt = CssCrush_Regex::create('@import\s+(<s-token>)|<LB>(url|data-uri)\(', 'iS');
        }

        $count = preg_match_all($url_patt, $str, $m, PREG_OFFSET_CAPTURE);
        while ($count--) {

            // Full match.
            $outer0 = $m[0][$count];

            // @import directive position.
            $outer1 = $m[1][$count];

            // URL function position.
            $outer2 = is_array($m[2][$count]) ? $m[2][$count] : null;

            list($outer_text, $outer_offset) = $outer0;
            $newlines = '';

            // An @import directive.
            if (! $outer2) {

                if (strpos($outer_text, "\n") !== false) {
                    $newlines = str_repeat("\n", substr_count($outer_text, "\n"));
                }
                $url = new CssCrush_Url(trim($outer1[0]));
                $str = str_replace($outer1[0], $url->label . $newlines, $str);
            }

            // A URL function - match closing parens.
            elseif (
                preg_match(CssCrush_Regex::$patt->balancedParens, $str, $inner_m, PREG_OFFSET_CAPTURE, $outer_offset)
            ) {

                $inner_text = $inner_m[0][0];
                if (strpos($inner_text, "\n") !== false) {
                    $newlines = str_repeat("\n", substr_count($inner_text, "\n"));
                }
                $url = new CssCrush_Url(trim($inner_m[1][0]));
                $func_name = strtolower($outer2[0]);
                $url->convertToData = 'data-uri' === $func_name;
                $str = substr_replace($str, $url->label . $newlines, $outer0[1], strlen($func_name) + strlen($inner_text));
            }
        }

        return $str;
    }

    public function captureCommentAndString ($str)
    {
        return preg_replace_callback(CssCrush_Regex::$patt->commentAndString,
            array('self', 'cb_captureCommentAndString'), $str);
    }

    protected function cb_captureCommentAndString ($match)
    {
        $full_match = $match[0];
        $process = CssCrush::$process;

        // We return the newline count to keep track of line numbering.
        $newlines = str_repeat("\n", substr_count($full_match, "\n"));

        if (strpos($full_match, '/*') === 0) {

            // Bail without storing comment if output is minified or a private comment.
            if (
                $process->minifyOutput ||
                strpos($full_match, '/*$') === 0
            ) {
                return $newlines;
            }

            // Fix broken comments as they will break any subsquent
            // imported files that are inlined.
            if (! preg_match('~\*/$~', $full_match)) {
                $full_match .= '*/';
            }
            $label = $process->tokens->add($full_match, 'c');
        }
        else {

            // Fix broken strings as they will break any subsquent
            // imported files that are inlined.
            if ($full_match[0] !== $full_match[strlen($full_match)-1]) {
                $full_match .= $full_match[0];
            }
            $label = $process->tokens->add($full_match, 's');
        }

        return $newlines . $label;
    }

    static public function is ($label, $of_type)
    {
        if (preg_match('~^\?([a-z])[0-9a-z]+\?$~S', $label, $m)) {
            return $of_type ? ($of_type === $m[1]) : true;
        }
        return false;
    }
}
