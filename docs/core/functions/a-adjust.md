<!--{

"title": "a-adjust()"

}-->

Manipulate the opacity (alpha channel) of a color value.

<code>a-adjust( *color*, *offset* )</code>

## Parameters

* *`color`* Any valid CSS color value
* *`offset`* The percentage to offset the color opacity

## Returns

The modified color value


## Examples

```css
/* Reduce color opacity by 10% */
color: a-adjust( rgb(50,50,0) -10 );
```
