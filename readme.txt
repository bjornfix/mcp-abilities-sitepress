=== MCP Abilities - SitePress ===
Contributors: basicus
Tags: mcp, wpml, translation, ai, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 0.3.75
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPML translation, language metadata, and language-switcher recovery abilities for MCP.

== Description ==

Version 0.3.66 adds selected registered-string source-language correction through WPML's native language setter. It preserves text and translation metadata, requires reviewed source/translation hashes and native permissions, and blocks active translation work. Version 0.3.65 adds explicit selection of registered admin-option string domains through native WPML inventory and translation APIs. It preserves original values, native permissions, reviewed source/target hashes and active job progress. Version 0.3.64 preserves existing translated content and native Elementor documents during URL-only saves. URL updates require the MCP Expose Abilities translation-sibling guard and refuse missing protection. Native slug uniqueness and old-slug history remain in use. The plugin also translates the caption or title that native Elementor images use when stored alt text is empty. Empty stored alt fields, explicit alternative text and original image rendering are preserved. Publicly selected native image and gallery alt text can also use String Translation when an old attachment parent is unpublished. Hidden private titles, captions and descriptions remain excluded. The plugin also displays translated attachment text in native Elementor Pro Gallery, lightboxes and retained-ID image tooltips while retaining the original image IDs, files, sizing and layout. Existing translated media fields are reused. Missing text can be translated through the native WPML String Translation domain MCP Media Text without creating another attachment. Source text remains visible until its translation is supplied. The plugin exposes 57 abilities for selected native WPML workflows with existing WordPress and WPML permissions, including bounded repair for misplaced Elementor thumbnail references without regenerating image files.


This plugin exposes core WPML translation workflows through MCP:

* `wpml/get-site-languages`
* `wpml/add-site-language`
* `wpml/set-active-site-languages`
* `wpml/set-default-site-language`
* `wpml/set-term-language-details`
* `wpml/link-term-translation`
* `wpml/get-general-settings`
* `wpml/update-general-settings`
* `wpml/get-diagnostics`
* `wpml/clear-translation-cache`
* `wpml/get-registration-status`
* `wpml/get-registration-workflow`
* `wpml/list-strings`
* `wpml/get-string`
* `wpml/update-string-source-language`
* `wpml/update-string-translation`
* `wpml/list-local-translators`
* `wpml/assign-local-translation-job`
* `wpml/create-local-translation-job`
* `wpml/get-translation-controls`
* `wpml/enable-manual-translation`
* `wpml/list-translation-jobs`
* `wpml/preview-translation-job-cancellation`
* `wpml/cancel-translation-jobs`
* `wpml/get-pricing-notice-state`
* `wpml/set-pricing-notice-hidden`
* `wpml/get-update-channel-status`
* `wpml/get-administration-coverage`
* `wpml/list-page-translation-status`
* `wpml/ensure-page-translation`
* `wpml/ensure-post-translation`
* `wpml/update-translated-post-url`
* `wpml/audit-elementor-language-assets`
* `wpml/detect-untranslated-content`
* `wpml/list-active-languages`
* `wpml/list-posts`
* `wpml/list-terms`
* `wpml/get-element-language-details`
* `wpml/get-post-translations`
* `wpml/get-term-translations`
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
* `wpml/update-contact-form-7-translation-form`

Use it to inspect translation mappings, create missing translation shells, run untranslated-content checks, and safely inspect/recover WPML language-switcher state.

Plugin page: https://devenia.com/plugins/mcp-abilities-sitepress/

Elementor’s existing signed public previews use the previewed post’s WPML language, including unpublished translations. Existing signatures, expiry, revision checks and native rendering are retained.

= Compatibility =

* Requires WordPress 6.9 or newer
* Tested up to WordPress 7.1
* Requires PHP 8.0 or newer
* Native administration controls require WPML 4.7 or newer
* String operations require matching WPML String Translation and its native string inventory API
* Intended for sites running WPML SitePress within that same WordPress release line

= Selected administration workflows =

Read native administrative languages, including hidden active languages, before changing activation or the default. Deactivation retains stored translations. Default changes retain WPML locale/user-language effects. These controls retain language lifecycle hooks but do not download language packs or create native setup notices. Term linking rejects occupied languages and existing linked groups and requires edit access to both terms.

`wpml/enable-manual-translation` now disables new automatic pickup, drafts and automatic string collection while preserving manual ATE/classic editing, review and service pickup. Existing work is not paused or cancelled. External verification failures preserve local queue results and explicitly mark unfinished ATE work unverified.

Select the manual editor separately with `wpml/update-general-settings`, `changes.translation_editor` set to advanced or classic, a reviewed expected_settings_hash and confirm=true. Submit editor selection alone. Advanced selection requires automatic pickup and drafts off and a complete native inventory without unfinished ATE work. The operation does not dispatch jobs or change native-editor overrides.

