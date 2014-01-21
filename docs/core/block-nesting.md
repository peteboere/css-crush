<!--{

"title": "Nesting"

}-->

Block nesting is done with the `@in` directive. Especially useful for when you need to group lots of styles under a common selector prefix.

Note use of the parent selector `&`:

```crush2
@in .homepage {
  @in .content {
    p {
      font-size: 110%;
    }
  }
  &amp;.blue {
    color: powderblue;
  }
  .no-js &amp; {
    max-width: 1024px;
  }
}
```

```crush
.homepage .content p {
  font-size: 110%;
}
.homepage.blue {
  color: powderblue;
}
.no-js .homepage {
  max-width: 1024px;
}
```
