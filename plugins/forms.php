<?php
/**
 * Pseudo classes for working with forms.
 *
 * @before
 *      :text {...}
 *      :checkbox {...}
 *      :radio {...}
 *      :button {...}
 *      :input(date) {...}
 *
 * @after
 *      input[type="text"] {...}
 *      input[type="checkbox"] {...}
 *      input[type="radio"] {...}
 *      input[type="button"], button {...}
 *      input[type="date"] {...}
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


function forms () {
    return array(
        'input' => 'input[type="#(0 text)"]',
        'button' => ':any(button, input[type="button"])',
        'checkbox' => 'input[type="checkbox"]',
        'file' => 'input[type="file"]',
        'image' => 'input[type="image"]',
        'password' => 'input[type="password"]',
        'submit' => 'input[type="submit"]',
        'radio' => 'input[type="radio"]',
        'reset' => 'input[type="reset"]',
        'text' => 'input[type="text"]',
    );
}
