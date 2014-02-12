<?php
/**
 * Pseudo classes for working with forms
 *
 * @see docs/plugins/forms.md
 */
namespace CssCrush;

Plugin::register('forms', array(
    'enable' => function () {
        foreach (forms() as $name => $handler) {
            if (is_array($handler)) {
                $type = $handler['type'];
                $handler = $handler['handler'];
            }
            Crush::addSelectorAlias($name, $handler, $type);
        }
    },
    'disable' => function () {
        foreach (forms() as $name => $handler) {
            Crush::removeSelectorAlias($name);
        }
    },
));


function forms() {
    return array(
        'input' => array(
            'type' => 'splat',
            'handler' => 'input[type=#(text)]',
        ),
        'checkbox' => 'input[type="checkbox"]',
        'radio' => 'input[type="radio"]',
        'file' => 'input[type="file"]',
        'image' => 'input[type="image"]',
        'password' => 'input[type="password"]',
        'submit' => 'input[type="submit"]',
        'text' => 'input[type="text"]',
    );
}
