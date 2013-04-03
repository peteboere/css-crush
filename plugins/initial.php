<?php
/**
 * Polyfill for the 'initial' keyword
 *
 * (http://www.w3.org/TR/css3-cascade/#initial0)
 *
 * @before
 *     opacity: initial;
 *     white-space: initial;
 *     min-height: initial;
 *
 * @after
 *     opacity: 1;
 *     white-space: normal;
 *     max-height: auto;
 *
 */

CssCrush_Plugin::register('initial', array(
    'enable' => 'csscrush__enable_initial',
    'disable' => 'csscrush__disable_initial',
));

function csscrush__enable_initial () {
    CssCrush_Hook::add('rule_prealias', 'csscrush__initial');
}

function csscrush__disable_initial () {
    CssCrush_Hook::remove('rule_prealias', 'csscrush__initial');
}

function csscrush__initial (CssCrush_Rule $rule) {

    static $initial_values;
    if (! $initial_values) {
        if (! ($initial_values = @parse_ini_file(CssCrush::$config->location . '/misc/initial-values.ini'))) {
            trigger_error(__METHOD__ . ": Initial keywords file could not be parsed.\n", E_USER_NOTICE);
            return;
        }
    }

    foreach ($rule as &$declaration) {
        if (! $declaration->skip && 'initial' === $declaration->value) {
            if (isset($initial_values[$declaration->property])) {
                $declaration->value = $initial_values[ $declaration->property ];
            }
            else {
                // Fallback to 'inherit'.
                $declaration->value = 'inherit';
            }
        }
    }
}
