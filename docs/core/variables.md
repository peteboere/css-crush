<!--{

"title": "Variables"

}-->

Declare variables in your CSS with a `@define` directive and apply them using the `$()` function.

Variables can be injected at runtime with the [vars option](#api--options).


```crush
/* Defining variables */
@define {
  helvetica: "Helvetica Neue", "Helvetica", "Arial", sans-serif;
  theme-bg-color: #88CDEA;
  theme-fg-color: #F4F2E2;
  breakpoint-1: 960px;
}

/* Applying variables */
@media only screen and (max-width: $(breakpoint-1)) {
  ul, p {
    color: $(theme-fg-color);
    /* Specifying a fallback value */
    background-color: $(accent-color #ff0);
  }
}
```

*******

```css
/* Interpolation */
.username::before {
  content: "$(lang-greeting)";
}
```

## Conditionals

Sections of CSS can be included and excluded on the basis of variable existence with the `@ifdefine` directive:

```crush
@define foo #f00;

@ifdefine foo {
    p {
        color: $(foo);
    }
}

p {
    font-size: 12px;
    @ifdefine not foo {
        line-height: 1.5;
    }
}
```
