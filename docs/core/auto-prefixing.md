<!--{

"title": "Auto prefixing"

}-->

Vendor prefixes for properties, functions, @-rules and even full declarations are **automatically generated** – based on [trusted](http://caniuse.com) [sources](http://developer.mozilla.org/en-US/docs/CSS/CSS_Reference) – so you can maintain cross-browser support while keeping your source code clean and easy to maintain.

In some cases (e.g. CSS3 gradients) final syntax is incompatible with older prefixed syntax. In these cases the old syntax is polyfilled so you can use the correct syntax while preserving full support for older implementations.

```crush
.foo {
  background: linear-gradient(to right, red, white);
}
```

```css
.foo {
  background: -webkit-linear-gradient(left, red, white);
  background: -moz-linear-gradient(left, red, white);
  background: linear-gradient(to right, red, white);
}
```


```crush
@keyframes bounce {
  50% { transform: scale(1.4); }
}
```

```css
@-webkit-keyframes bounce {
  50% {-webkit-transform: scale(1.4);
               transform: scale(1.4);}
}
@-moz-keyframes bounce {
  50% {-moz-transform: scale(1.4);
            transform: scale(1.4);}
}
@keyframes bounce {
  50% {-webkit-transform: scale(1.4);
          -moz-transform: scale(1.4);
           -ms-transform: scale(1.4);
               transform: scale(1.4);}
}
```
