<!--{

"title": "Auto prefixing"

}-->

Vendor prefixes for properties, functions, @-rules and declarations are **automatically generated** – based on [trusted](http://caniuse.com) [sources](http://developer.mozilla.org/en-US/docs/CSS/CSS_Reference) – so you can maintain cross-browser support while keeping your source code clean and easy to maintain.


```crush
.foo {
  background: linear-gradient(to right, red, white);
}
```

```css
.foo {
  background: -webkit-linear-gradient(to right, red, white);
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
@keyframes bounce {
  50% {-webkit-transform: scale(1.4);
               transform: scale(1.4);}
}
```
