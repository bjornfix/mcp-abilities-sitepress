# MCP Abilities - SitePress

Find missing WPML translations, reuse the right translated post, and repair links that still send readers back to the source language. MCP Abilities - SitePress gives an authenticated assistant access to these jobs inside WordPress.

[![Release 0.3.53](https://img.shields.io/badge/release-0.3.53-blue.svg)](https://downloads.devenia.com/mcp-abilities-sitepress.zip)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)

**Stable tag:** 0.3.53 · **Tested up to:** WordPress 7.1 · **License:** GPLv2 or later

**Tags:** mcp, wpml, translation, ai, automation

## What It Does

The plugin exposes 35 WordPress abilities for WPML language discovery, translation groups, translation shells, link checks, content checks, and selected integration repairs. WPML keeps ownership of language relationships. WordPress keeps ownership of posts, terms, media, and permissions.

It can identify a translated service page that still links to the original contact page, show the linked replacement, and apply that replacement when requested. It can also distinguish a missing translation from an existing draft that can be reused.

The plugin does not write translations or judge whether a translated explanation suits its readers. Text comparisons identify material to review; they are not a language-quality score.

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
- The matching plugin for optional operations: Elementor, Contact Form 7, Yoast SEO Premium, or Permalink Manager. These are not translation engines supplied by this plugin.

## Documentation

Read the [plugin guide](https://devenia.com/plugins/mcp-abilities-sitepress/), [WPML hooks reference](https://wpml.org/documentation/support/wpml-coding-api/wpml-hooks-reference/), and [WordPress Abilities API documentation](https://developer.wordpress.org/apis/abilities-api/).

## Start Here

Ask your assistant to inspect a source page's translation group and report which language is missing. Keep the first request read-only. If a target already exists, inspect that post before asking for a new translation shell.

## Complete Ability Inventory

| Ability | Purpose |
| --- | --- |
| `wpml/get-translation-controls` | Read selected automation, editor, delivery and ATE activation controls without keys |
| `wpml/enable-manual-translation` | Disable new automatic translation, ATE delivery and string collection through native settings |
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
| `wpml/repair-elementor-gallery-media` | Regenerate missing image sizes for selected gallery attachments |
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

### Manual translation and job administration

Read `wpml/get-translation-controls` before requesting `wpml/enable-manual-translation` with `{"confirm": true}`. Manual mode selects native WordPress editing, the classic WPML document editor, review before publishing, manual service pickup, and disabled automatic draft translation and string collection. Selecting the global native editor clears per-post editor overrides through WPML's own controls. It preserves language relationships and translation content. Existing external jobs and previously consumed credits are not cancelled or refunded. Re-enabling ATE can make old work deliverable again.

List jobs with `wpml/list-translation-jobs`:

```json
{"source_ids": [123], "include_history": true, "check_ate": true, "limit": 100}
```

An empty `source_ids` array covers the native site queue. The native repository supplies current jobs; WPML's previous-job operation supplies history for those groups. Unknown external status is reported explicitly. `external_verification_complete` describes unfinished jobs; finished translations are excluded from cancellation. Translation-service jobs that cannot be safely cancelled through the local native workflow remain blockers.

Request `wpml/preview-translation-job-cancellation` with the reviewed source scope. It returns the exact IDs, count, loss warning and a preview valid for fifteen minutes for the same authenticated user. Only after authorizing that exact action, call `wpml/cancel-translation-jobs`:

```json
{"preview_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee", "confirm": true, "acknowledge_loss": true}
```

The ID above is illustrative. Cancellation requires ATE delivery to be disabled through native manual controls. It rejects changed local jobs, unreadable external state and missing acknowledgement. ATE cancellation and the absence of deliverable XLIFF in the native priority response must be confirmed before local changes. Failure can leave partial external cancellation; re-read the queue before retrying. Native state transitions preserve completed translations. A repeated successful preview returns its receipt and reads current queue state without cancelling newly added jobs. Cancellation can discard unfinished progress and does not refund consumed credits. The preview does not grant permission by itself.

`wpml/get-administration-coverage` reports remaining gaps. This release does not provide full WPML administration, registration-key entry, account connections, payments or subscriptions.

### Frontend integration

The `[mcp_wpml_language_flag]` shortcode renders a linked flag from WPML language data. The plugin also maps linked Contact Form 7 shortcodes to the current language and provides WPML sibling IDs to compatible Elementor editing tools. These features use existing translations.

## Permissions and Limits

The ability permission check and the affected post's permission both apply. Administrative recovery and translation linking require `manage_options`; media operations also require media permissions. When String Translation is active, manual controls also require its native string-management or translation-management capability. A connected assistant inherits the WordPress user's access.

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
