<!--{

"title": "Settings"

}-->

Plugins sometimes use __settings__ to configure their behaviour.

Settings can be specified as an [option](#api--options), or declared in CSS with block or single-line syntax:

```crush
@settings {
  dir: ltr;
}

@settings rem-mode px-fallback;
```