Settings changes accept only the selected synchronization, translated-slug and translation-editor allowlist. Review a preview and confirm against the current settings hash. Other options, domains, roles and account settings remain outside this Interface.

String inventory uses the existing native WPML API with the current user and a native REST nonce. Select an exact registered domain. Registered admin_texts_* option-string domains require include_option_strings=true and native administrator permission on list, get and update calls. Arbitrary WordPress option values are never read. Target updates require reviewed source/target hashes, an active target language and draft/complete state. Waiting and in-progress/partial targets are preserved. WPML owns text normalization and translation-file generation; review actual saved values.

Local translator controls require existing qualified translators and native management permissions. Assignment applies only to waiting local manual post jobs. First-job creation requires native manual mode, an editable original post in a translated type and no existing target relationship, content or native job history. Existing work blocks resend. Native configured notifications may be sent. No translator roles, language pairs, ATE/external jobs or string/package dispatch are created. Coordinate concurrent edits to the same item; native APIs do not provide an atomic transaction with the WordPress UI.

Source-language correction previews one selected registered string using wpml/update-string-source-language. Select an active source_language, then confirm with expected_source_hash, expected_translations_hash and confirm=true from the reviewed preview. Native administrator and String Translation permissions are required. Waiting, in-progress and partial translations block the change. WPML preserves source text and all translation rows while recalculating aggregate status and clearing its string cache. Source identity, text and translations are verified after saving. Site default language and raw option values remain unchanged. Re-read the selected string before retrying an uncertain or failed write.

Diagnostics return selected status without a full debug dump or external connectivity probe. Cache refresh is the only supplied troubleshooting action; database repair, destructive reset, content deletion and orphan-job repair are excluded.

Registration status reports site-key presence, not license validity. The workflow returns the existing native Installer link. Enter keys only in the native secure form, never in an MCP request. These abilities do not accept/return keys, register the site, connect accounts or manage payments.

== Installation ==

