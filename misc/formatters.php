<?php
/**
  *
  * Formatter callbacks.
  *
  */
namespace CssCrush;

Crush::$config->formatters = array(
    'single-line' => 'CssCrush\fmtr_single',
    'padded' => 'CssCrush\fmtr_padded',
    'block' => 'CssCrush\fmtr_block',
);

function fmtr_single($rule) {

    $EOL = Crush::$process->newline;

    $selectors = $rule->selectors->join(', ');
    $block = $rule->declarations->join('; ');
    return "$selectors { $block; }$EOL";
}

function fmtr_padded($rule, $padding = 40) {

    $EOL = Crush::$process->newline;

    $selectors = $rule->selectors->join(', ');
    $block = $rule->declarations->join('; ');

    if (strlen($selectors) > $padding) {
        $padding = str_repeat(' ', $padding);
        return "$selectors$EOL$padding { $block; }$EOL";
    }
    else {
        $selectors = str_pad($selectors, $padding);
        return "$selectors { $block; }$EOL";
    }
}

function fmtr_block($rule, $indent = '    ') {

    $EOL = Crush::$process->newline;

    $selectors = $rule->selectors->join(",$EOL");
    $block = $rule->declarations->join(";$EOL$indent");
    return "$selectors {{$EOL}$indent$block;$EOL$indent}$EOL";
}
