<?php
/**
 * Polyfill to auto-generate legacy flexbox syntaxes
 *
 * Works in conjunction with aliases to support legacy
 * flexbox (flexbox 2009) syntax with CR flexbox.
 *
 * @before
 *     display: flex;
 *     flex-flow: row-reverse wrap;
 *     justify-content: space-between;
 *
 * @after
 *     display: -webkit-box;
 *     display: -moz-box;
 *     display: -webkit-flex;
 *     display: -ms-flexbox;
 *     display: flex;
 *     -webkit-box-direction: reverse;
 *     -moz-box-direction: reverse;
 *     -webkit-box-orient: horizontal;
 *     -moz-box-orient: horizontal;
 *     -webkit-box-lines: wrap;
 *     -moz-box-lines: wrap;
 *     -webkit-flex-flow: row-reverse wrap;
 *     -ms-flex-flow: row-reverse wrap;
 *     flex-flow: row-reverse wrap;
 *     -webkit-box-pack: justify;
 *     -moz-box-pack: justify;
 *     -webkit-justify-content: space-between;
 *     -ms-flex-pack: justify;
 *     justify-content: space-between;
 *
 * @caveats
 *     Firefox's early flexbox implementation has several non-trivial issues:
 *     - With flex containers "display: -moz-box" generates an inline-block element,
 *       not a block level element as in other implementations.
 *       Suggested workaround is to set "width: 100%", in conjunction
 *       with "-moz-box-sizing: border-box" if padding is required.
 *     - The width of flex items can only be set in pixels.
 *     - Flex items cannot be justified. I.e. "-moz-box-pack: justify" does not work.
 *
 *     Firefox 20 will ship in April 2013 with an updated (and unprefixed) implementation
 *     of flexbox: https://developer.mozilla.org/en-US/docs/Firefox_20_for_developers
 */

CssCrush_Plugin::register('legacy-flexbox', array(
    'enable' => 'csscrush__enable_legacy_flexbox',
    'disable' => 'csscrush__disable_legacy_flexbox',
));

function csscrush__enable_legacy_flexbox () {
    CssCrush_Hook::add('rule_prealias', 'csscrush__legacy_flexbox');
}

function csscrush__disable_legacy_flexbox () {
    CssCrush_Hook::remove('rule_prealias', 'csscrush__legacy_flexbox');
}

function csscrush__legacy_flexbox (CssCrush_Rule $rule) {

    static $flex_related_props = array(
        'align-items' => true,
        'flex' => true,
        'flex-direction' => true,
        'flex-flow' => true,
        'flex-grow' => true,
        'flex-wrap' => true,
        'justify-content' => true,
        'order' => true,

        // The following properties have no legacy equivalent:
        //  - align-content
        //  - align-self
        //  - flex-shrink
        //  - flex-basis
    );

    $properties =& $rule->properties;
    $intersect_props = array_intersect_key($properties, $flex_related_props);

    // Checking for flex related properties or 'display:flex'.
    // First checking the display property as it's pretty common.
    if (isset($properties['display'])) {
        foreach ($rule->declarations as $declaration) {
            if ($declaration->property === 'display' &&
                ($declaration->value === 'flex' || $declaration->value === 'inline-flex')) {
                // Add 'display' to the intersected properties.
                $intersect_props['display'] = true;
                break;
            }
        }
    }

    // Bail early if the rule has no flex related properties.
    if (! $intersect_props) {
        return;
    }

    $declaration_aliases =& CssCrush::$process->aliases['declarations'];

    $stack = array();
    $rule_updated = false;

    foreach ($rule as $declaration) {

        $prop = $declaration->property;
        $value = $declaration->value;

        if (! isset($intersect_props[$prop])) {
            $stack[] = $declaration;
            continue;
        }

        switch ($prop) {

            // display:flex => display:-*-box.
            case 'display':
                if (
                    // Treat flex and inline-flex the same in this case.
                    ($value === 'flex' || $value === 'inline-flex') &&
                    isset($declaration_aliases['display']['box'])) {
                    foreach ($declaration_aliases['display']['box'] as $pair) {
                        $stack[] = new CssCrush_Declaration($pair[0], $pair[1]);
                        $rule_updated = true;
                    }
                }
                break;

            case 'align-items':
                $rule_updated = csscrush__flex_align_items($value, $stack);
                break;

            case 'flex':
                $rule_updated = csscrush__flex($value, $stack);
                break;

            case 'flex-direction':
                $rule_updated = csscrush__flex_direction($value, $stack);
                break;

            case 'flex-grow':
                $rule_updated = csscrush__flex_grow($value, $stack);
                break;

            case 'flex-wrap':
                // No browser seems to have implemented the box-lines property,
                // definitely not firefox.
                // - https://bugzilla.mozilla.org/show_bug.cgi?id=562073
                // - http://stackoverflow.com/questions/5010083/\
                //   css3-flex-box-specifying-multiple-box-lines-doesnt-work

                // $rule_updated = csscrush__flex_wrap($value, $stack);
                break;

            case 'justify-content':
                $rule_updated = csscrush__flex_justify_content($value, $stack);
                break;

            case 'order':
                $rule_updated = csscrush__flex_order($value, $stack);
                break;

            // Shorthand values.
            case 'flex-flow':

                // <‘flex-direction’> || <‘flex-wrap’>

                $args = explode(' ', $value);
                $direction = isset($args[0]) ? $args[0] : 'initial';
                $wrap = isset($args[1]) ? $args[1] : 'initial';

                $rule_updated = csscrush__flex_direction($direction, $stack);
                // $rule_updated = csscrush__flex_wrap($wrap, $stack);
                break;
        }

        // The existing declaration.
        $stack[] = $declaration;
    }

    // Re-assign if any updates have been made.
    if ($rule_updated) {
        $rule->setDeclarations($stack);
    }
}


