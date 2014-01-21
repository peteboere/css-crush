<!--{

"title": "Fragments"

}-->

Fragments – defined and invoked with the <code>@fragment</code> directive – work in a similar way to [mixins](#core--mixins), except that they work at block level:

```crush
@fragment input-placeholder {
  #(1)::-webkit-input-placeholder { color: #(0); }
  #(1):-moz-placeholder           { color: #(0); }
  #(1)::placeholder               { color: #(0); }
  #(1).placeholder-state          { color: #(0); }
}

@fragment input-placeholder(#777, textarea);
```

```css
textarea::-webkit-input-placeholder { color: #777; }
textarea:-moz-placeholder           { color: #777; }
textarea::placeholder               { color: #777; }
textarea.placeholder-state          { color: #777; }
```
