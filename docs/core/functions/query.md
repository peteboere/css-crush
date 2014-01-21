<!--{

"title": "query()"

}-->

Copy a value from another rule.

<code>query( *reference* [, *property-name* = default] [, *fallback*] )</code>

## Params

* *`reference`* A CSS selector to match, or abstract rule name
* *`property-name`* The CSS property name to copy, or just 'default' to pass over. Defaults to the calling property
* *`fallback`* A CSS value to use if the target property does not exist


## Returns

The referenced property value, or the fallback if it has not been set.


```css
.foo {
  width: 40em;
  height: 100em;
}

.bar {
  width: query( .foo ); /* 40em */
  margin-top: query( .foo, height ); /* 100em */
  margin-right: query( .foo, top, auto ); /* auto */
  margin-bottom: query( .foo, default, 3em ); /* 3em */
}
```


