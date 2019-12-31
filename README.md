[![Build Status](https://travis-ci.org/peteboere/css-crush.svg)](https://travis-ci.org/peteboere/css-crush)

<img src="http://the-echoplex.net/csscrush/images/css-crush-external.svg?v=2" alt="Logo"/>

A CSS preprocessor designed to enable a modern and uncluttered CSS workflow.

* Automatic vendor prefixing
* Variables
* Import inlining
* Nesting
* Functions (color manipulation, math, data-uris etc.)
* Rule inheritance (@extends)
* Mixins
* Minification
* Lightweight plugin system
* Source maps

See the [docs](http://the-echoplex.net/csscrush) for full details.

********************************

## Setup (PHP)

If you're using [Composer](http://getcomposer.org) you can use Crush in your project with the following line in your terminal:

```shell
composer require css-crush/css-crush:dev-master
```

If you're not using Composer yet just download the library into a convenient location and require the bootstrap file:

```php
<?php require_once 'path/to/CssCrush.php'; ?>
```

## Basic usage (PHP)

```php
<?php

echo csscrush_tag('css/styles.css');

?>
```

Compiles the CSS file and outputs the following link tag:

```html
<link rel="stylesheet" href="css/styles.crush.css" media="all" />
```

There are several other [functions](http://the-echoplex.net/csscrush#api) for working with files and strings of CSS:

* `csscrush_file($file, $options)` - Returns a URL of the compiled file.
* `csscrush_string($css, $options)` - Compiles a raw string of css and returns the resulting css.
* `csscrush_inline($file, $options, $tag_attributes)` - Returns compiled css in an inline style tag.

There are a number of [options](http://the-echoplex.net/csscrush#api--options) available for tailoring the output, and a collection of bundled [plugins](http://the-echoplex.net/csscrush#plugins) that cover many workflow issues in contemporary CSS development.

********************************

## Setup (JS)

```shell
npm install csscrush
```

## Basic usage (JS)

```js
// All methods can take the standard options (camelCase) as the second argument.
const csscrush = require('csscrush');

// Compile. Returns promise.
csscrush.file('./styles.css', {sourceMap: true});

// Compile string of CSS. Returns promise.
csscrush.string('* {box-sizing: border-box;}');

// Compile and watch file. Returns event emitter (triggers 'data' on compile).
csscrush.watch('./styles.css');
```

********************************

## Contributing

If you think you've found a bug please create an [issue](https://github.com/peteboere/css-crush/issues) explaining the problem and expected result.

Likewise, if you'd like to request a feature please create an [issue](https://github.com/peteboere/css-crush/issues) with some explanation of the requested feature and use-cases.

[Pull requests](https://help.github.com/articles/using-pull-requests) are welcome, though please keep coding style consistent with the project (which is based on [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)).


## Licence

MIT
