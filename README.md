# MCP Abilities - SitePress

SitePress (WPML) translation management for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-sitepress)](https://github.com/bjornfix/mcp-abilities-sitepress/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 0.3.22
**Requires PHP:** 8.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

This add-on plugin exposes SitePress (WPML) workflows through MCP (Model Context Protocol). Your AI assistant can inspect translation mapping, create missing translation shells, run untranslated-content checks, and safely inspect/recover WPML language-switcher state.

**Part of the [MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities) ecosystem.**

This is one piece of a bigger open WordPress automation stack that lets AI agents do real multilingual maintenance instead of leaving teams buried in WPML admin work.

## Why This Is Cool

Translation QA, missing shells, and language-switcher recovery are the kind of jobs people avoid because they are slow and fiddly.

This add-on makes that promptable. You can tell the agent to inspect translation status, create only the missing shells, detect copied source-language text, and keep multilingual cleanup moving without the usual admin fatigue.

## Documentation

- [Core Plugin: MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities)
- [MCP Wiki Home](https://github.com/bjornfix/mcp-expose-abilities/wiki)
- [Why Teams Use It](https://github.com/bjornfix/mcp-expose-abilities/wiki/Why-Teams-Use-It)
- [Use Cases](https://github.com/bjornfix/mcp-expose-abilities/wiki/Use-Cases)
- [SitePress / WPML Add-On Guide](https://github.com/bjornfix/mcp-expose-abilities/wiki/Addon-SitePress)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)

## Requirements

- WordPress 6.9+
- PHP 8.0+
- [Abilities API](https://github.com/WordPress/abilities-api) plugin
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin
- [WPML](https://wpml.org/) with SitePress active

## WordPress Compatibility

- Requires WordPress 6.9 or newer
- Tested up to WordPress 6.9
- Requires PHP 8.0 or newer
- Intended for sites running WPML SitePress within that same WordPress release line

## Installation

1. Install the required plugins (Abilities API, MCP Adapter, WPML/SitePress)
2. Download the latest release from [Releases](https://github.com/bjornfix/mcp-abilities-sitepress/releases)
3. Upload via WordPress Admin > Plugins > Add New > Upload Plugin
4. Activate the plugin

## Abilities (13)

| Ability | Description |
|---------|-------------|
| `wpml/list-page-translation-status` | List translation status for source pages and target languages |
| `wpml/ensure-page-translation` | Create and link a target translation shell for a source page |
| `wpml/ensure-post-translation` | Create and link a target translation shell for a source post, page, or custom post type |
| `wpml/update-translated-post-url` | Update translated post slug, categories, and primary category in the target WPML language context |
| `wpml/audit-elementor-language-assets` | Audit/fix translated Elementor global widget template references and Trustpilot locales |
| `wpml/detect-untranslated-content` | Detect copied/untranslated source-language fragments in target content |
| `wpml/list-active-languages` | List active WPML languages with normalized metadata |
| `wpml/get-element-language-details` | Read normalized WPML language details for a page/post element |
| `wpml/get-language-switcher-settings` | Read normalized WPML language-switcher settings and overview |
| `wpml/list-language-switcher-slots` | Inspect language-switcher slots across statics, menus, and sidebars |
| `wpml/validate-language-switcher-settings` | Validate WPML language-switcher option structure before changes |
| `wpml/reset-language-switcher-settings` | Delete switcher settings so WPML can rebuild them |
| `wpml/rebuild-language-switcher-settings` | Reset and re-read switcher settings through a recovery path |

## Usage Examples

### List translation status

```json
{
  "ability_name": "wpml/list-page-translation-status",
  "parameters": {
    "source_lang": "no",
    "target_lang": "en",
    "per_page": 20,
    "page": 1
  }
}
```

### Ensure translation shell exists

```json
{
  "ability_name": "wpml/ensure-post-translation",
  "parameters": {
    "source_id": 123,
    "target_lang": "en",
    "target_status": "publish",
    "copy_elementor": true,
    "copy_taxonomies": true
  }
}
```

### Detect untranslated content

```json
{
  "ability_name": "wpml/detect-untranslated-content",
  "parameters": {
    "source_id": 123,
    "target_id": 456,
    "ignore_terms": ["devenia", "oslo"]
  }
}
```

### Audit translation coverage

```json
{
  "ability_name": "wpml/audit-translation-coverage",
  "parameters": {
    "source_lang": "no",
    "target_lang": "en",
    "post_types": ["page", "post", "elementor_library"],
    "status": "publish",
    "include_stale": true
  }
}
```

## Changelog

### 0.3.22

- Added Elementor gallery media audit/repair abilities and batch media-caption updates for translated content.
- Added Yoast redirect removal and expanded translation integrity checks for migrated multilingual pages.

### 0.3.15

- Added language-aware configured option translations for values such as `blogdescription`, so schema/tagline output can differ per WPML language without changing the default-language option.
- Extended `wpml/audit-translation-integrity` to report `[insert page='...']` shortcodes that still point at a source-language post/template.

### 0.3.14

- Added `wpml/audit-translation-integrity` to audit translated posts/pages for untranslated source text, source-language URL segments, and optional rendered frontend markers.
- Expanded `wpml/detect-untranslated-content` so it works for posts and other post types, not only pages.

### 0.3.13
- Added `wpml/audit-elementor-language-assets` to detect and optionally fix translated Elementor content that still references source-language global widget templates or wrong Trustpilot locales.

### 0.3.12
- Extended `wpml/update-translated-post-url` to update Permalink Manager custom URIs so old source-language custom URLs no longer override translated slugs.

### 0.3.11
- Added `wpml/update-translated-post-url` to update translated post slugs, categories, and Yoast/Rank Math primary categories in the correct WPML language context.

### 0.3.10
- Added `wpml/ensure-post-translation` to create proper WPML-linked translations for pages, posts, and custom post types while copying Elementor data, featured images, selected meta, and translated taxonomy terms where available.

### 0.3.9
- Added `wpml/audit-translation-coverage` to report published source-language pages, posts, and templates that are missing or older than target-language translations.

### 0.3.8
- Tightened URL extraction for translated-link audit so unresolved checks do not treat closing tags, shortcode fragments, or prose with slashes as URLs.

### 0.3.7
- Extended translated-link audit to flag unresolved internal page-like URLs that are not under the target language prefix, so broken/source-language-looking links are visible even when `url_to_postid()` cannot map them to a translated post.

### 0.3.6
- Added `wpml/audit-translated-links-batch` to scan explicit IDs or all translated posts of a post type for source-language internal links, with optional batch replacement.

### 0.3.5
- Tightened translated-link audit so URLs already using the target language path prefix, such as `/en/...`, are not flagged as source-language links.

### 0.3.4
- Added `wpml/audit-translated-links` to detect internal links in translated content/Elementor data that still point to source-language originals, with optional `fix=true` replacement to translated URLs.

### 0.3.3
- Added `wpml/link-post-translation` for linking existing post/CPT items, including Elementor library templates, as WPML translations.
- Made element language detail lookup post-type-aware instead of page-only.

### 0.3.2
- Fixed: removed tracked hidden files from the release package so WP.org Plugin Check passes

### 0.3.1
- Docs: added explicit WordPress and PHP compatibility notes
- Docs: corrected the documented ability count to match the current 10 registered abilities

### 0.3.0
- Added safe WPML administration abilities around active languages and element language details
- Added language-switcher inspection, slot listing, validation, reset, and rebuild abilities
- Hardened WPML operational workflow so switcher recovery no longer depends on raw option writes

### 0.2.5
- Renamed plugin display name to SitePress for trademark-safe naming
- Removed site-specific wording from documentation
- Added link to plugin page on devenia.com

### 0.2.4
- Added output schemas and MCP meta annotations for all abilities
- Added WP.org `readme.txt` for release parity

### 0.2.2
- Improved untranslated-content detection tunables

### 0.2.1
- Added `wpml/detect-untranslated-content`

### 0.2.0
- Added `wpml/ensure-page-translation`

### 0.1.0
- Initial release with translation status listing

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Free and Open

Like the rest of the ecosystem, this add-on is free, open, and built from practical production work.

## Star and Share

If this add-on helps, please star the repo, share the ecosystem, and point people to the main wiki:

- https://github.com/bjornfix/mcp-expose-abilities
- https://github.com/bjornfix/mcp-expose-abilities/wiki

## Links

- [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
- [Main Wiki](https://github.com/bjornfix/mcp-expose-abilities/wiki)
- [SitePress / WPML Add-On Guide](https://github.com/bjornfix/mcp-expose-abilities/wiki/Addon-SitePress)
