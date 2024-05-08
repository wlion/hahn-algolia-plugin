=== Hahn: Algolia Integration ===

A front-end solution to configure Algolia indices and provide a wrapper for Algolia.

== Description ==

Since Algolia has abandoned support for their Wordpress plugin, we went ahead and created a front-end for it so indices can be managed and configured from within the Wordpress admin. Additionally, this plugin will provide the necessary means to communicate with Algolia for searching purposes.

== Custom Hooks ==

The plugin has a *Custom Hooks* section to execute custom user functions. By default, the plugin will be looking for a `admin-custom-hooks.php` in `/wp-content/themes/hahn/algolia`; add functions to this array and follow the format outlined in this file.
