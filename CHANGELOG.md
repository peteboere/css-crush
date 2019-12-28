## 3.0.0

* Raised php requirement to >= 5.6
* Removed `csscrush_version()`
* Removed `csscrush_add_function()` (can use plugin instead).
* Added `csscrush_plugin()` with simplified plugin api.
* Added `import_path` option. Additional paths to search when resolving relative imports.
* Added support for non-CSS declaration values via backticks (for custom property values).
* Custom properties `--*` now preserve case.
* Updated vendor aliases.
* Moved loop plugin to core.
* Removed `@in` directive.
* Removed `@settings` directive and its api.
* Removed legacy IE plugins.
* Removed hsl2hex, initial, noise, rem, px2em, color and text-align plugins.
* Combined svg plugins (svg-gradients and svg).
* Removed `percent` function.


********************************************************************

## 2.4.0 (2015-07-30)

* Added simple value checking to `@ifset`.
* Updated vendor aliases.
* Various fixes and under the hood improvements.

## 2.3.0 (2015-02-16)

* Added support for function calls on media query lists.
* Added package.json for node package managers.
* Added `previous`/`next` context keywords to `query()` function.
* Removed legacy-flexbox plugin.
* Removed `disable` option. Renamed `enable` option to `plugins`, old name still supported.
* Removing trace option (SASS debug-info is obsolete) and related functionality. CSS source maps are now well supported.
* Color functions now return nothing if the color argument is invalid.
* Improvements to logging and error reporting.
* Various bug fixes.

## 2.2.0 (2014-06-17)

* Rule nesting now works without `@in` directives.
* Added `csscrush_add_function()` as a simple way of adding custom functions without plugins.
* Added alternative directive names: `@set`/`@ifset` for `@define`/`@ifdefine` and `@selector` for `@selector-alias`.
* Added support for a command line config file (`crushfile.php`).
* Added `Util::readConfigFile()` method to enable easier configuration sharing between different workflows; esp. command-line and server.
* Protocoled `@import` directives are now hoisted to the top of output.
* Default output filename now uses `.crush.css` suffix only when outputting to the same directory as input. Otherwise a regular `.css` suffix is used.
* Updated vendor aliases.
* Removed math shorthand syntax.
* Deprecated `@in` directives. Supported until at-least 3.x.
* Deprecated `@define`/`@ifdefine`/`@selector-alias` in favour of new directive names. Supported until at-least 3.x.
* Deprecated the static api methods in favour of the `csscrush_*` functions. Supported until at-least 3.x.

## 2.1.0 (2014-03-21)

* Added HHVM support (HHVM >= 2.4)
* Added Travis CI support.
* Added custom color keywords plugin.
* Added text-align plugin for polyfilling the direction sensitive text-align values, start and end.
* Added selector splat aliases which expand based on arguments.
* Added settings interface for plugins and CSS environment. Old variable based settings (as used in rem and px2em plugins) are now deprecated.
* Added library docs to repository.
* Added unit argument to the math function.
* Deprecated bare parens math e.g. `()` due to their use in developing CSS specs.
* Removed `-ms-` gradient aliases.
* Renamed plugin `hsl-to-hex` to `hsl2hex`.
* Updated plugin API.
* Improved feedback for command line watched files.
* Removed date modified from default boilerplate.
* Made git version available for use in boilerplates.
* Reported version now uses `git describe` style output if available.
* Changed base IO class to use non-static methods.
* Numerous under the hood improvements.

## 2.0.0 (2013-11-2)

