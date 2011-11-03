<?php
/**
 * Add opacity for IE8/9
 * 
 * Before: 
 *     opacity: 0.45;
 * 
 * After:
 *     opacity: 0.45;
 *     -ms-filter: progid:DXImageTransform.Microsoft.Alpha(Opacity=45);
 *     filter: alpha(opacity=45);
 */

CssCrush::addRuleMacro( 'csscrush_opacity' );

function csscrush_opacity ( CssCrush_Rule $rule ) {
    if ( $rule->propertyCount( 'opacity' ) < 1 ) {
        return;
    }
    $new_set = array();
    foreach ( $rule as $declaration ) {
        $new_set[] = $declaration;
        if ($declaration->property != 'opacity') {
            continue;
        }

        $opacity = (float)$declaration->value;
        $opacityX100 = round( $opacity * 100 );
        $new_set[] = $rule->createDeclaration( '-ms-filter', '"progid:DXImageTransform.Microsoft.Alpha(Opacity='.$opacityX100.')"' );
        $new_set[] = $rule->createDeclaration( 'filter', 'alpha(opacity='.$opacityX100.')' );
    }
    $rule->declarations = $new_set;
}