function csscrush__flex_direction ($value, &$stack) {

    // flex-direction: row | row-reverse | column | column-reverse
    // box-orient:     horizontal | vertical | inline-axis | block-axis | inherit
    // box-direction:  normal | reverse | inherit

    $prop_aliases =& CssCrush::$process->aliases['properties'];

    static $directions = array(
        'row'            => 'normal',
        'row-reverse'    => 'reverse',
        'column'         => 'normal',
        'column-reverse' => 'reverse',
        'inherit'        => 'inherit',
        'initial'        => 'normal',
    );
    static $orientations = array(
        'row'            => 'horizontal',
        'row-reverse'    => 'horizontal',
        'column'         => 'vertical',
        'column-reverse' => 'vertical',
        'inherit'        => 'inherit',
    );
    $rule_updated = false;

    if (isset($prop_aliases['box-direction'])) {
        foreach ($prop_aliases['box-direction'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $directions[$value]);
            $rule_updated = true;
        }
    }
    if (isset($prop_aliases['box-orient'])) {
        foreach ($prop_aliases['box-orient'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $orientations[$value]);
            $rule_updated = true;
        }
    }
    return $rule_updated;
}


function csscrush__flex_justify_content ($value, &$stack) {

    // justify-content: flex-start | flex-end | center | space-between | space-around
    // box-pack:        start | end | center | justify

    $prop_aliases =& CssCrush::$process->aliases['properties'];

    static $positions = array(
        'flex-start'    => 'start',
        'flex-end'      => 'end',
        'center'        => 'center',
        'space-between' => 'justify',
        'space-around'  => 'justify',
    );
    $rule_updated = false;

    if (isset($prop_aliases['box-pack']) && isset($positions[$value])) {
        foreach ($prop_aliases['box-pack'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $positions[$value]);
            $rule_updated = true;
        }
    }
    return $rule_updated;
}


function csscrush__flex_align_items ($value, &$stack) {

    // align-items: flex-start | flex-end | center | baseline | stretch
    // box-align:   start | end | center | baseline | stretch

    $prop_aliases =& CssCrush::$process->aliases['properties'];

    static $positions = array(
        'flex-start'    => 'start',
        'flex-end'      => 'end',
        'center'        => 'center',
        'baseline'      => 'baseline',
        'stretch'       => 'stretch',
    );
    $rule_updated = false;

    if (isset($prop_aliases['box-align']) && isset($positions[$value])) {
        foreach ($prop_aliases['box-align'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $positions[$value]);
            $rule_updated = true;
        }
    }
    return $rule_updated;
}


function csscrush__flex_order ($value, &$stack) {

    // order:             <integer>
    // box-ordinal-group: <integer>

    $prop_aliases =& CssCrush::$process->aliases['properties'];

    // Bump value as box-ordinal-group requires a positive integer:
    // http://www.w3.org/TR/2009/WD-css3-flexbox-20090723/#displayorder
    //
    // Spec suggests a 'natural number' as valid value which Webkit seems
    // to interpret as integer > 0, whereas Firefox interprets as
    // integer > -1.
    $value = $value < 1 ? 1 : $value + 1;

    $rule_updated = false;
    if (isset($prop_aliases['box-ordinal-group'])) {
        foreach ($prop_aliases['box-ordinal-group'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $value);
            $rule_updated = true;
        }
    }
    return $rule_updated;
}


function csscrush__flex_wrap ($value, &$stack) {

    // flex-wrap: nowrap | wrap | wrap-reverse
    // box-lines: single | multiple

    $prop_aliases =& CssCrush::$process->aliases['properties'];

    static $wrap_behaviours = array(
        'nowrap'       => 'single',
        'wrap'         => 'multiple',
        'wrap-reverse' => 'multiple',
        'initial'      => 'single',
    );
    $rule_updated = false;

    if (isset($prop_aliases['box-lines']) && isset($wrap_behaviours[$value])) {
        foreach ($prop_aliases['box-lines'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $wrap_behaviours[$value]);
            $rule_updated = true;
        }
    }
    return $rule_updated;
}


function csscrush__flex ($value, &$stack) {

    // flex:     none | [ <'flex-grow'> <'flex-shrink'>? || <'flex-basis'> ]
    // box-flex: <number>

    $prop_aliases =& CssCrush::$process->aliases['properties'];

    // Normalize keyword arguments.
    static $keywords = array(
        'none'    => '0 0 auto',
        'auto'    => '1 1 auto',
        'initial' => '0 1 auto',
    );
    if (isset($keywords[$value])) {
        $value = $keywords[$value];
    }

    // Grow (first value) not have a unit to avoid being interpreted as a basis:
    // https://developer.mozilla.org/en-US/docs/CSS/flex
    $grow = 1;
    if (preg_match('~^(\d*\.?\d+) ~', $value, $m)) {
        $grow = $m[1];
    }

    $rule_updated = false;
    if (isset($prop_aliases['box-flex'])) {
        foreach ($prop_aliases['box-flex'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $grow);
            $rule_updated = true;
        }
    }
    return $rule_updated;
}


function csscrush__flex_grow ($value, &$stack) {

    // flex-grow: <number>
    // box-flex:  <number>

    $prop_aliases =& CssCrush::$process->aliases['properties'];

    $rule_updated = false;
    if (isset($prop_aliases['box-flex'])) {
        foreach ($prop_aliases['box-flex'] as $prop_alias) {
            $stack[] = new CssCrush_Declaration($prop_alias, $value);
            $rule_updated = true;
        }
    }
    return $rule_updated;
}

