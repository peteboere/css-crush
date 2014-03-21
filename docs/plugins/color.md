
Define custom color keywords.

Standard color keywords (e.g. blue, red) cannot be overridden.

```crush
@color {
  acme-blue: s-adjust(blue -10);
  kolanut: #D0474E;
}

@color vanilla #FBF7EC;


/* Usage is the same as with native color keywords */
p {
  color: vanilla;
  border: 1px solid acme-blue;
}
```
