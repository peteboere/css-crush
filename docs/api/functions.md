<!--{

"title": "API functions"

}-->

## csscrush_file()

Process CSS file and return the compiled file URL.

<code>csscrush_file( string $file [, array [$options](#api--options) ] )</code>


***************

## csscrush_tag()

Process CSS file and return an html `link` tag with populated href.

<code>csscrush_tag( string $file [, array [$options](#api--options) [, array $tag\_attributes ]] )</code>


***************

## csscrush_inline()

Process CSS file and return CSS as text wrapped in html `style` tags.

<code>csscrush_inline( string $file [, array [$options](#api--options) [, array $tag\_attributes ]] )</code>


***************

## csscrush_string()

Compile a raw string of CSS string and return it.

<code>csscrush_string( string $string [, array [$options](#api--options) ] )</code>


***************

## csscrush_get()

Retrieve a config setting or option default.

`csscrush_get( string $object_name, string $property )`

### Parameters

 * `$object_name`  Name of object you want to inspect: 'config' or 'options'.
 * `$property`


***************

## csscrush_set()

Set a config setting or option default.

`csscrush_set( string $object_name, mixed $settings )`

### Parameters

 * `$object_name`  Name of object you want to modify: 'config' or 'options'.
 * `$settings`  Associative array of keys and values to set, or callable which argument is the object specified in `$object_name`.


***************

## csscrush_plugin()

Register a plugin.

`csscrush_plugin( string $name, callable $callback )`


***************

## csscrush_stat()

Get compilation stats from the most recent compiled file.

`csscrush_stat()`
