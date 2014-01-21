
Pseudo classes for working with ARIA roles, states and properties.

 * [ARIA roles spec](http://www.w3.org/TR/wai-aria/roles)
 * [ARIA states and properties spec](http://www.w3.org/TR/wai-aria/states_and_properties)

````css
:role(tablist) {...}
:aria-expanded {...}
:aria-expanded(false) {...}
:aria-label {...}
:aria-label(foobarbaz) {...}
````

````css
[role="tablist"] {...}
[aria-expanded="true"] {...}
[aria-expanded="false"] {...}
[aria-label] {...}
[aria-label="foobarbaz"] {...}
````
