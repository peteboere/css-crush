CSS Crush
=====

CSS Crush is an extensible PHP based CSS preprocessor that aims to alleviate many of the hacks and workarounds necessary in modern CSS development.


Overview
===================================

http://the-echoplex.net/csscrush


Quick start
===================================

    <?php
    
    require_once 'CssCrush/CssCrush.php';
    $global_css = CssCrush::file( '/css/global.css' );
    
    ?>
    
    <link rel="stylesheet" type="text/css" href="<?php echo $global_css; ?>" />


Submitting bugs
===================================

If you think you've found a bug, please visit the Issue tracker — https://github.com/peteboere/css-crush/issues — and create an issue explaining the problem and expected result.


Submitting patches
===================================

To contribute code and bug fixes fork this project on Github, make changes to the code in your fork, and then send a "pull request" to be reviewed for inclusion.
