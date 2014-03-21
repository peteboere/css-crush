
Define and embed simple SVG elements, paths and effects inside CSS


```crush
@svg foo {
  type: star;
  star-points: #(0 5);
  radius: 100 50;
  margin: 20;
  stroke: black;
  fill: red;
  fill-opacity: .5;
}

/* Embed SVG with svg() function (generates an svg file). */
body {
  background: svg(foo);
}
/* As above but a 3 point star creating a data URI instead of a file. */
body {
  background: svg-data(foo, 3);
}
```

*******

```crush
/* Using path data and stroke styles to create a plus sign. */
@svg plus {
  d: "M0,5 h10 M5,0 v10";
  width: 10;
  height: 10;
  stroke: white;
  stroke-linecap: round;
  stroke-width: 2;
}
```


*******

```crush
/* Skewed circle with radial gradient fill and drop shadow. */
@svg circle {
  type: circle;
  transform: skewX(30);
  diameter: 60;
  margin: 20;
  fill: svg-radial-gradient(at top right, gold 50%, red);
  drop-shadow: 2 2 0 rgba(0,0,0,1);
}
```

*******

```crush
/* 8-sided polygon with an image fill.
   Note: images usually have to be converted to data URIs, see known issues below. */
@svg pattern {
  type: polygon;
  sides: 8;
  diameter: 180;
  margin: 20;
  fill: pattern(data-uri(kitten.jpg), scale(1) translate(-100 0));
  fill-opacity: .8;
}
```


### Known issues

Firefox [does not allow linked images](https://bugzilla.mozilla.org/show_bug.cgi?id=628747#c0) (or other svg) when svg is in "svg as image" mode.

