<?php
/**
 * Pseudo classes for working with forms.
 *
 * @before
 *      :input(date, search, email) {...}
 *      :checkbox {...}
 *      :radio {...}
 *      :text {...}
 *
 * @after
 *      input[type="date"], input[type="search"], input[type="email"] {...}
 *      input[type="checkbox"] {...}
 *      input[type="radio"] {...}
 *      input[type="text"] {...}
 */
namespace CssCrush;

Plugin::register('forms', array(
    'enable' => function () {
        foreach (forms() as $name => $value) {
            CssCrush::addSelectorAlias($name, $value);
        }
    },
    'disable' => function () {
        foreach (forms() as $name => $value) {
            CssCrush::removeSelectorAlias($name);
        }
    },
));


function forms() {
    return array(
        'input' => function ($args) {
            $types = array();
            foreach ($args as $type) {
                $types[] = "[type=\"$type\"]";
            }

            $result = $types ? 'input:any(' .  implode(',', $types) . ')' : 'input[type="text"]';
            return CssCrush::$process->tokens->captureStrings($result);
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
