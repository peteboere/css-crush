<!--{

"title": "Selector grouping"

}-->

Selector grouping with the `:any` pseudo class (modelled after CSS4 :matches) simplifies the creation of complex selector chains.

```crush
:any( .sidebar, .block ) a:any( :hover, :focus ) {
  color: lemonchiffon;
}
```

```css
.block a:hover,
.block a:focus,
.sidebar a:hover,
.sidebar a:focus {
  color: lemonchiffon;
}
```
