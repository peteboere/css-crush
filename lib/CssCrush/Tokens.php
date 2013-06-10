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
        $counter = ++$this->uid;
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
            $url_patt = CssCrush_Regex::create('@import +(<s-token>)|<LB>(url|data-uri)\(', 'iS');
        }

        $offset = 0;
        while (preg_match($url_patt, $str, $outer_m, PREG_OFFSET_CAPTURE, $offset)) {

            $outer_offset = $outer_m[0][1];
            $is_import_url = ! isset($outer_m[2]);

            if ($is_import_url) {
                $url = new CssCrush_Url($outer_m[1][0]);
                $str = str_replace($outer_m[1][0], $url->label, $str);
            }

            // Match parenthesis if not a string token.
            elseif (
                preg_match(CssCrush_Regex::$patt->balancedParens, $str, $inner_m, PREG_OFFSET_CAPTURE, $outer_offset)
            ) {
                $url = new CssCrush_Url($inner_m[1][0]);
                $func_name = strtolower($outer_m[2][0]);
                $url->convertToData = 'data-uri' === $func_name;
                $str = substr_replace($str, $url->label, $outer_offset,
                    strlen($func_name) + strlen($inner_m[0][0]));
            }

            // If brackets cannot be matched, skip over the original match.
            else {
                $offset += strlen($outer_m[0][0]);
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

        // We return the newlines to maintain line numbering when tracing.
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
        if (preg_match('~^\?([a-z])\d+\?$~S', $label, $m)) {
            return $of_type ? ($of_type === $m[1]) : true;
        }
        return false;
    }
}
