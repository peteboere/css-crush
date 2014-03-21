
Bitmap image generator.

Requires the GD image library bundled with PHP.

```crush
/* Create square semi-opaque png. */
@canvas foo {
  width: 50;
  height: 50;
  fill: rgba(255, 0, 0, .5);
}

body {
  background: white canvas(foo);
}
```

*****

```crush
/* White to transparent east facing gradient with 10px
   margin and background fill. */
@canvas horz-gradient {
  width: #(0);
  height: 150;
  fill: canvas-linear-gradient(to right, #(1 white), #(2 rgba(255,255,255,0)));
  background-fill: powderblue;
  margin: 10;
}

/* Rectangle 300x150. */
body {
  background: canvas(horz-gradient, 300);
}
/* Flipped gradient, using canvas-data() to generate a data URI. */
.bar {
  background: canvas-data(horz-gradient, 100, rgba(255,255,255,0), white);
}
```

*****

```crush
/* Google logo resized to 400px width and given a sepia effect. */
@canvas sepia {
  src: url(http://www.google.com/images/logo.png);
  width: 400;
  canvas-filter: greyscale() colorize(45, 45, 0);
}

.bar {
  background: canvas(sepia);
}
```
