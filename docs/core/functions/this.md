<!--{

"title": "this()"

}-->

Reference another property value from the same containing block.

Restricted to referencing properties that don't already reference other properties.

<code>this( *property-name*, *fallback* )</code>

## Params

* *`property-name`* Property name
* *`fallback`* A CSS value

## Returns

The referenced property value, or the fallback if it has not been set.


```css
.foo {
  width: this( height );
  margin-top: -( this( height ) / 2 )em;
  height: 100em;
}
```

********

```css
/* The following both fail because they create circular references. */
.bar {
  height: this( width );
  width: this( height );
}
```
