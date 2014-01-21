<!--{

"title": "Mixins"

}-->

Mixins make reusing small snippets of CSS much simpler. You define them with the `@mixin` directive.

Positional arguments via the argument function `#()` extend the capability of mixins for repurposing in different contexts.

```crush
@mixin display-font {
  font-family: "Arial Black", sans-serif;
  font-size: #(0); 
  letter-spacing: #(1);
}

/* Another mixin with default arguments */
@mixin blue-theme {
  color: #(0 navy);
  background-image: url("images/#(1 cross-hatch).png");
}

/* Applying the mixins */
.foo {
  @include display-font(100%, .1em), blue-theme;
}
```

```css
.foo {
  font-family: "Arial Black", sans-serif;
  font-size: 100%;
  letter-spacing: .1em;
  color: navy;
  background-image: url("images/cross-hatch.png");
}
```

## Skipping arguments

Mixin arguments can be skipped by using the **default** keyword:

```crush
@mixin display-font {
  font-size: #(0 100%);
  letter-spacing: #(1);
}

/* Applying the mixin skipping the first argument so the
   default value is used instead */
#foo {
  @include display-font(default, .3em);
}
```

Sometimes you may need to use the same positional argument more than once. In this case the default value only needs to be specified once:

```crush
@mixin square {
  width:  #(0 10px);
  height: #(0);
}

.foo {
  @include square;
}
```

```css
#foo {
  width:  10px;
  height: 10px;
}
```


## Mixing-in from other sources

Normal rules and [abstract rules](#core--abstract) can also be used as static mixins without arguments:

```crush
@abstract negative-text {
  text-indent: -9999px;
  overflow: hidden;
}

#main-content .theme-border {
  border: 1px solid maroon;
}

.foo {
  @include negative-text, #main-content .theme-border;
}
```
