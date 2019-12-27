<?php
/**
 * Pseudo classes for working with forms
 *
 * @see docs/plugins/forms.md
 */
namespace CssCrush;

\csscrush_plugin('forms', function ($process) {
    foreach (forms() as $name => $handler) {
        if (is_array($handler)) {
            $type = $handler['type'];
            $handler = $handler['handler'];
        }
        $process->addSelectorAlias($name, $handler, $type);
    }
});

function forms() {
    return [
        'input' => [
            'type' => 'splat',
            'handler' => 'input[type=#(text)]',
        ],
        'checkbox' => 'input[type="checkbox"]',
        'radio' => 'input[type="radio"]',
        'file' => 'input[type="file"]',
        'image' => 'input[type="image"]',
        'password' => 'input[type="password"]',
        'submit' => 'input[type="submit"]',
        'text' => 'input[type="text"]',
    ];
}
