<?php
/**
 * Polyfill for the 'initial' keyword
 *
 * @see docs/plugins/initial.md
 */
namespace CssCrush;

Plugin::register('initial', array(
    'enable' => function ($process) {
        $process->hooks->add('rule_prealias', 'CssCrush\initial');
    }
));

function initial(Rule $rule) {

    static $initial_values;
    if (! $initial_values) {
        if (! ($initial_values = Util::parseIni(Crush::$dir . '/misc/initial-values.ini'))) {
            return;
        }
    }

    foreach ($rule->declarations->filter(array('skip' => false, 'value|lower' => 'initial')) as $declaration) {
        if (isset($initial_values[$declaration->property])) {
            $declaration->value = $initial_values[ $declaration->property ];
        }
        else {
            $declaration->value = 'inherit';
        }
    }
}
