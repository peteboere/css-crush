<!--{

"title": "JavaScript"

}-->

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
