<!DOCTYPE html>
<head>
<?php

require_once '../CssCrush.php'; 
echo CssCrush::tag( CssCrush::$location . "/docs/styles.css" );

?>
</head>
<?php


$class = 'CssCrush';
$description = '';
$reflectionClass = new ReflectionClass( $class );

$description = $description ?
	$description : '(lib/' . basename( $reflectionClass->getFileName() ) . ')';

$str = <<<TPL
<div class="cssc-block CssCrush $class">
<h2 class="headline">$class <small>$description</small></h2>

TPL;

foreach ( $reflectionClass->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
	$comment = $method->getDocComment();
	if ( empty( $comment ) ) {
		continue;
	}
	$method_name = "$class::{$method->getName()}";
	$docObj = CssCrush_Doc::parseDocComment( $comment );
	$signature = CssCrush_Doc::createMethodSignature( $method_name, $method, $docObj );

	$str .= <<<TPL
<div class="cssc-row">
<h3>$method_name</h3>
<div class="description">
{$docObj->desc}
</div>
<div class="signature">
$signature
</div>
</div>
TPL;
}
$str .= "</div>\n";
echo $str;

?>



CssCrush Macros
CssCrush Aliases
CssCrush core api