1.10 (18th May 2013)
----
* Added SVG plugin for defining and generating SVG files/data URIs in CSS.
* Added Canvas plugin for image generation and manipulation (requires GD extension).
* Added rem and px2em plugins.
* Added ease plugin for expanded easing keywords.
* Command line utility now has a `--watch` option for automatic compiling when a file is updated.
* `vendor_target` option now accepts an array of targets.
* Added `@name` in-rule directive for more robust rule referencing.
* Added grouping for function aliases so multiple related functions (e.g. gradients) can now be
  applied to one value.
* Rule references previously looked for the closest previous match. This behaviour has been changed
  to a 'last wins' match to be more consistent with the way CSS works. This may affect users of `@extend`
  or the `query()` function.
* Added `-i` alias to `--file` option for the command line utility.
* Removed data-* properties.
* Nested rules that use the parent symbol (&) can now work in conjunction with the rooting symbol (^).
* Fixed issue with empty imported files not registering.
* Various bug fixes.

1.9.1 (31th January 2013)
-----
* Added noise plugin (noise/texture generating functions).
* Resolved issues #42 and #43.
* Fixed command line context option.
* Fixed error notice with no enabled plugins in Plugins.ini file.
* Updated aliases file.

1.9 (12th January 2013)
---
* Added flexbox aliases for both 2009 and 2012 edition specs.
* Added a legacy-flexbox plugin for auto-generating the flexbox 2009 spec equivilant properties.
* Updated selector aliases to take arguments at runtime.
* Updated plugin API to use distinct "enable" and "disable" handlers.
* `disable` option is now resolved before the `enable` option so you can easily disable all plugins
  and then specify the plugins you want to apply.
* Added functions API for defining custom functions inside plugins.
* Improved gradient function aliasing to handle new angle keywords (to left, at center, etc.).
* Added svg-gradients plugin for simulating CSS3 gradients with data-uris.
* Added `formatter` option for un-minified output. Possible values (custom formatters can also be defined):
    * "block" (default) - Rules are block formatted.
    * "single-line" - Rules are printed in single lines.
    * "padded" - Rules are printed in single lines with right padded selectors.
  Custom formatters can also be defined.
* Added `newlines` option to set the style of newlines in output. Possible values:
    * "use-platform" (default)
    * "unix"
    * "windows" or "win"
* Updated command line utility to use the new options.
* Property/value aliases expanded and renamed as declaration aliases.
* Classes now loaded via an autoloader, also some other refactoring for moving towards PSR-0 compliance.

1.8 (13th November 2012)
---
* Added selector aliasing with the `@selector-alias` directive.
* Added `output_dir` option for specifying the destination of compiled files.
* Added `doc_root` option for working around problems with server aliases or path rewrites.
* Added viewport @-rule aliases.
* `debug` option renamed to `minify`; `debug` option will still work as before but is deprecated.
* `minify` option takes an optional array of advanced minification parameters. Possible values:
    * `colors`
* Expanded `trace` option to take an optional array of log parameters. Possible values:
    * `stubs`
    * `selector_count`
    * `errors`
    * `compile_time`
* Added `CssCrush::stat` method to retrieve logged parameters.
* Improved cross OS support.
* Improved minification.
* Major refactoring.

1.7 (28th September 2012)
---
* Added `trace` option to output SASS compatible debug-info stubs for use with tools like FireSass.
* Added `@ifdefine` directive for dynamically including/excluding parts of a CSS file based on the
  existence of variables.
* Updated plugin API.
* Added options for enabling and disabling plugins at runtime.
* Added property sorter plugin.
* Added support for SASS-like @include/@extend syntax for invoking mixins and extends.
* Boilerplate option now accepts a filename string as a boilerplate template.
* `CssCrush::string` method now uses document\_root as a default context for finding linked resources.
* Updated command line appication.
* Updated aliases and initial value files.
* Fixed parsing issue introduced in 1.6.1.

1.6.1 (22nd August 2012)
-----
* Resolved issues #34 and #35.

