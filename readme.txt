=== MCP Abilities - SitePress ===
Contributors: devenia
Tags: mcp, wpml, translation, ai, automation
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 0.3.39
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPML translation, language metadata, and language-switcher recovery abilities for MCP.

== Description ==

This plugin exposes core WPML translation workflows through MCP:

* `wpml/list-page-translation-status`
* `wpml/ensure-page-translation`
* `wpml/ensure-post-translation`
* `wpml/update-translated-post-url`
* `wpml/audit-elementor-language-assets`
* `wpml/detect-untranslated-content`
* `wpml/list-active-languages`
* `wpml/list-posts`
* `wpml/get-element-language-details`
* `wpml/get-post-translations`
* `wpml/find-translation-candidates`
* `wpml/set-post-language-details`
* `wpml/link-post-translation`
* `wpml/audit-translated-links`
* `wpml/audit-translated-links-batch`
* `wpml/audit-translation-coverage`
* `wpml/get-language-switcher-settings`
* `wpml/list-language-switcher-slots`
* `wpml/validate-language-switcher-settings`
* `wpml/reset-language-switcher-settings`
* `wpml/rebuild-language-switcher-settings`
* `wpml/audit-translation-integrity`
* `wpml/remove-yoast-redirect`
* `wpml/repair-elementor-gallery-media`
* `wpml/audit-elementor-gallery-media`
* `wpml/update-media-captions-batch`

Use it to inspect translation mappings, create missing translation shells, run untranslated-content checks, and safely inspect/recover WPML language-switcher state.

Plugin page: https://devenia.com/plugins/mcp-expose-abilities/

= Compatibility =

* Requires WordPress 6.9 or newer
* Tested up to WordPress 7.0
* Requires PHP 8.0 or newer
* Intended for sites running WPML SitePress within that same WordPress release line

== Installation ==

1. Install and activate MCP Expose Abilities
2. Install and activate WPML
3. Upload this plugin
4. Activate the plugin

== Changelog ==

= 0.3.39 =

* Improved: translation integrity shared-term detection ignores shared neutral terms such as `design` and `aluminium`.

= 0.3.38 =

* Improved: translation integrity shared-term detection ignores legitimate shared material names such as `plexiglass` and `lexan`.

= 0.3.37 =

* Improved: translation integrity shared-term detection ignores legitimate shared product and technology terms such as `PRIVA-LITE` and `intelligent`.

= 0.3.36 =

* Improved: translation integrity frontend marker defaults no longer report global Trustpilot locale markup as untranslated page content.

= 0.3.35 =

* Improved: translation integrity shared-term detection ignores layout terms and domain-neutral glass terminology instead of reporting them as untranslated source text.

= 0.3.34 =

* Improved: translation integrity detection ignores Elementor global style tokens instead of reporting them as untranslated source text.

= 0.3.33 =

* Improved: `wpml/list-posts` now accepts `lang` as an alias for `target_lang`, so search queries can use the same language parameter shape as other list/search helpers.

= 0.3.32 =

* Added: `wpml/list-posts` lists posts, pages, and custom post types inside an explicit WPML language context with native query filters and translation metadata.

= 0.3.31 =

* Improved: Elementor translation-sibling adapter registration now binds the WPML provider to Elementor's guarded-write seam across normal WordPress load order variations.

= 0.3.30 =

* Added: WPML translation sibling provider for Elementor guarded writes, so translated Elementor documents can be preserved independently during WPML-linked page updates.

= 0.3.29 =

* Continued the architecture split by moving translation mutation, link audits, language-switcher recovery, translation shell/integrity checks, and Elementor gallery media abilities into dedicated modules.

= 0.3.28 =

* Fixed `wpml/find-translation-candidates` to always include the already linked WPML target when one exists, even when the source-language title does not match the translated title.

= 0.3.27 =

* Added `wpml/find-translation-candidates` to find reusable target-language publish/draft/trash candidates before creating duplicate translations.

= 0.3.26 =

* Added `wpml/get-post-translations` for read-only WPML translation-group lookup across posts, pages, and custom post types.
* Started the architecture split by moving read-only language and translation query abilities into a dedicated module.

= 0.3.25 =

* Added a guarded ability for registering WPML language details on existing post/CPT items that have no language metadata yet.

= 0.3.24 =

* Cleared Plugin Check warnings for the public language-flag shortcode release.

= 0.3.23 =

* Added a public WPML-powered language flag shortcode for theme/header inserts.

= 0.3.22 =

* Added Elementor gallery media audit/repair abilities and batch media-caption updates for translated content.
* Added Yoast redirect removal and expanded translation integrity checks for migrated multilingual pages.

= 0.3.15 =

* Added configured option translations for language-aware option output such as `blogdescription`.
* Extended `wpml/audit-translation-integrity` to report `[insert page='...']` shortcodes that still point at source-language content.

= 0.3.14 =

* Added `wpml/audit-translation-integrity` for untranslated source text, source-language URL segments, and optional frontend marker checks.
* Expanded `wpml/detect-untranslated-content` to support posts and other post types, not only pages.

= 0.3.13 =
* Added `wpml/audit-elementor-language-assets` to detect and optionally fix translated Elementor content that still references source-language global widget templates or wrong Trustpilot locales.

= 0.3.12 =
* Extended `wpml/update-translated-post-url` to update Permalink Manager custom URIs so old source-language custom URLs no longer override translated slugs.

= 0.3.11 =
* Added `wpml/update-translated-post-url` to update translated post slugs, translated categories, and Yoast/Rank Math primary category in the correct WPML language context.

= 0.3.10 =
* Added `wpml/ensure-post-translation` to create proper WPML-linked translations for pages, posts, and custom post types while copying Elementor data, featured images, selected meta, and translated taxonomy terms where available.

= 0.3.9 =
* Added `wpml/audit-translation-coverage` to report published source-language pages, posts, and templates that are missing or older than target-language translations.

= 0.3.8 =
* Tightened URL extraction for translated-link audit so unresolved checks do not treat closing tags, shortcode fragments, or prose with slashes as URLs.

= 0.3.7 =
* Extended translated-link audit to flag unresolved internal page-like URLs that are not under the target language prefix, so broken/source-language-looking links are visible even when `url_to_postid()` cannot map them to a translated post.

= 0.3.6 =
* Added `wpml/audit-translated-links-batch` to scan explicit IDs or all translated posts of a post type for source-language internal links, with optional batch replacement.

= 0.3.5 =
* Tightened translated-link audit so URLs already using the target language path prefix, such as `/en/...`, are not flagged as source-language links.

= 0.3.4 =
* Added `wpml/audit-translated-links` to detect internal links in translated content/Elementor data that still point to source-language originals, with optional `fix=true` replacement to translated URLs.

= 0.3.3 =
* Added `wpml/link-post-translation` for linking existing post/CPT items, including Elementor library templates, as WPML translations.
* Made element language detail lookup post-type-aware instead of page-only.

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
