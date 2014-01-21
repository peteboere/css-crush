<?php
/**
 * Pseudo classes for working with forms
 *
 * @see docs/plugins/forms.md
 */
namespace CssCrush;

Plugin::register('forms', array(
    'enable' => function () {
        foreach (forms() as $name => $value) {
            Crush::addSelectorAlias($name, $value);
        }
    },
    'disable' => function () {
        foreach (forms() as $name => $value) {
            Crush::removeSelectorAlias($name);
        }
    },
));


function forms() {
    return array(
        'input' => function ($args) {
            $types = array();
            foreach ($args as $type) {
                $types[] = "[type=$type]";
            }

            $result = $types ? 'input:any(' .  implode(',', $types) . ')' : 'input[type="text"]';
            return Crush::$process->tokens->capture($result, 's');
        },

        'checkbox' => 'input[type="checkbox"]',
        'radio' => 'input[type="radio"]',
        'file' => 'input[type="file"]',
        'image' => 'input[type="image"]',
        'password' => 'input[type="password"]',
        'submit' => 'input[type="submit"]',
        'text' => 'input[type="text"]',
    );
}
