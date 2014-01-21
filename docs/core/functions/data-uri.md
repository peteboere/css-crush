<!--{

"title": "data-uri()"

}-->

Create a data-uri.

<code>data-uri( *url* )</code>

## Params

* *`url`* URL of an asset

`url` cannot be external, and must not be written with an http protocol prefix.

The following file extensions are supported: jpg, jpeg, gif, png, svg, svgz, ttf, woff


## Returns

The created data-uri as a string inside a CSS url().

```css
background: silver data-uri(../images/stripe.png);
```

```css
background: silver url(data:&lt;img-data&gt;);
```