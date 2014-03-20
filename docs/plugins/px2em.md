
Functions for converting pixel values into `em` (__px2em__) or `rem` (__px2rem__) values

For both functions the optional second argument is base font-size for calculation though usually not required when converting pixel to rem.


## Settings

### px2em-base

The default base pixel value (16px by default) used for px2em conversion.

### px2rem-base

The default base pixel value (16px by default) used for px2rem conversion.


```css
font-size: px2em(11 13);
font-size: px2rem(16);
```

```css
font-size: .84615em;
font-size: 1rem;
```
