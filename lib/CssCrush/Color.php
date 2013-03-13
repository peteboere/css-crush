<?php
/**
 *
 * Colour parsing and conversion.
 *
 */
class CssCrush_Color
{
    // Cached color keyword tables.
    static public $keywords;
    static public $minifyableKeywords;

    static public function &loadKeywords ()
    {
        if (! isset(self::$keywords)) {

            $table = array();
            $path = CssCrush::$config->location . '/misc/color-keywords.ini';
            if ($keywords = parse_ini_file($path)) {
                foreach ($keywords as $word => $rgb) {
                    $rgb = array_map('intval', explode(',', $rgb));
                    self::$keywords[ $word ] = $rgb;
                }
            }
        }

        return self::$keywords;
    }

    static public function &loadMinifyableKeywords ()
    {
        if (! isset(self::$minifyableKeywords)) {

            // If color name is longer than 4 and less than 8 test to see if its hex
            // representation could be shortened.
            $table = array();
            $keywords =& CssCrush_Color::loadKeywords();

            foreach ($keywords as $name => &$rgb) {
                $name_len = strlen($name);
                if ($name_len < 5) {
                    continue;
                }

                $hex = self::rgbToHex($rgb);

                if ($name_len > 7) {
                    self::$minifyableKeywords[ $name ] = $hex;
                }
                else {
                    if (preg_match(CssCrush_Regex::$patt->cruftyHex, $hex)) {
                        self::$minifyableKeywords[ $name ] = $hex;
                    }
                }
            }
        }

        return self::$minifyableKeywords;
    }

    static public function parse ($str)
    {
        $rgba = false;

        if ($test = CssCrush_Color::test($str)) {
            $color = $test['value'];
            $type = $test['type'];
        }
        else {

            return $rgba;
        }

        switch ($type) {

            case 'hex':
                $rgba = CssCrush_Color::hexToRgb($color);
                break;

            case 'rgb':
            case 'rgba':
            case 'hsl':
            case 'hsla':
                $function = $type;
                $vals = substr($color, strlen($function) + 1);  // Trim function name and start paren.
                $vals = substr($vals, 0, strlen($vals) - 1);    // Trim end paren.
                $vals = array_map('trim', explode(',', $vals)); // Explode to array of arguments.

                // Always set the alpha channel.
                $vals[3] = isset($vals[3]) ? floatval($vals[3]) : 1;

                if (strpos($function, 'rgb') === 0) {
                    $rgba = CssCrush_Color::normalizeCssRgb($vals);
                }
                else {
                    $rgba = CssCrush_Color::cssHslToRgb($vals);
                }
                break;

            case 'keyword':
                $keywords =& self::loadKeywords();
                $rgba = $keywords[$color];

                // Manually add the alpha component.
                $rgba[] = 1;
                break;
        }

        return $rgba;
    }

    static public function test ($str)
    {
        $color_test = array();
        $str = strtolower(trim($str));

        // First match a hex value or the start of a function.
        if (preg_match('~^(
                \#(?=[[:xdigit:]]{3}) |
                \#(?=[[:xdigit:]]{6}) |
                rgba?(?=[?(]) |
                hsla?(?=[?(])
            )~xS', $str, $m)) {

            $type_match = $m[1];

            switch ($type_match) {
                case '#':
                    $color_test['type'] = 'hex';
                    break;

                case 'hsl':
                case 'hsla':
                case 'rgb':
                case 'rgba':
                    $color_test['type'] = $type_match;
                    break;
            }
        }

        // Secondly try to match a color keyword.
        else {

            $keywords =& self::loadKeywords();
            if (isset($keywords[$str])) {
                $color_test['type'] = 'keyword';
            }
        }

        if ($color_test) {
            $color_test['value'] = $str;
        }

        return $color_test ? $color_test : false;
    }

    /**
     * http://mjijackson.com/2008/02/
     * rgb-to-hsl-and-rgb-to-hsv-color-model-conversion-algorithms-in-javascript
     *
     * Converts an RGB color value to HSL. Conversion formula
     * adapted from http://en.wikipedia.org/wiki/HSL_color_space.
     * Assumes r, g, and b are contained in the set [0, 255] and
     * returns h, s, and l in the set [0, 1].
     */
    static public function rgbToHsl (array $rgba)
    {
        list($r, $g, $b, $a) = $rgba;
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $h;
        $s;
        $l = ($max + $min) / 2;

        if ($max == $min) {
            $h = $s = 0;
        }
        else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch($max) {
                case $r:
                    $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $d + 2;
                    break;
                case $b:
                    $h = ($r - $g) / $d + 4;
                    break;
            }
            $h /= 6;
        }

        return array($h, $s, $l, $a);
    }

    /**
     * http://mjijackson.com/2008/02/
     * rgb-to-hsl-and-rgb-to-hsv-color-model-conversion-algorithms-in-javascript
     *
     * Converts an HSL color value to RGB. Conversion formula
     * adapted from http://en.wikipedia.org/wiki/HSL_color_space.
     * Assumes h, s, and l are contained in the set [0, 1] and
     * returns r, g, and b in the set [0, 255].
     */
    static public function hslToRgb (array $hsla)
    {
        // Populate unspecified alpha value.
        if (! isset($hsla[3])) {
            $hsla[3] = 1;
        }

        list($h, $s, $l, $a) = $hsla;
        $r;
        $g;
        $b;
        if ($s == 0) {
            $r = $g = $b = $l;
        }
        else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return array(round($r * 255), round($g * 255), round($b * 255), $a);
    }

    // Convert percentages to points (0-255).
    static public function normalizeCssRgb (array $rgba)
    {
        foreach ($rgba as &$val) {
            if (strpos($val, '%') !== false) {
                $val = str_replace('%', '', $val);
                $val = round($val * 2.55);
            }
        }

        return $rgba;
    }

    static public function cssHslToRgb (array $hsla)
    {
        // Populate unspecified alpha value.
        if (! isset($hsla[3])) {
            $hsla[3] = 1;
        }

        // Alpha is carried over.
        $a = array_pop($hsla);

        // Normalize the hue degree value then convert to float.
        $h = array_shift($hsla);
        $h = $h % 360;
        if ($h < 0) {
            $h = 360 + $hue;
        }
        $h = $h / 360;

        // Convert saturation and lightness to floats.
        foreach ($hsla as &$val) {
            $val = str_replace('%', '', $val);
            $val /= 100;
        }
        list($s, $l) = $hsla;

        return self::hslToRgb(array($h, $s, $l, $a));
    }

    static public function hueToRgb ($p, $q, $t)
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }

