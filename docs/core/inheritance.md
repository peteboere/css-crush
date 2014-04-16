<!--{

"title": "Rule inheritance"

}-->

By using the `@extend` directive and passing it a named ruleset or selector from any other rule you can share styles more effectively across a stylesheet.

[Abstract rules](#core--abstract) can be used if you just need to extend a generic set of declarations.

```crush
.negative-text {
  overflow: hidden;
  text-indent: -9999px;
}

.sidebar-headline {
  @extend .negative-text;
  background: url( headline.png ) no-repeat;
}
```

```css
.negative-text,
.sidebar-headline {
  overflow: hidden;
  text-indent: -9999px;
}

.sidebar-headline {
  background: url( headline.png ) no-repeat;
}
```

Inheritance is recursive:

```crush
.one { color: pink; }
.two { @extend .one; }
.three { @extend .two; }
.four { @extend .three; }
```

```css
.one, .two, .three, .four { color: pink; }
```

## Referencing by name

If you want to reference a rule without being concerned about later changes to the identifying selector use the `@name` directive:

```crush
.foo123 {
  @name foo;
  text-decoration: underline;
}

.bar {
  @include foo;
}
.baz {
  @extend foo;
}
```


## Extending with pseudo classes/elements

`@extend` arguments can adopt pseudo classes/elements by appending an exclamation mark:

```crush
.link-base {
  color: #bada55;
  text-decoration: underline;
}
.link-base:hover,
.link-base:focus {
  text-decoration: none;
}

.link-footer {
  @extend .link-base, .link-base:hover!, .link-base:focus!;
  color: blue;
}
```

```css
.link-base,
.link-footer {
  color: #bada55;
  text-decoration: underline;
}

.link-base:hover,
.link-base:focus,
.link-footer:hover,
.link-footer:focus {
  text-decoration: none;
}

.link-footer {
  color: blue;
}
```

The same outcome can also be achieved with an [Abstract rule](#core--abstract) wrapper to simplify repeated use:

```crush
.link-base {
  color: #bada55;
  text-decoration: underline;
}
.link-base:hover,
.link-base:focus {
  text-decoration: none;
}

@abstract link-base {
  @extend .link-base, .link-base:hover!, .link-base:focus!;
}

.link-footer {
  @extend link-base;
  color: blue;
}
```

