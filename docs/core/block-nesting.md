<!--{

"title": "Nesting"

}-->

Rules can be nested to avoid repetitive typing when scoping to a common parent selector.

```crush
.homepage {
  color: #333;
  background: white;
  .content {
    p {
      font-size: 110%;
    }
  }
}
```

```css
.homepage {
  color: #333;
  background: white;
}
.homepage .content p {
  font-size: 110%;
}
```

## Parent referencing

Sometimes when nesting it can be convenient to use the parent selector in different ways. The parent reference symbol `&` can be used for this.

```crush
.homepage {
  .no-js & {
    p {
      font-size: 110%;
    }
  }
}
```

```css
.no-js .homepage p {
  font-size: 110%;
}
```
