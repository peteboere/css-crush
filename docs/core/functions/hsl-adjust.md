<!--{

"title": "hsl-adjust()"

}-->

Manipulate the hue, saturation and lightness of a color value

<code>hsl-adjust( *color*, *hue-offset*, *saturation-offset*, *lightness-offset*  )</code>

## Params

* *`color`* Any valid CSS color value
* *`hue-offset`* The percentage to offset the color hue
* *`saturation-offset`* The percentage to offset the color saturation
* *`lightness-offset`* The percentage to offset the color lightness

## Returns

The modified color value

```css
/* Lighten and increase saturation */
color: hsl-adjust( red 0 5 5 );
```
