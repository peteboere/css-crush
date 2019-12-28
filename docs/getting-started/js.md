<!--{

"title": "JavaScript"

}-->

This preprocessor is written in PHP, so as prerequisite you will need to have PHP installed on your system to use the JS api.

```shell
npm install csscrush
```

All methods can take the standard options (camelCase) as the second argument.

```php
const csscrush = require('csscrush');

// Compile. Returns promise.
csscrush.file('./styles.css', {sourceMap: true});

// Compile string of CSS. Returns promise.
csscrush.string('* {box-sizing: border-box;}');

// Compile and watch file. Returns event emitter (triggers 'data' on compile).
csscrush.watch('./styles.css');
```
