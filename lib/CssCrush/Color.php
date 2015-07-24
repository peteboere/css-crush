<?php
/**
 *
 * Colour parsing and conversion.
 *
 */
namespace CssCrush;

class Color
{
    protected static $minifyableKeywords;

    public static function getKeywords()
    {
        static $namedColors;
        if (! isset($namedColors)) {
            if ($colors = Util::parseIni(Crush::$dir . '/misc/color-keywords.ini')) {
                foreach ($colors as $name => $rgb) {
                    $namedColors[$name] = array_map('floatval', explode(',', $rgb)) + array(0,0,0,1);
                }
            }
        }

        return isset(Crush::$process->colorKeywords) ? Crush::$process->colorKeywords : $namedColors;
    }

    public static function getMinifyableKeywords()
    {
        if (! isset(self::$minifyableKeywords)) {

            // If color name is longer than 4 and less than 8 test to see if its hex
            // representation could be shortened.
            $keywords = self::getKeywords();

            foreach ($keywords as $name => $rgba) {
                $name_len = strlen($name);
                if ($name_len < 5) {
                    continue;
                }

                $hex = self::rgbToHex($rgba);

                if ($name_len > 7) {
                    self::$minifyableKeywords[$name] = $hex;
                }
                else {
                    if (preg_match(Regex::$patt->cruftyHex, $hex)) {
                        self::$minifyableKeywords[$name] = $hex;
                    }
                }
            }
        }

        return self::$minifyableKeywords;
    }

    public static function parse($str)
    {
        if ($test = Color::test($str)) {
            $color = $test['value'];
            $type = $test['type'];
        }
        else {

            return false;
        }

        $rgba = false;

        switch ($type) {

            case 'hex':
                $rgba = Color::hexToRgb($color);
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
                    $rgba = Color::normalizeCssRgb($vals);
                }
                else {
                    $rgba = Color::cssHslToRgb($vals);
                }
                break;

            case 'keyword':
                $keywords = self::getKeywords();
                $rgba = $keywords[$color];
                break;
        }

        return $rgba;
    }

    public static function test($str)
    {
        static $color_patt;
        if (! $color_patt) {
            $color_patt = Regex::make('~^(
                \#(?={{hex}}{3}) |
                \#(?={{hex}}{6}) |
                rgba?(?=\() |
                hsla?(?=\()
            )~ixS');
        }

        $color_test = array();
        $str = strtolower(trim($str));

        // First match a hex value or the start of a function.
        if (preg_match($color_patt, $str, $m)) {

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
            $keywords = self::getKeywords();
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
    public static function rgbToHsl(array $rgba)
    {
        list($r, $g, $b, $a) = $rgba;
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $h = 0;
        $s = 0;
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
    public static function hslToRgb(array $hsla)
    {
        // Populate unspecified alpha value.
        if (! isset($hsla[3])) {
            $hsla[3] = 1;
        }

        list($h, $s, $l, $a) = $hsla;
        $r = 0;
        $g = 0;
        $b = 0;
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
    public static function normalizeCssRgb(array $rgba)
    {
        foreach ($rgba as &$val) {
            if (strpos($val, '%') !== false) {
                $val = str_replace('%', '', $val);
                $val = round($val * 2.55);
            }
        }

        return $rgba;
    }

    public static function cssHslToRgb(array $hsla)
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
            $h = 360 + $h;
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

    public static function hueToRgb($p, $q, $t)
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }

    public static function rgbToHex(array $rgba)
    {
        // Drop alpha component.
        array_pop($rgba);

        $hex_out = '#';
        foreach ($rgba as $val) {
            $hex_out .= str_pad(dechex($val), 2, '0', STR_PAD_LEFT);
        }

        return $hex_out;
    }

    public static function hexToRgb($hex)
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

    public static function colorAdjust($str, array $adjustments)
    {
        $hsla = new Color($str, true);

        // On failure to parse return input.
        return $hsla->isValid ? $hsla->adjust($adjustments)->__toString() : $str;
    }

    public static function colorSplit($str)
    {
        if ($test = Color::test($str)) {
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

        // Strip all whitespace.
        $color = preg_replace('~\s+~', '', $color);

        // Extract alpha component if one is matched.
        $opacity = 1;
        if (preg_match(
                Regex::make('~^(rgb|hsl)a\(({{number}}%?,{{number}}%?,{{number}}%?),({{number}})\)$~i'),
                $color,
                $m)
        ) {
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
    protected $namedComponents = array(
        'red' => 0,
        'green' => 1,
        'blue' => 2,
        'alpha' => 3,
    );
    public $isValid;

    public function __construct($color, $useHslColorSpace = false)
    {
        $this->value = is_array($color) ? $color : self::parse($color);
        $this->isValid = ! empty($this->value);
        if ($useHslColorSpace && $this->isValid) {
            $this->toHsl();
        }
    }

    public function __toString()
    {
        // For opaque colors return hex notation as it's the most compact.
        if ($this->getComponent('alpha') == 1) {

            return $this->getHex();
        }

        // R, G and B components must be integers.
        $components = array();
        foreach (($this->hslColorSpace ? $this->getRgb() : $this->value) as $index => $component) {
            $components[] = ($index === 3) ? $component : min(round($component), 255);
        }

        return 'rgba(' . implode(',', $components) . ')';
    }

    public function toRgb()
    {
        if ($this->hslColorSpace) {
            $this->hslColorSpace = false;
            $this->value = self::hslToRgb($this->value);
        }

        return $this;
    }

    public function toHsl()
    {
        if (! $this->hslColorSpace) {
            $this->hslColorSpace = true;
            $this->value = self::rgbToHsl($this->value);
        }

        return $this;
    }

    public function getHex()
    {
        return self::rgbToHex($this->getRgb());
    }

    public function getHsl()
    {
        return ! $this->hslColorSpace ? self::rgbToHsl($this->value) : $this->value;
    }

    public function getRgb()
    {
        return $this->hslColorSpace ? self::hslToRgb($this->value) : $this->value;
    }

    public function getComponent($index)
    {
        $index = isset($this->namedComponents[$index]) ? $this->namedComponents[$index] : $index;
        return $this->value[$index];
    }

    public function setComponent($index, $newComponentValue)
    {
        $index = isset($this->namedComponents[$index]) ? $this->namedComponents[$index] : $index;
        $this->value[$index] = is_numeric($newComponentValue) ? $newComponentValue : 0;
    }

    public function adjust(array $adjustments)
    {
        $wasHslColor = $this->hslColorSpace;

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

        return ! $wasHslColor ? $this->toRgb() : $this;
    }
}
