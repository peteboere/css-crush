<!--{

"title": "Direct @import"

}-->

Files referenced with the `@import` directive are inlined directly to save on http requests. Relative URL paths in the CSS are also updated if necessary.

If you specify a media designation following the import URL — as per the CSS standard — the imported file content is wrapped in a `@media` block.


```crush
/* Standard CSS @import statements */
@import "print.css" print;
@import url( "small-screen.css" ) screen and ( max-width: 500px );
```

```css
@media print {
  /* Contents of print.css */
}
@media screen and ( max-width: 500px ) {
  /* Contents of small-screen.css */
}
```
