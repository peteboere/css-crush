
Polyfill for the rem (root em) length unit.

No version of IE to date (IE <= 10) resizes text set with pixels though IE > 8 supports rem units which are resizeable.

* [Rem unit browser support](http://caniuse.com/#feat=rem)

## Settings

### rem-mode

Has the following possible values:

* `rem-fallback` (default) - rem to px, with converted value as fallback.

```css
font-size: 1rem;
```

```css
font-size: 16px;
font-size: 1rem;
```

* `px-fallback` - px to rem, with original pixel value as fallback.

```css
font-size: 16px;
```

```css
font-size: 16px;
font-size: 1rem;
```

* `convert` - in-place px to rem conversion.

```css
font-size: 16px;
```

```css
font-size: 1rem;
```

### rem-all

To convert all length values, not just values of the font related properties, set `rem-all` with a value of `yes`.
