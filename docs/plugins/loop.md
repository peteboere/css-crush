
For...in loops with lists and generator functions.

```crush
@for fruit in apple, orange, pear {
  .#(fruit) {
    background-image: url("images/#(fruit).jpg");
  }
}
```

```css
.apple { background-image: url(images/apple.jpg); }
.orange { background-image: url(images/orange.jpg); }
.pear { background-image: url(images/pear.jpg); }
```

```crush
@for base in range(2, 24) {
  @for i in range(1, #(base)) {
    .grid-#(i)-of-#(base) {
      width: math(#(i) / #(base) * 100, %);
    }
  }
}
```

```css
.grid-1-of-2 { width: 50%; }
.grid-2-of-2 { width: 100%; }
/*
    Intermediate steps ommited.
*/
.grid-23-of-24 { width: 95.83333%; }
.grid-24-of-24 { width: 100%; }
```

```crush
/* The last argument to color-range() is an integer
   specifying how many transition colors to generate
   between the color arguments. */
@for color in color-range(powderblue, deeppink, a-adjust(yellow, -80), 5) {
  .foo-#(loop.counter) {
    background-color: #(color);
  }
}
```

```css
.foo-1 { background-color: #b0e0e6; }
.foo-2 { background-color: #bdbed8; }
/*
    Intermediate steps ommited
*/
.foo-12 { background-color: rgba(255,216,25,.33); }
.foo-13 { background-color: rgba(255,255,0,.2); }
```
