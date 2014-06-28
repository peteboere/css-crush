<!--{

"title": "this()"

}-->

Reference another property value from the same containing block.

Restricted to referencing properties that don't already reference other properties.

<code>this( *property-name*, *fallback* )</code>

## Parameters

* *`property-name`* Property name
* *`fallback`* A CSS value

## Returns

The referenced property value, or the fallback if it has not been set.

## Examples

```css
.foo {
  width: this( height );
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
