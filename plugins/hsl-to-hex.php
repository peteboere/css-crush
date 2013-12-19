<?php
/**
 * Polyfill for hsl() color values
 *
 * @before
 *     color: hsl(100, 50%, 50%)
 *
 * @after
 *    color: #6abf40
 */
namespace CssCrush;

Plugin::register('hsl-to-hex', array(
    'enable' => function () {
        Hook::add('rule_postalias', 'CssCrush\hsl_to_hex');
    },
    'disable' => function () {
        Hook::remove('rule_postalias', 'CssCrush\hsl_to_hex');
    },
));


function hsl_to_hex(Rule $rule) {

    $hsl_patt = Regex::make('~{{ LB }}hsl({{ parens }})~i');

    foreach ($rule->declarations->filter(array('skip' => false)) as $declaration) {
        if (isset($declaration->functions['hsl'])) {
            $declaration->value = preg_replace_callback($hsl_patt, function ($m) {
                $color = new Color($m[0]);
                return $color->getHex();
            }, $declaration->value);
        }
    }
}