    static public function rgbToHex (array $rgba)
    {
        // Drop alpha component.
        array_pop($rgba);

        $hex_out = '#';
        foreach ($rgba as $val) {
            $hex_out .= str_pad(dechex($val), 2, '0', STR_PAD_LEFT);
        }

        return $hex_out;
    }

    static public function hexToRgb ($hex)
    {
        $hex = substr($hex, 1);

        // Handle shortened format.
        if (strlen($hex) === 3) {
            $long_hex = array();
            foreach (str_split($hex) as $val) {
                $long_hex[] = $val . $val;
            }
            $hex = $long_hex;
        }
        else {
            $hex = str_split($hex, 2);
        }

        // Return RGBa
        $rgba = array_map('hexdec', $hex);
        $rgba[] = 1;

        return $rgba;
    }

    static public function colorAdjust ($str, array $adjustments)
    {
        $hsla = new CssCrush_Color($str, true);

        // On failure to parse return input.
        return $hsla->isValid ? $hsla->adjust($adjustments)->__toString() : $str;
    }

    static public function colorSplit ($str)
    {
        if ($test = CssCrush_Color::test($str)) {
            $color = $test['value'];
            $type = $test['type'];
        }
        else {

            return false;
        }

        // If non-alpha color return early.
        if (! in_array($type, array('hsla', 'rgba'))) {

            return array($color, 1);
        }

        static $alpha_color_patt;
        if (! $alpha_color_patt) {
            $alpha_color_patt = CssCrush_Regex::create(
                '^(rgb|hsl)a\((<number>%?,<number>%?,<number>%?),(<number>)\)$');
        }

        // Strip all whitespace.
        $color = preg_replace('~\s+~', '', $color);

        // Extract alpha component if one is matched.
        $opacity = 1;
        if (preg_match($alpha_color_patt, $color, $m)) {
            $opacity = floatval($m[3]);
            $color = "$m[1]($m[2])";
        }

        // Return color value and alpha component seperated.
        return array($color, $opacity);
    }


    #############################
    #  Instances.

    protected $value;
    protected $hslColorSpace;
    public $isValid;

    public function __construct ($color, $use_hsl_color_space = false)
    {
        $this->value = is_array($color) ? $color : self::parse($color);
        $this->isValid = $this->value;
        if ($use_hsl_color_space && $this->isValid) {
            $this->toHsl();
        }
    }

    public function __toString ()
    {
        if ($this->value[3] !== 1) {

            return 'rgba(' . implode(',', $this->hslColorSpace ? $this->getRgb() : $this->value) . ')';
        }
        else {

            return $this->getHex();
        }
    }

    public function toRgb ()
    {
        if ($this->hslColorSpace) {
            $this->hslColorSpace = false;
            $this->value = self::hslToRgb($this->value);
        }

        return $this;
    }

    public function toHsl ()
    {
        if (! $this->hslColorSpace) {
            $this->hslColorSpace = true;
            $this->value = self::rgbToHsl($this->value);
        }

        return $this;
    }

    public function getHex ()
    {
        return self::rgbToHex($this->getRgb());
    }

    public function getHsl ()
    {
        return ! $this->hslColorSpace ? self::rgbToHsl($this->value) : $this->value;
    }

    public function getRgb ()
    {
        return $this->hslColorSpace ? self::hslToRgb($this->value) : $this->value;
    }

    public function getComponent ($index)
    {
        return $this->value[$index];
    }

    public function setComponent ($index, $new_component_value)
    {
        $this->value[$index] = $new_component_value;
    }

    public function adjust (array $adjustments)
    {
        $was_hsl_color_space = $this->hslColorSpace;

        $this->toHsl();
        // Normalize percentage adjustment parameters to floating point numbers.
        foreach ($adjustments as $index => $val) {

            // Normalize argument.
            $val = $val ? trim(str_replace('%', '', $val)) : 0;

            if ($val) {
                // Reduce value to float.
                $val /= 100;
                // Update the color component.
                $this->setComponent($index, max(0, min(1, $this->getComponent($index) + $val)));
            }
        }

        return ! $was_hsl_color_space ? $this->toRgb() : $this;
    }
}
