<!--{

"title": "Variables"

}-->

Declare variables in your CSS with a `@define` directive and use them with the `$()` function.

Variables can also be injected at runtime with the [vars option](#api--options).


```crush
/* Defining variables */
@define {
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
