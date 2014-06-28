<!--{

"title": "s-adjust()"

}-->

Adjust the saturation of a color value.

<code>s-adjust( *color*, *offset* )</code>

## Parameters

* *`color`* Any valid CSS color value
* *`offset`* The percentage to offset the color hue (percent mark optional)

## Returns

The modified color value.

## Examples

```css
/* Desaturate */
color: s-adjust( deepskyblue -100 );
```