1.6 (1st August 2012)
---
* Inheritance model improved to support adoption of pseudo classes and elements (see wiki).
* Added rule self-referencing function `this()` and complimentary data-* properties.
* Added rule referencing function `query()`.
* Added default value argument for variables.
* Added `hsl-adjust()` and `hsla-adjust()` color functions.
* Mixin and fragment `arg()` function can now be nested.
* Commas are now optional when specifying arguments for most custom functions.
* Double-colon plugin moved to core.
* Option `rewrite_import_urls` now defaults to true.

1.5.3 (13th June 2012)
-----
* Refactoring.
* Fixed some test cases.

1.5.2 (8th June 2012)
-----
* Resolved issue #32.
* `CssCrush::inline` method now defaults to not printing a boilerplate.
* Updated aliases file.

1.5.1 (1st June 2012)
-----
* Extended mixins to work with abstract rules and regular rules.
* Fixed issue with selector grouping and inheritance in combination.

1.5 (21st May 2012)
---
* New feature: Rule inheritance / abstract rules.
* New feature: Block nesting.
* New feature: Mixins.
* New feature: Fragments.
* Abstracted IO interface.
* Added some error reporting.
* Added spiffing.css plugin.
* `CssCrush::tag` method now uses media type 'all' by default.
* Updated alias and initial-value tables.
* Internal refactoring.
* Resolved issues #23, #24, #27, #28 and #29.

1.4.2 (14th March 2012)
-----
* Fixed bug with @import statement parsing.
* Some minor under the hood changes.

1.4.1 (10th February 2012)
-----
* Added command line application.
* Added `rewrite_import_urls` option - Ability to rewrite relative url references inside imported css files.
* Added Prepend.css - Optionally prepend css to every input.
* Fix for issue #21.
* Reorganized aliases file with some additions.
* Initial-values updated.
* Updated `CssCrush::string` method to correctly handle import statements.

1.4 (24th January 2012)
---
* Added initial-keyword plugin (shim for the CSS3 keyword).
* Added inline method (Issue #18).
* Added ability to escape declarations from aliasing or plugins by prefixing with tilde.
* Added procedural style public API to mirror the static class API.
* Deprecated `@variables` directive for `@define`. @variables still supported for next few releases.
* Adjusted color functions to accept a space delimiter (as well as comma) in the arguments list.
* Surpressed some benign PHP warning messages.
* Some internal cleaning up.
* Disabled IE6 min-height plugin by default.

1.3.6 (9th November 2011)
-----
* Improved color functions.
* Added `a-adjust()` function for altering a color's opacity.
* Deprecated hsl-adjust function (you can use nested color functions instead).
* Added the ability to use local versions of alias and plugin files so pull updates don't clobber local settings.

1.3.5 (8th November 2011)
-----
* Added hook system for plugins.
* Plugins split into seperate files.
* Aliases and Plugins files renamed with '.ini' file extensions to be editor friendly.
* Added opacity plugin.
* Updated filter plugin.
* Fixed nested custom function parsing (issue #14).

1.3.4 (29th October 2011)
-----
* Added output_filename option.
* Added vendor_target option.
* Renamed 'macros' to the more general 'plugins' and split them into their own files.
* Removed superfluous outer containing directory (update your include paths).

1.3.3 (28th October 2011)
-----
* Fixed regression with absolute URL file imports (issue #12).
* Fixed minification bug (issue #13).

1.3.2 (18th October 2011)
-----
* Updated variable syntax.
* Fixed minification bug.

1.3.1 (9th October 2011)
-----
* Added support for svg and svgz data uris.
* Added animation shorthand alias.
* Added user-select alias.

1.3 (20th September 2011)
---
* Added the public function `CssCrush::string` for processing raw strings of CSS.
* Added color functions.
* Added aliases for IE10.

1.2 (8th September 2011)
---
* Rewritten the file importer.

1.1 (2nd September 2011)
---
* Added support for global variables.
* Added support for variable interpolation within string literals.
* Added `CssCrush::tag` method for outputting an html link tag instead of returning a filename.
* Added values aliases, dynamic 'runtime' variables.
* Added RGBA macro.
* Added IE clip macro.
* Added data uri function.
* Minor correction to WAMP support.
* Minor fix to rule API.

1.0 (14th July 2011)
---
* Major refactoring.
* Custom functions.
* Optional boilerplate.
* Double colon syntax shim.
* Resolved document root issues.
* Minification improvements.

0.9 (20th September 2010)
---
* Initial release.
