<?php
/**
  *
  * Formatter callbacks.
  *
  */
CssCrush::$config->formatters = array(
    'single-line' => 'csscrush__fmtr_single',
    'padded' => 'csscrush__fmtr_padded',
    'block' => 'csscrush__fmtr_block',
);

function csscrush__fmtr_single ($rule) {

    $EOL = CssCrush::$process->newline;
    if ($stub = $rule->tracingStub) {
        $stub .= $EOL;
    }

    $comments = implode('', $rule->comments);
    if ($comments) {
      $comments = "$EOL$comments";
    }
    $selectors = implode(", ", $rule->selectors);
    $block = implode("; ", $rule->declarations);
    return "$comments$stub$selectors { $block; }$EOL";
}

function csscrush__fmtr_padded ($rule, $padding = 40) {

    $EOL = CssCrush::$process->newline;
    if ($stub = $rule->tracingStub) {
        $stub .= $EOL;
    }

    $comments = implode('', $rule->comments);
    if ($comments) {
        $comments = "$EOL$comments";
    }

    $selectors = implode(", ", $rule->selectors);
    $block = implode("; ", $rule->declarations);

    if (strlen($selectors) > $padding) {
        $padding = str_repeat(' ', $padding);
        return "$comments$stub$selectors$EOL$padding { $block; }$EOL";
    }
    else {
        $selectors = str_pad($selectors, $padding);
        return "$comments$stub$selectors { $block; }$EOL";
    }
}

function csscrush__fmtr_block ($rule, $indent = '    ') {

    $EOL = CssCrush::$process->newline;
    if ($stub = $rule->tracingStub) {
        $stub .= $EOL;
    }

    $comments = implode('', $rule->comments);
    $selectors = implode(",$EOL", $rule->selectors);
    $block = implode(";$EOL$indent", $rule->declarations);
    return "$comments$stub$selectors {{$EOL}$indent$block;$EOL$indent}$EOL$EOL";
}
