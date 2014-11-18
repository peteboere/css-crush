<!--{

"title": "Selector aliases"

}-->

Selector aliases can be useful for grouping together common selector chains for reuse.

They're defined with the `@selector` directive, and can be used anywhere you might use a pseudo class.


```crush
@selector heading :any(h1, h2, h3, h4, h5, h6);
@selector radio input[type="radio"];
@selector hocus :any(:hover, :focus);

/* Selector aliases with arguments */
@selector class-prefix :any([class^="#(0)"], [class*=" #(0)"]);
@selector col :class-prefix(-col);

.sidebar :heading {
  color: honeydew;
}

:radio {
  margin-right: 4px;
}

:col {
  float: left;
}

p a:hocus {
  text-decoration: none;
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

[class^="col-"],
[class*=" col-"] {
  border: 1px solid rgba(0,0,0,.5);
}

p a:hover,
p a:focus {
  text-decoration: none;
}
```

## Selector splatting

Selector splats are a special kind of selector alias that expand using passed arguments.

```crush
@selector-splat input input[type="#(text)"];

form :input(time, text, url, email, number) {
  border: 1px solid;
}
```

```css
form input[type="time"],
form input[type="text"],
form input[type="url"],
form input[type="email"],
form input[type="number"] {
  border: 1px solid;
}
```
