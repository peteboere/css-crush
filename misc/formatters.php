<?php
/**
  *
  * Formatter callbacks.
  *
  */
namespace CssCrush;

CssCrush::$config->formatters = array(
    'single-line' => 'CssCrush\fmtr_single',
    'padded' => 'CssCrush\fmtr_padded',
    'block' => 'CssCrush\fmtr_block',
);

function fmtr_single($rule) {

    $EOL = CssCrush::$process->newline;

    $selectors = implode(", ", $rule->selectors);
    $block = implode("; ", $rule->declarations);
    return "$selectors { $block; }$EOL";
}

function fmtr_padded($rule, $padding = 40) {

    $EOL = CssCrush::$process->newline;

    $selectors = implode(", ", $rule->selectors);
    $block = implode("; ", $rule->declarations);

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

    $EOL = CssCrush::$process->newline;

    $selectors = implode(",$EOL", $rule->selectors);
    $block = implode(";$EOL$indent", $rule->declarations);
    return "$selectors {{$EOL}$indent$block;$EOL$indent}$EOL";
}
