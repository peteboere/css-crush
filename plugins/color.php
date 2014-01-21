<?php
/**
 * Custom color keywords
 *
 * @see docs/plugins/color.md
 */
namespace CssCrush;

Plugin::register('color', array(
    'enable' => function () {
        $GLOBALS['CSSCRUSH_COLOR_PATT'] = null;
        Crush::$process->hooks->add('capture_phase1', 'CssCrush\color_capture');
        Crush::$process->hooks->add('declaration_preprocess', 'CssCrush\color');
    },
    'disable' => function () {
        Crush::$process->hooks->remove('capture_phase1', 'CssCrush\color_capture');
        Crush::$process->hooks->remove('declaration_preprocess', 'CssCrush\color');
    },
));


function color(&$declaration) {
    if (isset($GLOBALS['CSSCRUSH_COLOR_PATT'])) {
        $declaration['value'] = preg_replace_callback($GLOBALS['CSSCRUSH_COLOR_PATT'], function ($m) {
            return new Color(Crush::$process->colorKeywords[$m['color_keyword']]);
        }, $declaration['value']);
    }
}

function color_capture($process) {

    $patt = Regex::make('~@color(?:\s*{{ block }}|\s+(?<name>{{ ident }})\s+(?<value>[^;]+)\s*;)~iS');
    $captured_keywords = array();

    $process->stream->pregReplaceCallback($patt, function ($m) use (&$captured_keywords) {
        if (isset($m['name'])) {
            $captured_keywords[strtolower($m['name'])] = $m['value'];
        }
        else {
            $captured_keywords = DeclarationList::parse($m['block_content'], array(
                'keyed' => true,
                'lowercase_keys' => true,
                'flatten' => true,
            )) + $captured_keywords;
        }

        return '';
    });


    if ($captured_keywords) {

        $native_keywords = Color::getKeywords();
        $custom_keywords = array();
        Crush::$process->colorKeywords = $native_keywords;

        foreach ($captured_keywords as $key => $value) {
            $value = Functions::executeOnString($value);
            if (! isset($native_keywords[$key]) && $rgba = Color::parse($value)) {
                $custom_keywords[] = $key;
                Crush::$process->stat['colors'][$key] = new Color($rgba);
                Crush::$process->colorKeywords[$key] = $rgba;
            }
        }

        if ($custom_keywords) {
            $GLOBALS['CSSCRUSH_COLOR_PATT'] = Regex::make('~{{ LB }}(?<color_keyword>' .
                implode('|', $custom_keywords) . '){{ RB }}~iS');
        }
    }
}
