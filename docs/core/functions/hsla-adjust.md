<!--{

"title": "hsla-adjust()"

}-->

Manipulate the hue, saturation, lightness and opacity of a color value.

<code>hsla-adjust( *color*, *hue-offset*, *saturation-offset*, *lightness-offset*, *alpha-offset* )</code>

## Parameters

* *`color`* Any valid CSS color value
* *`hue-offset`* The percentage to offset the color hue
* *`saturation-offset`* The percentage to offset the color saturation
* *`lightness-offset`* The percentage to offset the color lightness
* *`alpha-offset`* The percentage to offset the color opacity

## Returns

The modified color value.

## Examples

```css
color: hsla-adjust( #f00 0 5 5 -10 );
```