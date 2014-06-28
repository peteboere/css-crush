<!--{

"title": "percent()"

}-->

Calculate a percentage value based on two given values.

<code>percent( *value1*, *value2* [, *precision* = 5] )</code>

## Parameters

* *`value1`* Number
* *`value2`* Number
* *`precision`* Integer The number of decimal places to round to. Defaults to 5

## Returns

*`value1`* as a percentage of *`value2`*.

## Examples

```css
width: percent( 20, 960 );
```

```css
width: 2.08333%;
```