
Functions for creating SVG gradients with a CSS gradient like syntax.

Primarily useful for supporting Internet Explorer 9.

## svg-linear-gradent()

Syntax is the same as [linear-gradient()](http://dev.w3.org/csswg/css3-images/#linear-gradient)

```syntax
svg-linear-gradent( [ <angle> | to <side-or-corner> ,]? <color-stop> [, <color-stop>]+ )
```

### Returns

A base64 encoded svg data-uri.

### Known issues

Color stops can only take percentage value offsets.

```css
background-image: svg-linear-gradient( to top left, #fff, rgba(255,255,255,0) 80% );
background-image: svg-linear-gradient( 35deg, red, gold 20%, powderblue );
```


## svg-radial-gradent()

Syntax is similar to but more limited than [radial-gradient()](http://dev.w3.org/csswg/css3-images/#radial-gradient)

```syntax
svg-radial-gradent( [ <origin> | at <position> ,]? <color-stop> [, <color-stop>]+ )
```

### Returns

A base64 encoded svg data-uri.

### Known issues

Color stops can only take percentage value offsets.
No control over shape - only circular gradients - however, the generated image can be stretched with background-size.

```css
background-image: svg-radial-gradient( at center, red, blue 50%, yellow );
background-image: svg-radial-gradient( 100% 50%, rgba(255,255,255,.5), rgba(255,255,255,0) );
```
