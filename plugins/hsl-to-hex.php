<?php
/**
 * Polyfill for hsl() color values
 *
 * @before
 *     color: hsl( 100, 50%, 50%)
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

    $hsl_patt = Regex::make('~{{LB}}hsl({{p-token}})~i');

    foreach ($rule as &$declaration) {

        if (! $declaration->skip && isset($declaration->functions['hsl'])) {
            while (preg_match($hsl_patt, $declaration->value, $m)) {
                $token = $m[1];
                $color = new Color('hsl' . CssCrush::$process->tokens->pop($token));
                $declaration->value = str_replace($m[0], $color->getHex(), $declaration->value);
            }
        }
    }
}
