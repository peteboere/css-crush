<!--{

"title": "Selector aliases"

}-->

Selector aliases can be useful for grouping together common selector chains for reuse.

They're  defined with the `@selector-alias` directive, and can be used anywhere you might use a psuedo class.


```crush
/* Defining selector aliases */
@selector-alias heading :any(h1, h2, h3, h4, h5, h6);
@selector-alias radio input[type="radio"];

/* Selector alias with arguments */
@selector-alias class-prefix :any([class^="#(0)"], [class*=" #(0)"]);

.sidebar :heading {
  color: honeydew;
}

:radio {
  margin-right: 4px;
}

:class-prefix(button) {
  border: 1px solid rgba(0,0,0,.5);
}
```

```css
.sidebar h1, .sidebar h2,
.sidebar h3, .sidebar h4,
.sidebar h5, .sidebar h6 {
  color: honeydew;
}

input[type="radio"] {
  margin-right: 4px;
}

[class^="button"],
[class*=" button"] {
  border: 1px solid rgba(0,0,0,.5);
}
```
