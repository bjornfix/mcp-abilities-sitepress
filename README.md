# MCP Abilities - SitePress

Find missing WPML translations, reuse the right translated post, and repair links that still send readers back to the source language. MCP Abilities - SitePress gives an authenticated assistant access to these jobs inside WordPress.

[![Release 0.3.51](https://img.shields.io/badge/release-0.3.51-blue.svg)](https://downloads.devenia.com/mcp-abilities-sitepress.zip)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)

**Stable tag:** 0.3.51 · **Tested up to:** WordPress 7.1 · **License:** GPLv2 or later

**Tags:** mcp, wpml, translation, ai, automation

## What It Does

The plugin exposes 29 WordPress abilities for WPML language discovery, translation groups, translation shells, link checks, content checks, and selected integration repairs. WPML keeps ownership of language relationships. WordPress keeps ownership of posts, terms, media, and permissions.

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
- An active, configured WPML SitePress installation. These abilities are not registered without WPML.
- An authenticated connection that exposes WordPress abilities to your assistant, such as [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/).
- The matching plugin for optional operations: Elementor, Contact Form 7, Yoast SEO Premium, or Permalink Manager. These are not translation engines supplied by this plugin.

## Documentation

Read the [plugin guide](https://devenia.com/plugins/mcp-abilities-sitepress/), [WPML hooks reference](https://wpml.org/documentation/support/wpml-coding-api/wpml-hooks-reference/), and [WordPress Abilities API documentation](https://developer.wordpress.org/apis/abilities-api/).

## Start Here

Ask your assistant to inspect a source page's translation group and report which language is missing. Keep the first request read-only. If a target already exists, inspect that post before asking for a new translation shell.

## Complete Ability Inventory

| Ability | Purpose |
| --- | --- |
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

### Frontend integration

The `[mcp_wpml_language_flag]` shortcode renders a linked flag from WPML language data. The plugin also maps linked Contact Form 7 shortcodes to the current language and provides WPML sibling IDs to compatible Elementor editing tools. These features use existing translations.

## Permissions and Limits

The ability permission check and the affected post's permission both apply. Administrative recovery and translation linking require `manage_options`; media operations also require media permissions. A connected assistant inherits the WordPress user's access.

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
