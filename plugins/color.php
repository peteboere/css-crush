<?php
/**
 * Custom color keywords
 *
 * @see docs/plugins/color.md
 */
namespace CssCrush;

\csscrush_plugin('color', function ($process) {
    $GLOBALS['CSSCRUSH_COLOR_PATT'] = null;
    $process->on('capture_phase1', 'CssCrush\color_capture');
    $process->on('declaration_preprocess', 'CssCrush\color');
});

function color(&$declaration) {
    if (isset($GLOBALS['CSSCRUSH_COLOR_PATT'])) {
        $declaration['value'] = preg_replace_callback($GLOBALS['CSSCRUSH_COLOR_PATT'], function ($m) {
            return new Color(Crush::$process->colorKeywords[$m['color_keyword']]);
        }, $declaration['value']);
    }
}

function color_capture($process) {

    $captured_keywords = $process->string->captureDirectives('color', array('singles' => true));

    if ($captured_keywords) {

        $native_keywords = Color::getKeywords();
        $custom_keywords = [];
        $process->colorKeywords = $native_keywords;

        foreach ($captured_keywords as $key => $value) {
            $value = $process->functions->apply($value);
            if (! isset($native_keywords[$key]) && $rgba = Color::parse($value)) {
                $custom_keywords[] = $key;
                $process->stat['colors'][$key] = new Color($rgba);
                $process->colorKeywords[$key] = $rgba;
            }
        }

        if ($custom_keywords) {
            $GLOBALS['CSSCRUSH_COLOR_PATT'] = Regex::make('~{{ LB }}(?<color_keyword>' .
                implode('|', $custom_keywords) . '){{ RB }}~iS');
        }
    }
}
