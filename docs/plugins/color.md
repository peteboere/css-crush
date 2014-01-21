
Define custom color keywords.

Standard color keywords (blue, red etc.) cannot be overridden.

```crush
@color {
    acme-blue: s-adjust(blue -10);
    kolanut: #D0474E;
}

/* Alternative syntax */
@color vanilla #FBF7EC;


/* Usage is the same as with native color keywords */
p {
    color: vanilla;
    border: 1px solid acme-blue;
}
```
