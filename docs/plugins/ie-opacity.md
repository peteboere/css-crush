
Polyfill for opacity in IE < 9

```css
opacity: 0.45;
```

```css
opacity: 0.45;
-ms-filter: "alpha(opacity=45)";
*filter: alpha(opacity=45);
zoom: 1;
```