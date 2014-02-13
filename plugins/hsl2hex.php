<?php
/**
 * Polyfill for hsl() color values
 *
 * @see docs/plugins/hsl2hex.md
 */
namespace CssCrush;

Plugin::register('hsl2hex', array(
    'enable' => function ($process) {
        $process->hooks->add('rule_postalias', 'CssCrush\hsl2hex');
    }
));


function hsl2hex(Rule $rule) {

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
