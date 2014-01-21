
Polyfill to auto-generate legacy flexbox syntaxes.

Works in conjunction with aliases to support legacy flexbox (flexbox 2009) syntax with modern flexbox.

```css
display: flex;
flex-flow: row-reverse wrap;
justify-content: space-between;
```

```css
display: -webkit-box;
display: -moz-box;
display: -webkit-flex;
display: -ms-flexbox;
display: flex;
-webkit-box-direction: reverse;
-moz-box-direction: reverse;
-webkit-box-orient: horizontal;
-moz-box-orient: horizontal;
-webkit-box-lines: wrap;
-moz-box-lines: wrap;
-webkit-flex-flow: row-reverse wrap;
-ms-flex-flow: row-reverse wrap;
flex-flow: row-reverse wrap;
-webkit-box-pack: justify;
-moz-box-pack: justify;
-webkit-justify-content: space-between;
-ms-flex-pack: justify;
justify-content: space-between;
```

### Caveats

Firefox's early flexbox implementation (Firefox < 22) has several non-trivial issues:

* With flex containers `display: -moz-box` generates an inline-block element, not a block level element as in other implementations. Suggested workaround is to set `width: 100%`, in conjunction with `box-sizing: border-box` if padding is required.
* The width of flex items can only be set in pixels.
* Flex items cannot be justified. I.e. `-moz-box-pack: justify` does not work.
