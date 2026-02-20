=== MCP Abilities - SitePress Multilingual CMS ===
Contributors: devenia
Tags: mcp, wpml, translation, ai, automation
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 0.2.4
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPML translation mapping and translation-shell helper abilities for MCP.

== Description ==

This plugin exposes core WPML translation workflows through MCP:

* `wpml/list-page-translation-status`
* `wpml/ensure-page-translation`
* `wpml/detect-untranslated-content`

Use it to inspect translation mappings, create missing translation shells, and run untranslated-content checks before publishing.

== Installation ==

1. Install and activate MCP Expose Abilities
2. Install and activate WPML
3. Upload this plugin
4. Activate the plugin

== Changelog ==

= 0.2.4 =
* Added output schemas and MCP meta annotations for all abilities
* Added WP.org `readme.txt` for release parity

= 0.2.2 =
* Improved untranslated-content detection tunables

= 0.2.1 =
* Added `wpml/detect-untranslated-content`

= 0.2.0 =
* Added `wpml/ensure-page-translation`

= 0.1.0 =
* Initial release with translation status listing
