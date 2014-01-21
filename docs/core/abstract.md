<!--{

"title": "Abstract rules"

}-->

Abstract rules are generic rules that can be [extended](#core--inheritance) with the `@extend` directive or mixed in (without arguments) like regular [mixins](#core--mixins) with the `@include` directive.

```crush
@abstract ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
@abstract heading {
  font: bold 1rem serif;
  letter-spacing: .1em;
}

.foo {
  @extend ellipsis;
  display: block;
}
.bar {
  @extend ellipsis;
  @include heading;
}
```

```css
.foo,
.bar {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.foo {
  display: block;
}
.bar {
  font: bold 1rem serif;
  letter-spacing: .1em;
}
```