* Raised PHP version requirement to PHP 5.3.1.
* Library code (excluding API functions) is now namespaced.
* Added loop plugin: For...in loops with lists and generator functions.
* Added ARIA plugin for working with aria roles states and properties.
* Added forms plugin: pseudo classes for working with forms.
* Removed legacy IE plugins (ie-clip, ie-filter, ie-min-height, rgba-fallback) and spiffing.
* Added parsing for single line variable definitions e.g. `@define col-width 30px;`
* Added support for relative input/output file paths (based on the current excecuting script path).
* Added support for protocol-relative (//) URLs.
* Removed `csscrush_clearcache()` function – Its functionality can be easily replicated in plain PHP since all output files have a '.crush.css' file extension.
* Removed `csscrush_globalvars()` function. Use `csscrush_set()` instead.
* Added `stat_dump` option for saving stats and variables used to a file in json format.
* Added `asset_dir` option for directing generated svg and image files.
* Deprecated and removed the *-local.ini now there is a better ways of augmenting the default aliases.
* If `formatter` option is set will now override the `minify` option (setting it to false)
* Now using a PSR-3 compatible logging interface (default implementation can be overridden).
* Better error reporting for syntax errors.
* Various Bug fixes.


********************************************************************

## 1.11.0 (2013-8-3)

* Added source map support according to the Source Map v3 proposal (boolean option `source-map`).
* Compile times are now 20-30% reduced.
* Added support for fragment calls within fragment definitions (Issue #48).
* Added check and recovery for overly conservative ini settings.
* The block nesting parent symbol can now be used multiple times (useful for adjacent/general sibling combinations).
* Command utility now supports the `trace` option.
* Custom formatter callbacks have been simplified.
* Simplified the `csscrush_stat()` function signature.
* Added command line utility alias for composer's vendor/bin directory.
* Removed Plugins.ini (use `csscrush_set()` instead).
* Removed Prepend.css.
* Various refactoring for cleaner under-the-hood APIs.

## 1.10.0 (2013-5-18)

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

## 1.9.1 (2013-1-31)

* Added noise plugin (noise/texture generating functions).
* Resolved issues #42 and #43.
* Fixed command line context option.
* Fixed error notice with no enabled plugins in Plugins.ini file.
* Updated aliases file.

## 1.9 (2013-1-12)

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

## 1.8.0 (2012-11-13)

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

## 1.7.0 (2012-9-28)

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

## 1.6.1 (2012-8-22)

* Resolved issues #34 and #35.

## 1.6.0 (2012-8-1)

* Inheritance model improved to support adoption of pseudo classes and elements (see wiki).
* Added rule self-referencing function `this()` and complimentary data-* properties.
* Added rule referencing function `query()`.
* Added default value argument for variables.
* Added `hsl-adjust()` and `hsla-adjust()` color functions.
* Mixin and fragment `arg()` function can now be nested.
* Commas are now optional when specifying arguments for most custom functions.
* Double-colon plugin moved to core.
* Option `rewrite_import_urls` now defaults to true.

## 1.5.3 (2012-6-13)

* Refactoring.
* Fixed some test cases.

## 1.5.2 (2012-6-8)

* Resolved issue #32.
* `CssCrush::inline` method now defaults to not printing a boilerplate.
* Updated aliases file.

## 1.5.1 (2012-6-1)

* Extended mixins to work with abstract rules and regular rules.
* Fixed issue with selector grouping and inheritance in combination.

## 1.5.0 (2012-5-21)

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

## 1.4.2 (2012-3-14)

* Fixed bug with @import statement parsing.
* Some minor under the hood changes.

## 1.4.1 (2012-2-10)

* Added command line application.
* Added `rewrite_import_urls` option - Ability to rewrite relative url references inside imported css files.
* Added Prepend.css - Optionally prepend css to every input.
* Fix for issue #21.
* Reorganized aliases file with some additions.
* Initial-values updated.
* Updated `CssCrush::string` method to correctly handle import statements.

## 1.4.0 (2012-1-24)

* Added initial-keyword plugin (shim for the CSS3 keyword).
* Added inline method (Issue #18).
* Added ability to escape declarations from aliasing or plugins by prefixing with tilde.
* Added procedural style public API to mirror the static class API.
* Deprecated `@variables` directive for `@define`. @variables still supported for next few releases.
* Adjusted color functions to accept a space delimiter (as well as comma) in the arguments list.
* Surpressed some benign PHP warning messages.
* Some internal cleaning up.
* Disabled IE6 min-height plugin by default.

## 1.3.6 (2011-11-9)

* Improved color functions.
* Added `a-adjust()` function for altering a color's opacity.
* Deprecated hsl-adjust function (you can use nested color functions instead).
* Added the ability to use local versions of alias and plugin files so pull updates don't clobber local settings.

## 1.3.5 (2011-11-8)

* Added hook system for plugins.
* Plugins split into seperate files.
* Aliases and Plugins files renamed with '.ini' file extensions to be editor friendly.
* Added opacity plugin.
* Updated filter plugin.
* Fixed nested custom function parsing (issue #14).

## 1.3.4 (2011-10-29)

* Added output_filename option.
* Added vendor_target option.
* Renamed 'macros' to the more general 'plugins' and split them into their own files.
* Removed superfluous outer containing directory (update your include paths).

## 1.3.3 (2011-10-28)

* Fixed regression with absolute URL file imports (issue #12).
* Fixed minification bug (issue #13).

## 1.3.2 (2011-10-18)

* Updated variable syntax.
* Fixed minification bug.

## 1.3.1 (2011-10-9)

* Added support for svg and svgz data uris.
* Added animation shorthand alias.
* Added user-select alias.

## 1.3 (2011-10-20)

* Added the public function `CssCrush::string` for processing raw strings of CSS.
* Added color functions.
* Added aliases for IE10.

## 1.2.0 (2011-9-8)

* File importer rewritten.

## 1.1.0 (2011-9-2)

* Added support for global variables.
* Added support for variable interpolation within string literals.
* Added `CssCrush::tag` method for outputting an html link tag instead of returning a filename.
* Added values aliases, dynamic 'runtime' variables.
* Added RGBA macro.
* Added IE clip macro.
* Added data uri function.
* Minor correction to WAMP support.
* Minor fix to rule API.

## 1.0.0 (2011-7-14)

* Major refactoring.
* Custom functions.
* Optional boilerplate.
* Double colon syntax shim.
* Resolved document root issues.
* Minification improvements.

## 0.9.0 (2010-9-20)

* Initial release.
