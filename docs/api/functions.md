<!--{

"title": "API functions"

}-->

## csscrush_file()

Process host CSS file and return the compiled file URL.

<code>csscrush_file( string $file [, array [$options](#api--options) ] )</code>


## csscrush_tag()

Process host CSS file and return an HTML link tag with populated href.

<code>csscrush_tag( string $file [, array [$options](#api--options) [, array $attributes ]] )</code>


## csscrush_inline()

Process host CSS file and return CSS as text wrapped in html `style` tags.

<code>csscrush_inline( string $file [, array [$options](#api--options) [, array $attributes ]] )</code>


## csscrush_string()

Compile a raw string of CSS string and return it.

<code>csscrush_string( string $string [, array [$options](#api--options) ] )</code>


## csscrush_stat()

Retrieve statistics from the most recent compiled file. Current available stats: selector_count, rule_count, compile_time and errors.

`csscrush_stat()`


## csscrush_version()

Get the library version.

`csscrush_version()`


## csscrush_get()

Retrieve a config setting or option default.

 * `$object_name`  Name of object you want to inspect: 'config' or 'options'.
 * `$property`

`csscrush_get( string $object_name, string $property )`


## csscrush_set()

Set a config setting or option default.

 * `$object_name`  Name of object you want to modify: 'config' or 'options'.
 * `$settings`  Assoc array of keys and values to set, or callable which argument is the object specified in `$object_name`.

`csscrush_set( string $object_name, mixed $settings )`
