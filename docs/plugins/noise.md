
Functions for generating noise textures with SVG filters.

Supported in any browser that supports SVG filters (IE > 9 and most other browsers).


## noise() / turbulence()

Both functions work in the same way, the difference being `noise()` uses feTurbulence type 'fractalNoise' and `turbulence()` uses feTurbulence type 'turbulence'.

```syntax
noise/turbulence(
  [ <fill-color> || <size> ]?
  [, <frequency> <octaves>? <sharpness>? ]?
  [, <blend-mode> || <fade> ]?
  [, <color-filter> <color-filter-value> ]?
)
```

### Parameters

* **fill-color** - Any valid CSS color value.
* **size** - Pixel size of canvas in format WxH (e.g. 320x480).
* **frequency** - Number. Noise frequency; useful values are between 0 and 1.  X and Y frequencies can be specified by joining two numbers with a colon.
* **octaves** - Number. Noise complexity.
* **sharpness** - Noise sharpening; possible values "normal" and "sharpen"
* **blend-mode** - Blend mode for overlaying noise filter; possible values "normal", "multiply", "screen", "darken" and "lighten"
* **fade** - Ranged number (0-1). Opacity of noise effect.
* **color-filter** - Color filter type; possible values "hueRotate" and "saturate"
* **color-filter-value** - Mixed. For "hueRotate" a degree as number. For "saturate" a ranged number (0-1).

### Returns

A data-uri.

```css
/* Grainy noise with 50% opacity and de-saturated.
   Demonstrates the "default" keyword for skipping arguments. */
background-image: noise( slategray, default, .5, saturate 0 );
```

*******

```css
/* Cloud effect. */
background: noise( 700x700 skyblue, .01 4 normal, screen, saturate 0 );
```

*******

```css
/* Typical turbulence effect. */
background: turbulence();
```

*******

```css
/* Sand effect. */
background: turbulence( wheat 400x400, .35:.2 4 sharpen, normal, saturate .4 );
```
