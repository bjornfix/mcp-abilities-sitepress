# MCP Abilities - SitePress

Find missing WPML translations, manage selected native language and string settings, reuse the right translated post, and repair links that still send readers back to the source language. MCP Abilities - SitePress gives an authenticated assistant access to these jobs inside WordPress.

[![Release 0.3.73](https://img.shields.io/badge/release-0.3.73-blue.svg)](https://downloads.devenia.com/mcp-abilities-sitepress.zip)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)

**Stable tag:** 0.3.73 · **Tested up to:** WordPress 7.1 · **License:** GPLv2 or later

**Tags:** mcp, wpml, translation, ai, automation

## What It Does

The plugin exposes 54 WordPress abilities for WPML language discovery, translation groups, translation shells, link checks, content checks, and selected integration repairs. WPML keeps ownership of language relationships. WordPress keeps ownership of posts, terms, media, and permissions.

It can identify a translated service page that still links to the original contact page, show the linked replacement, and apply that replacement when requested. It can also distinguish a missing translation from an existing draft that can be reused.

You can supply target string text and save it through WPML’s native writer. The plugin does not generate translations or judge whether a translated explanation suits its readers. Text comparisons identify material to review; they are not a language-quality score.

Elementor’s existing signed public preview links also retain the previewed post’s WPML language. Editors can inspect an unpublished translation without publishing it or changing its language relationship.

## The Real Workflow

1. Read the source post's translation group and check coverage for the intended language.
2. Inspect existing candidates before creating a new shell. A shell copies selected source material into a draft and links it through WPML.
3. Write and review the translation. Inspect links, Elementor references, and media text for source-language material.
4. Apply the selected repairs, then check the actual page and language switcher.

## Why This Feels Different

A language code alone cannot tell you which post should be changed. These abilities expose post IDs, translation groups, language codes, and native URLs together. An assistant can check the relationship before it changes content.

Creation reuses an existing linked translation. Linking rejects an occupied language slot. New shells remain drafts if WPML does not confirm the relationship. URL updates retain WordPress's unique-slug rules and old-slug history.

## Before vs After

| Job | Manual work | With these abilities |
| --- | --- | --- |
| Find gaps | Open source pages and inspect each language | List missing translations and modification-date differences |
| Reuse a draft | Search titles and inspect language assignments | Compare candidates and the existing translation group |
| Repair internal links | Read each translated page and follow its links | Inspect proposed replacements, then enable the selected repair |
| Check translated media | Open galleries and attachment records separately | Inspect gallery use, missing sizes, and caller-specified caption markers |

## Who It Is For

Multilingual site editors, agencies maintaining WPML sites, and developers connecting an assistant to existing WordPress operations. Elementor users also get targeted gallery and template-reference checks.

## Requirements

- WordPress 6.9 or newer with its Abilities API, and PHP 8.0 or newer.
- An active, configured WPML SitePress installation. Native job administration and manual controls require WPML 4.7 or newer. These abilities are not registered without WPML.
- An authenticated connection that exposes WordPress abilities to your assistant, such as [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/).
- Matching WPML String Translation with its native string inventory API is required for string operations. The native API, factory, settings and translator capabilities must be available; missing APIs fail without an alternative query or access path.
- MCP Expose Abilities with its translation-sibling guard is required for URL updates. Existing sibling content is preserved; missing protection refuses the update.
- The matching plugin for optional operations: Elementor, Contact Form 7, Yoast SEO Premium, or Permalink Manager. These are not translation engines supplied by this plugin.

## Documentation

Read the [plugin guide](https://devenia.com/plugins/mcp-abilities-sitepress/), [WPML hooks reference](https://wpml.org/documentation/support/wpml-coding-api/wpml-hooks-reference/), and [WordPress Abilities API documentation](https://developer.wordpress.org/apis/abilities-api/).

## Start Here

Ask your assistant to inspect a source page's translation group and report which language is missing. Keep the first request read-only. If a target already exists, inspect that post before asking for a new translation shell.

## Complete Ability Inventory

| Ability | Purpose |
| --- | --- |
| `wpml/get-site-languages` | Read administrative languages, including hidden active languages and the default |
| `wpml/add-site-language` | Add one custom language through WPML without activating it |
| `wpml/set-active-site-languages` | Preview or activate/deactivate known languages while preserving stored translations |
| `wpml/set-default-site-language` | Preview or choose an active default through native locale and language controls |
| `wpml/set-term-language-details` | Register missing metadata for an editable translated-taxonomy term |
| `wpml/link-term-translation` | Link editable terms while rejecting occupied slots and linked groups |
| `wpml/get-general-settings` | Read selected synchronization, translated-slug and translation-editor settings with a review hash |
| `wpml/update-general-settings` | Preview or save an allowlisted native settings patch against the reviewed hash |
| `wpml/get-diagnostics` | Read selected component, language, control and registration status without a debug dump |
| `wpml/clear-translation-cache` | Refresh the native WPML cache without deleting content or jobs |
| `wpml/get-registration-status` | Read Installer availability and site-key presence; license validity is not checked |
| `wpml/get-registration-workflow` | Locate the secure native registration screen without accepting or returning keys |
| `wpml/list-strings` | Read a bounded content-string domain through the existing native WPML API |
| `wpml/get-string` | Read one selected registered string and its translations |
| `wpml/update-string-source-language` | Preview or correct a selected string's source language with reviewed source and translation hashes |
| `wpml/update-string-translation` | Preview or save target text through WPML with reviewed source and target hashes |
| `wpml/list-local-translators` | List existing qualified translators for an exact active language pair |
| `wpml/assign-local-translation-job` | Preview or assign a current waiting local manual job to an existing translator |
| `wpml/create-local-translation-job` | Preview or dispatch a first local manual job when no target relationship or job history exists |
| `wpml/get-translation-controls` | Read selected automation, editor, delivery and ATE activation controls without keys |
| `wpml/enable-manual-translation` | Disable automatic pickup, drafts and string collection while preserving the manual editor |
| `wpml/list-translation-jobs` | List current and historical jobs, with optional external ATE status checks |
| `wpml/preview-translation-job-cancellation` | Preview exact unfinished jobs and the consequences of cancellation |
| `wpml/cancel-translation-jobs` | Cancel a confirmed current preview and verify local and external state |
| `wpml/get-administration-coverage` | Distinguish supported functions, gaps and secure human steps |
| `wpml/list-active-languages` | List configured languages without needing a frontend query |
| `wpml/list-posts` | Query posts, pages, or custom post types in a language |
| `wpml/list-terms` | Query terms in a language |
| `wpml/get-element-language-details` | Read one post's language and translation-group ID |
| `wpml/get-post-translations` | Read a post translation group |
| `wpml/get-term-translations` | Read a term translation group |
| `wpml/list-page-translation-status` | Compare source pages with one target language |
| `wpml/find-translation-candidates` | Find existing translated, draft, or unassigned candidates |
| `wpml/set-post-language-details` | Register missing language details on an existing post |
| `wpml/link-post-translation` | Link an existing post without replacing an occupied language slot |
| `wpml/ensure-page-translation` | Reuse or create a linked page shell |
| `wpml/ensure-post-translation` | Reuse or create a shell for a supported post type |
| `wpml/update-translated-post-url` | Update the slug, post categories, primary category, or custom URI |
| `wpml/audit-translated-links` | Inspect or repair internal links in one post |
| `wpml/audit-translated-links-batch` | Run the same link operation on several posts |
| `wpml/audit-translation-coverage` | Report missing translations and modification-date differences |
| `wpml/detect-untranslated-content` | Compare source and target text for shared terms and segments |
| `wpml/audit-translation-integrity` | Inspect text, URLs, galleries, and optional frontend markers |
| `wpml/audit-elementor-language-assets` | Inspect or repair translated template references and Trustpilot locale markup |
| `wpml/audit-elementor-gallery-media` | Inspect gallery use, media sizes, and caption markers; optionally repair sizes |
| `wpml/repair-elementor-gallery-media` | Regenerate missing image sizes, or remove reviewed misplaced Elementor thumbnail references without changing files |
| `wpml/update-media-captions-batch` | Update selected attachment titles, captions, or descriptions |
| `wpml/get-language-switcher-settings` | Read the stored language-switcher configuration |
| `wpml/list-language-switcher-slots` | Inspect menu, sidebar, and static slots |
| `wpml/validate-language-switcher-settings` | Flag suspicious slot structures |
| `wpml/reset-language-switcher-settings` | Delete the stored switcher configuration |
| `wpml/rebuild-language-switcher-settings` | Reset and re-read settings, reporting whether WPML has populated them |
| `wpml/remove-yoast-redirect` | Delete an exact plain redirect through Yoast SEO Premium |
| `wpml/update-contact-form-7-translation-form` | Save a translated form template and locale through Contact Form 7 |

## Usage Examples

Inspect the group for a source page:

```json
{"id": 123, "include_missing": true}
```

Use that input with `wpml/get-post-translations`. The IDs below are illustrative; obtain real IDs from your site.

To move an existing translation to a different original, first inspect both groups. Use `wpml/link-post-translation` with the reviewed previous group ID:

```json
{"source_id": 123, "target_id": 456, "target_lang": "fr", "reassign_from_trid": 789}
```

This moves only the translation relationship. It does not copy or translate content. Both originals must use the same source language, the target language must stay unchanged, and all three posts must be editable. An occupied target-language slot is never replaced. Retrying an already completed link succeeds without another write.

Create or reuse a French draft through `wpml/ensure-post-translation`:

```json
{"source_id": 123, "target_lang": "fr", "target_status": "draft", "copy_content": true}
```

Inspect links through `wpml/audit-translated-links`:

```json
{"id": 456, "target_lang": "fr", "fix": false}
```

After reviewing the proposed replacements, use the same input with `fix: true`. Replacement matches complete URL values, including their HTML and JSON representations.

Check selected gallery images without changing files:

```json
{"attachment_ids": [789, 790], "size": "medium", "dry_run": true}
```

Use that input with `wpml/repair-elementor-gallery-media`.

For a custom size that points into the wrong upload subdirectory, inspect the bounded operation first:

```json
{"operation": "prune_invalid_srcset_sizes", "attachment_ids": [789], "size_keys": ["elementor_custom_400x400"], "dry_run": true}
```

This operation accepts one editable image and explicit Elementor custom size keys. It only proposes removing entries whose relative thumbnail is missing while the matching global Elementor thumbnail exists. Existing valid sizes stay. To apply, copy `expected_metadata_hash` from the reviewed response and repeat the input with `dry_run: false`. A stale hash is refused. Original files, other size entries and image text are preserved; files are not regenerated or deleted. The existing upload, page-edit and attachment-edit permissions remain required.

### Manual translation and job administration

Read `wpml/get-translation-controls` before requesting `wpml/enable-manual-translation` with `{"confirm": true}` and, optionally, the reviewed `expected_controls_hash`. This disables new automatic pickup, automatic drafts and automatic string collection. The manual ATE/classic editor, native-editor overrides, review mode, translation mode and service pickup remain unchanged. Results include selected before/after values and their diff. A saved partial change remains visible when a native write fails. Existing external jobs and consumed credits are not paused, cancelled or refunded.

List jobs with `wpml/list-translation-jobs`:

```json
{"source_ids": [123], "include_history": true, "check_ate": true, "limit": 100}
```

An empty `source_ids` array covers the native site queue. The native repository supplies current jobs; WPML's previous-job operation supplies history for those groups. External verification covers unfinished jobs only. A client exception or unavailable external result preserves the local inventory and explicitly reports unverified ATE work; it never means an empty queue. A local inventory failure remains a separate error. `external_verification_complete` describes unfinished jobs; finished translations are excluded from cancellation. Translation-service jobs that cannot be safely cancelled through the local native workflow remain blockers.

Request `wpml/preview-translation-job-cancellation` with the reviewed source scope. It returns the exact IDs, count, loss warning and a preview valid for fifteen minutes for the same authenticated user. Only after authorizing that exact action, call `wpml/cancel-translation-jobs`:

```json
{"preview_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee", "confirm": true, "acknowledge_loss": true}
```

The ID above is illustrative. Cancellation requires ATE delivery to be disabled separately through native controls; disabling automatic pickup alone preserves ATE. It rejects changed local jobs, unreadable external state and missing acknowledgement. ATE cancellation and the absence of deliverable XLIFF in the native priority response must be confirmed before local changes. Failure can leave partial external cancellation; re-read the queue before retrying. Native state transitions preserve completed translations. A repeated successful preview returns its receipt and reads current queue state without cancelling newly added jobs. Cancellation can discard unfinished progress and does not refund consumed credits. The preview does not grant permission by itself.

`wpml/get-administration-coverage` reports remaining gaps. This release does not provide full WPML administration, registration-key entry, account connections, payments or subscriptions.

Separate editor selection uses `wpml/update-general-settings` with `{"changes":{"translation_editor":"advanced"}}` or `"classic"`. Review the preview, then provide its `expected_settings_hash` and `confirm: true`. Submit editor selection alone. It changes only the native document-editor field and leaves automation, review, pickup and native-editor overrides intact. Enabling Advanced requires automatic pickup and drafts off plus a complete native inventory without unfinished ATE work. No translation is started to verify the editor.

### Languages, terms and selected settings

Read `wpml/get-site-languages` for the native administrative language inventory. It includes active languages hidden from frontend navigation. A new custom language remains inactive. Active-language changes require the reviewed `expected_active_codes`; default-language changes require `expected_default`. Deactivation removes a language from public navigation while retaining its translations. WPML’s language lifecycle hooks remain in use, but these controls do not download language packs or recreate native setup notices. A default-language change retains WPML’s WordPress locale and user-language effects.

Term controls use public WPML hooks and the term’s taxonomy ID internally. Both terms must be editable and use the same translated taxonomy. Linking refuses an occupied target-language slot, a conflicting term language or an existing multi-term group. It does not change term names, slugs or descriptions.

Read `wpml/get-general-settings` to obtain the current selected values and their `settings_hash`. Preview `wpml/update-general-settings` with the requested `changes`, then confirm using that hash as `expected_settings_hash`. The allowlist covers page order, parent, template, ping/comment status, sticky flag, post format/date, term retranslation blocking and translated-page slug mode. It does not accept raw WPML options, domains, roles or account settings.

### Registered strings and local translator work

List a selected domain through `wpml/list-strings`:

```json
{"domain": "my-theme", "source_language": "en", "target_language": "fr", "limit": 25}
```

The existing native WPML API validates the current user’s string-management capabilities and REST nonce. Results are bounded and do not claim a total when the API supplies only a page. For a registered `admin_texts_*` option-string domain, add `"include_option_strings": true` to list, get and update calls. Select the exact domain and ID. Native administrator and String Translation permissions remain required. This reads registered native strings rather than arbitrary WordPress option values.

Read `wpml/get-string` using the selected ID and domain. Preview `wpml/update-string-translation` with the target text and state:

```json
{"id": 456, "domain": "my-theme", "target_language": "fr", "value": "Bonjour", "state": "complete"}
```

Use the returned `before.source_hash` as `expected_source_hash` and `target_hash` as `expected_target_hash`, then set `confirm` to true after reviewing the exact operation. Both hashes must still match. Native waiting and in-progress/partial targets are preserved. States are draft or complete. WPML retains its line-break normalization, link handling and translation-file lifecycle. The response verifies saved text and returns the actual selected values; unexpected native adjustments require review.

If an existing string was registered in the wrong source language, preview `wpml/update-string-source-language` before translating it:

```json
{"id": 456, "domain": "my-theme", "source_language": "en"}
```

Confirm with the returned `before.source_hash` as `expected_source_hash`, `translations_hash` as `expected_translations_hash`, and `confirm: true`. Select an active source language. Native administrator and String Translation permissions are required, including for ordinary content strings. Waiting, in-progress and partial translation work blocks the correction. WPML's native language setter retains the source text and all translation rows, recalculates aggregate status and invalidates its string cache. The response verifies source identity, text and translation rows. This operation changes only the selected registered string; it does not change the site's default language or registered option values. Re-read the string before retrying an uncertain or failed write.

`wpml/list-local-translators` reads existing translators for an exact active language pair. Assignment accepts only current waiting local manual post jobs and the reviewed `expected_job_hash`. Started, completed, ATE, automatic and external work is preserved. First-job creation requires ATE disabled, Translate Everything off, an editable original post in a translated post type, a qualified existing translator and the reviewed `expected_source_hash`. Existing target relationships, target content and native current/history jobs block resend because WPML resend can cancel earlier progress. These operations do not grant translator roles or language pairs, and do not dispatch string/package, external or automatic jobs. Native configured job notifications may be sent when an assignment or creation is confirmed. Coordinate edits to the same item: the native APIs do not provide an atomic transaction with concurrent WordPress UI changes.

### Diagnostics and secure registration

`wpml/get-diagnostics` reports selected versions, native feature availability, language/control state and registration presence. It does not return a full debug dump or run an external connectivity probe. `wpml/clear-translation-cache` confirms a native cache refresh; destructive reset, database repair, content deletion and orphan-job repair remain in native troubleshooting workflows.

`wpml/get-registration-status` reports Installer availability and whether a site key exists. Presence is not proof of a valid license. `wpml/get-registration-workflow` returns the existing native Installer link and secure user steps. Obtain the correct key in your WPML account and enter it only in the native secure form. These abilities do not accept or return keys, register the site, connect accounts or manage payments.

### Frontend integration

The `[mcp_wpml_language_flag]` shortcode renders a linked flag from WPML language data. The plugin also maps linked Contact Form 7 shortcodes to the current language and provides WPML sibling IDs to compatible Elementor editing tools. These features use existing translations.

Native Elementor Pro Gallery overlays, accessible image labels, attachment tooltips and lightbox title/description selections use the current language’s existing attachment text. Original image IDs, files, URLs, sizing, crops and native layout remain in use. The original Images Manager keeps its image-management hooks. Gallery text is treated as language-dependent dynamic content.

When an attachment has no translated media record or a text field is still empty, its public source text is registered in WPML String Translation under **MCP Media Text**, with names such as `attachment-123-title`. Translate the selected field through the native String Translation interface or the existing string abilities. This does not create a media attachment or generate a translation. Original text remains visible until translated; incomplete work is not hidden. Existing nonempty public translated media fields take precedence, including when the embedded original image has an old unpublished parent.

An image explicitly selected by a native image or gallery widget in the current published document can also use String Translation for its public alt text despite an old unpublished attachment parent. This covers native Image, Image Box, Gallery, Image Gallery, Image Carousel, Media Carousel and G1 Text & Image Gallery selections. It does not register hidden private titles, captions or descriptions, unrelated images, decorative backgrounds, or content in draft, password-protected or preview documents. Ordinary unpublished source fields remain excluded. The native WordPress attachment-title lookup also supplies retained-ID image tooltips, including custom-size Loop images. Ordinary post/page titles, explicitly filtered alternative titles, empty title choices, original-language output and raw admin, REST and WP-CLI reads are preserved.

When native Elementor image rendering falls back from an empty alt field to a caption or title, the same existing translated field supplies that fallback. Stored alt fields remain empty, explicitly chosen alternatives are preserved, and the original image HTML is retained apart from the translated alt value. This also covers custom-size images in native Posts cards.

## Permissions and Limits

The ability permission check and the affected post's permission both apply. Administrative recovery and translation linking require `manage_options`; media operations also require media permissions. When String Translation is active, manual controls also require its native string-management or translation-management capability. New administration controls require `manage_options`; string and local-job controls additionally enforce WPML’s existing management capabilities. Term and post writes also require edit access to the affected items. Optional APIs must be available, and native denial is preserved. A connected assistant inherits the WordPress user's access.

Link and Elementor asset audits write only when `fix` is enabled. Gallery audits regenerate sizes only when repair is enabled. Switcher reset deletes custom configuration: inspect it first. Its legacy rebuild operation reports a reset, not proof that a rendered switcher works.

An integrity result is a review aid. Shared brand names can be correct in both languages; modified dates do not prove that a translation is outdated. Supply relevant source-language markers and review the actual source and target. URL resolution is limited to links WordPress and the site's URL configuration can resolve.

Trustpilot locale checks use the post's configured regional locale. A bare language code does not establish a country. Yoast controls its redirect exports; the deprecated `clean_htaccess` input no longer edits files independently. Custom URIs use Permalink Manager's own functions and permalink formats.

## Installation


For update notifications in WordPress, install [Devenia MCP Updater](https://downloads.devenia.com/devenia-mcp-updater.zip). The updater is optional. You choose which plugins update automatically through WordPress.

1. Install and configure WPML and your authenticated WordPress ability connection.
2. [Download the plugin ZIP](https://downloads.devenia.com/mcp-abilities-sitepress.zip).
3. Upload it through **Plugins → Add New → Upload Plugin**, then activate it.
4. Confirm that the `wpml/` abilities are available to the intended WordPress user.

## Recent Changes

### 0.3.73

Use the native document-editor ATE value independently from the job-editor enum. Require effective ATE readback before confirming an Advanced editor change.

### 0.3.72

Preserve the manual translation editor when disabling automation. Add a separate reviewed native editor patch and precise partial-write readback. Preserve local queue results when external ATE verification is unavailable; query only unfinished ATE work.

### 0.3.71

Use automatic WordPress loading of an optional site-managed translation catalog for the missing Formidable file helper. Sites without a catalog keep the original text; existing translations and other domains remain unchanged.


### 0.3.67

Translate the missing Norwegian Formidable Pro file-size helper while preserving existing translations, the size placeholder and English output.

### 0.3.66

Correct an existing registered string's source language through WPML's native language setter. Require an active language, native management permissions and reviewed source/translation hashes. Preserve text, translation metadata and active work; verify the saved result and retain native status and cache handling.

### 0.3.65

Read and translate explicitly selected registered option-string domains through the same native WPML APIs as content strings. Preserve original values, reviewed source/target hashes, native permissions and active job progress.

### 0.3.64

- Keep existing translated post content and native Elementor documents intact during URL-only saves.
- Refuse URL updates when the MCP Expose Abilities translation guard is unavailable or has not captured every WPML sibling. Native slug uniqueness and old-slug history remain in use.

### 0.3.63

- Translate the native caption/title alt fallback of Elementor images, including custom-size Posts thumbnails.
- Preserve empty stored alt fields, explicit alternatives, media identity, native markup, original-language output and raw administrative reads.

### 0.3.62

- Let the public alt text of explicitly selected native images and galleries use String Translation despite an old unpublished attachment parent.
- Preserve hidden private metadata, document visibility, source storage, media identity and all existing permissions and abilities.

### 0.3.61

- Add a bounded, previewable repair for misplaced Elementor thumbnail references. Explicit size keys and a reviewed metadata hash restrict changes to selected invalid entries while preserving image files and other metadata.

### 0.3.60

- Native signed Elementor public previews use the previewed post’s WPML language, including drafts in another language.
- Existing Elementor signatures, expiry, revision selection, privacy headers and native rendering remain in use; ordinary public, admin, REST and CLI requests keep their own language.

### 0.3.58

- Resolve retained-ID image tooltip titles through the native WordPress attachment-title Interface, including custom-size Elementor Loop images.
- Reuse an existing public translated media record even when the original has an old unpublished parent; never register unpublished source fields as public translation strings.
- Preserve ordinary content titles, explicitly filtered titles, native formatting and disabled/empty title choices.

### 0.3.56

- Resolve native Elementor Gallery/lightbox text and frontend attachment alt values through WPML without substituting media.
- Reuse native String Translation for missing attachment text; preserve original content and raw administrative reads.

### 0.3.55

Allow an existing translation to move to a new original when the caller supplies its reviewed previous group ID. Require edit access to both originals, preserve source and target languages, reject occupied target slots, and verify the native WPML relationship. Calls without explicit reassignment retain the existing group protection.

### 0.3.54

Add selected native language administration, safe term links, content-string inventory and reviewed target updates, local translator selection and first-job dispatch, allowlisted general settings, diagnostics, cache refresh and secure registration status/workflow. Preserve native capabilities, REST nonce validation, existing translation groups and unfinished work. Full WPML administration remains outside this release.

### 0.3.53

Add native translation controls, manual mode, current and historical job inventory, exact cancellation previews, verified native cancellation and an explicit administration coverage map. Remote status errors preserve local jobs; finished translations and newly added jobs are excluded from repeated cancellation. Registration credentials are not exposed.

### 0.3.52

Correct exact redirect deletion to use the native Yoast target getter. Destination matching and native redirect exports remain intact.


### 0.3.51

Add one dismissible Plugins-screen reminder when Devenia MCP Updater is missing or inactive, with persistent install or activate links. Automatic updates remain your choice in WordPress.

### 0.3.50

- Added per-post access checks and verification of translation writes.
- Prevented conflicting language links and kept unlinked shells as drafts.
- Preserved native slug uniqueness, URL history, and exact URL replacement.
- Used configured regional locales and native Contact Form 7, Yoast, and Permalink Manager operations.
- Clarified switcher reset results and completed the public ability inventory.

Earlier changes are listed in `readme.txt`.

## Contributing

Describe the affected ability, plugin versions, input, and observed result. Use a minimal example without credentials or private content. Keep language relationships in WPML and storage operations in their owning WordPress plugins.

## License and Author

GPLv2 or later. Author: [basicus](https://profiles.wordpress.org/basicus/).

## Links

- [Plugin guide](https://devenia.com/plugins/mcp-abilities-sitepress/)
- [Download](https://downloads.devenia.com/mcp-abilities-sitepress.zip)
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
