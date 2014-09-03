<!--{

"title": "query()"

}-->

Copy a value from another rule.

<code>query( *target* [, *property-name* = default] [, *fallback*] )</code>

## Parameters

* *`target`* A rule selector, an abstract rule name or context keyword: `previous`, `next` (also `parent` and  `top` within nested structures)
* *`property-name`* The CSS property name to copy, or just `default` to pass over. Defaults to the calling property
* *`fallback`* A CSS value to use if the target property does not exist


## Returns

The referenced property value, or the fallback if it has not been set.


## Examples


```css
.foo {
  width: 40em;
  height: 100em;
}

.bar {
  width: query( .foo ); /* 40em */
  margin-top: query( .foo, height ); /* 100em */
  margin-bottom: query( .foo, default, 3em ); /* 3em */
}
```

Using context keywords:

```css
.foo {
  width: 40em;
  .bar {
    width: 30em;
    .baz: {
      width: query( parent ); /* 30em */
      .qux {
        width: query( top ); /* 40em */
      }
    }
  }
}
```
