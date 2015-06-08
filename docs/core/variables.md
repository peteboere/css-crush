<!--{

"title": "Variables"

}-->

Declare variables in your CSS with a `@set` directive and use them with the `$()` function.

Variables can also be injected at runtime with the [vars option](#api--options).


```crush
/* Defining variables */
@set {
  dark: #333;
  light: #F4F2E2;
  smaller-screen: screen and (max-width: 800px);
}

/* Using variables */
@media $(smaller-screen) {
  ul, p {
    color: $(dark);
    /* Using a fallback value with an undefined variable */
    background-color: $(accent-color, #ff0);
  }
}
```

*******

```css
/* String interpolation */
.username::before {
  content: "$(greeting)";
}
```

## Conditionals

Sections of CSS can be included and excluded on the basis of variable existence with the `@ifset` directive:

```crush
@set foo #f00;
@set bar true;

@ifset foo {
  p {
    color: $(foo);
  }
}

p {
  font-size: 12px;
  @ifset not foo {
    line-height: 1.5;
  }
  @ifset bar(true) {
    margin-bottom: 5px;
  }
}
```