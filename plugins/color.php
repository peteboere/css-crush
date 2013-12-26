<?php
/**
 * Custom color keywords
 */
namespace CssCrush;

Plugin::register('color', array(
    'enable' => function () {
        Hook::add('capture_phase1', 'CssCrush\color_capture');
        Hook::add('declaration_preprocess', 'CssCrush\color');
    },
    'disable' => function () {
        Hook::remove('capture_phase1', 'CssCrush\color_capture');
        Hook::remove('declaration_preprocess', 'CssCrush\color');
    },
));


function color(&$declaration) {
    if (defined('CSSCRUSH_COLOR_PATT')) {
        $declaration['value'] = preg_replace_callback(CSSCRUSH_COLOR_PATT, function ($m) {
            return new Color(Crush::$process->colorKeywords[$m['color_keyword']]);
        }, $declaration['value']);
    }
}

function color_capture($process) {

    $color_directive_patt = Regex::make('~@color(?:\s*{{ block }}|\s+(?<name>{{ ident }})\s+(?<value>[^;]+)\s*;)~iS');
    $captured_keywords = array();

    $process->stream->pregReplaceCallback($color_directive_patt, function ($m) use (&$captured_keywords) {

        if (! isset($m['name'])) {
            $pairs = array_change_key_case(DeclarationList::parse($m['block_content'], array(
                'keyed' => true,
                'flatten' => true,
            )));
        }
        else {
            $pairs = array(strtolower($m['name']) => $m['value']);
        }

        $captured_keywords = $pairs + $captured_keywords;

        return '';
    });


    if ($captured_keywords) {

        $native_keywords = Color::getKeywords();
        $custom_keywords = array();
        Crush::$process->colorKeywords = $native_keywords;

        foreach ($captured_keywords as $key => $value) {
            $value = Functions::executeOnString($value);
            if (! isset($native_keywords[$key]) && $rgba = Color::parse($value)) {
                $custom_keywords[$key] = $rgba;
                Crush::$process->stat['colors'][$key] = new Color($rgba);
                Crush::$process->colorKeywords[$key] = $rgba;
            }
        }

        if ($custom_keywords) {
            define('CSSCRUSH_COLOR_PATT', Regex::make('~{{ LB }}(?<color_keyword>' .
                implode('|', array_keys($custom_keywords)) . '){{ RB }}~iS'));
        }
    }
}