For update notifications in WordPress, install [Devenia MCP Updater](https://downloads.devenia.com/devenia-mcp-updater.zip). The updater is optional. You choose which plugins update automatically through WordPress.

1. Install and activate MCP Expose Abilities
2. Install and activate WPML
3. Upload this plugin
4. Activate the plugin

== Changelog ==

= 0.3.75 =
* Read cached native Installer product/channel metadata, fallback and validity booleans through verified native getters. No provider refresh or account data is needed for this status projection.

= 0.3.74 =
* Add fixed-target, reversible pricing-notice hiding through native Unnotifier individual mode and normal administrator rendering. Preserve all other notices, unknown settings and human restore choices.
* Add safe commercial Installer feed/update-check metadata without account data, keys, raw notices or arbitrary callback execution.

= 0.3.73 =
* Use the native document-editor ATE value, distinct from the lowercase job-editor enum.
* Require effective ATE readback before confirming an Advanced editor change.

= 0.3.72 =
* Preserve manual ATE/classic editing, review and pickup when disabling new automatic translation. Report selected before/after changes and partial failures.
* Add a separate hash-reviewed translation-editor patch. Refuse mixed settings and unsafe reactivation while unfinished ATE work exists.
* Preserve local job inventory during external ATE failure and check only unfinished ATE work.

= 0.3.71 =
* Use automatic WordPress loading of an optional site-managed translation catalog for the missing Formidable file helper. Sites without a catalog keep the original text; existing translations and other domains remain unchanged.

= 0.3.67 =
* Translate the missing Norwegian Formidable Pro file-size helper while preserving existing translations, the size placeholder and English output.

= 0.3.66 =
* Correct the source language of one selected registered string through WPML's native language setter.
* Require an active language, native management permissions and reviewed source/translation hashes; preserve source text, translation metadata and active work.
* Verify saved source and translation data while retaining native aggregate status and string-cache handling.

= 0.3.65 =
* Read and translate explicitly selected registered admin-option strings through native WPML inventory and translation APIs.
* Preserve original values, native permissions, reviewed hashes and active translation job progress.

= 0.3.64 =
* Fixed: URL-only saves preserve existing translated post content and native Elementor documents through the MCP Expose Abilities translation-sibling guard.
* Preserved: Native WordPress slug uniqueness and old-slug history, WPML relationships and existing permissions. Missing guard protection refuses the URL update.

= 0.3.63 =
* Fixed: Native Elementor custom-size image alt fallbacks use the translated caption or title already stored in WPML.
* Preserved: Empty stored alt fields, explicit alternative text, source-language output, raw administrative reads and original image markup.

= 0.3.62 =
* Fixed: The alt text of an image explicitly selected in the current published native image or gallery document can use String Translation despite an old unpublished attachment parent.
* Preserved: Hidden private titles, captions and descriptions; draft, password-protected and preview documents; original media, permissions and raw source reads.

= 0.3.61 =
* Add a bounded repair mode for reviewed misplaced Elementor thumbnail entries, preserving original files and other image metadata.

= 0.3.60 =

* Fixed: Native signed Elementor public previews retain the previewed post’s WPML language instead of the root URL’s default language.
* Preserved: Existing token verification, expiry, revision selection, privacy headers, rendering and ordinary public/admin/REST/CLI language context.

= 0.3.58 =

* Fixed: Custom-size Elementor Loop images use translated native attachment titles for their tooltips while retaining original media identity.
* Fixed: An old unpublished original media parent no longer blocks existing public translated media fields; unpublished source strings remain excluded from registration and lookup.
* Preserved: Ordinary post/page titles, explicitly filtered alternative titles, empty title choices, native formatting and original-language/admin/REST/CLI output.

= 0.3.56 =

* Fixed: Native Elementor Gallery overlays, lightbox text and frontend attachment alt lookups use the current language while preserving original image identity and native rendering.
* Added: Native WPML String Translation fallback for attachment text without a translated media record or field; original text is retained until translated.
* Preserved: Original-language output, native lightbox field selections, image lifecycle hooks, raw administration/REST/CLI reads and all 53 existing abilities.

= 0.3.55 =

* Added: Explicit post-translation reassignment with the reviewed previous group ID, edit access to both originals, unchanged languages, occupied-slot protection and native WPML readback.

= 0.3.54 =

* Added: Native language inventory, custom inactive language creation, reviewed activation/deactivation and default-language selection.
* Added: Safe native term metadata and translation links with edit permissions and group collision checks.
* Added: Content-string inventory and reviewed native target updates; retain native nonce/capability checks and preserve waiting/in-progress/partial targets.
* Added: Existing local translator inventory, waiting manual assignment and first-job-only dispatch with source/job review hashes.
* Added: Allowlisted general settings, selected diagnostics, cache refresh and secret-free registration status/secure workflow.
* Limits: No language/content deletion, raw settings, role grants, existing-job resend, external dispatch, registration-key input/output or full WPML administration.

= 0.3.53 =

* Added: Native WPML manual translation controls and redacted automation status.
* Added: Current and historical jobs, exact cancellation previews, verified ATE cancellation, native local transitions, and repeat-safe readback.
* Added: Administration coverage with explicit gaps and secure user steps.
* Safety: Finished translations are excluded. Unverified remote status preserves local jobs. Cancellation requires acknowledgement of lost unfinished progress and does not refund credits.

= 0.3.52 =
* Correct exact redirect deletion to use the native Yoast target getter. Keep destination matching and native redirect exports intact.

= 0.3.51 =
* Add one dismissible Plugins-screen reminder when Devenia MCP Updater is missing or inactive, with persistent install or activate links. Automatic updates remain your choice in WordPress.

= 0.3.50 =

* Added per-post access checks and verification of translation writes.
* Prevented conflicting language links and kept unlinked shells as drafts.
* Preserved native slug uniqueness, URL history, and exact URL replacement.
* Used configured regional locales and native Contact Form 7, Yoast, and Permalink Manager operations.
* Clarified switcher reset results and completed the public ability inventory.

= 0.3.49 =

* Replaced built-in source-language assumptions with caller-supplied language codes and exact marker lists.
* Normalized locale-specific Trustpilot hosts without depending on one language.

= 0.3.48 =

* Aligned the public package author and contributor identity with the Basicus release identity.
* Documented all 29 registered SitePress abilities in the public inventory.

= 0.3.47 =

* Improved: translation integrity gallery parity accepts a translated Elementor container background image as the equivalent of a one-item source media-carousel when both use the same attachment.

= 0.3.46 =

* Improved: translation integrity gallery parity accepts a translated native Image widget as the equivalent of a one-item source media-carousel when both use the same attachment.

= 0.3.45 =

* Improved: translation integrity URL checks ignore shared translated parent slugs instead of reporting them as source-language child URL segments.

= 0.3.44 =

* Improved translation integrity gallery parity checks so translated Elementor media-carousel widgets with language-specific element IDs can match by attachment signature instead of being reported as missing.

= 0.3.43 =

* Ignored language-neutral web/platform terms in shared-term translation integrity checks so required privacy-policy terms like Google, Facebook, and cookies do not create false positives.

= 0.3.42 =

* Added: `wpml/update-contact-form-7-translation-form` updates the native Contact Form 7 form template and locale for translated CF7 forms.

= 0.3.41 =

* Added: WPML-aware Contact Form 7 shortcode rendering maps linked form translations to the current language when available.

= 0.3.40 =

* Added: read-only WPML taxonomy term abilities for language-scoped term lists and term translation-group lookup.

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
