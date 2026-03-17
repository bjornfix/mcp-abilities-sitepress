=== MCP Abilities - SitePress ===
Contributors: devenia
Tags: mcp, wpml, translation, ai, automation
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 0.3.2
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPML translation, language metadata, and language-switcher recovery abilities for MCP.

== Description ==

This plugin exposes core WPML translation workflows through MCP:

* `wpml/list-page-translation-status`
* `wpml/ensure-page-translation`
* `wpml/detect-untranslated-content`
* `wpml/list-active-languages`
* `wpml/get-element-language-details`
* `wpml/get-language-switcher-settings`
* `wpml/list-language-switcher-slots`
* `wpml/validate-language-switcher-settings`
* `wpml/reset-language-switcher-settings`
* `wpml/rebuild-language-switcher-settings`

Use it to inspect translation mappings, create missing translation shells, run untranslated-content checks, and safely inspect/recover WPML language-switcher state.

Plugin page: https://devenia.com/plugins/mcp-expose-abilities/

= Compatibility =

* Requires WordPress 6.9 or newer
* Tested up to WordPress 6.9
* Requires PHP 8.0 or newer
* Intended for sites running WPML SitePress within that same WordPress release line

== Installation ==

1. Install and activate MCP Expose Abilities
2. Install and activate WPML
3. Upload this plugin
4. Activate the plugin

== Changelog ==

= 0.3.2 =
* Fixed: removed tracked hidden files from the release package so WP.org Plugin Check passes

= 0.3.1 =
* Docs: added explicit WordPress and PHP compatibility notes
* Docs: corrected the documented ability count to match the current 10 registered abilities

= 0.3.0 =
* Added active-language and element-language detail abilities
* Added WPML language-switcher inspection, validation, reset, and rebuild abilities
* Expanded the plugin into a safer WPML operations surface for automation

= 0.2.5 =
* Renamed plugin display name to SitePress for trademark-safe naming
* Removed site-specific wording from documentation
* Added link to plugin page on devenia.com

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
