
Polyfill for the rem (root em) length unit.

No version of IE to date (IE <= 10) resizes text set with pixels though IE > 8 supports rem units which are resizeable.

* [Rem unit browser support](http://caniuse.com/#feat=rem)

## Conversion modes

### rem-fallback (default)

rem to px, with converted value as fallback.

```css
font-size: 1rem;
```

```css
font-size: 16px;
font-size: 1rem;
```

### px-fallback

px to rem, with original pixel value as fallback.

```css
font-size: 16px;
```

```css
font-size: 16px;
font-size: 1rem;
```

### convert

in-place px to rem conversion.

```css
font-size: 16px;
```

```css
font-size: 1rem;
```

`rem-fallback` is the default mode. To change the conversion mode set a variable named `rem__mode` with the mode name you want as its value.

To convert all length values, not just values of the font related properties, set a variable named `rem__all` with a value of `yes`.
