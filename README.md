# MCP Abilities - SitePress

WPML translation mapping and translation-shell helper abilities for MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-sitepress)](https://github.com/bjornfix/mcp-abilities-sitepress/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

**Tested up to:** 7.0
**Stable tag:** 0.3.39
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

WPML translation mapping and translation-shell helper abilities for MCP.

This plugin is part of the Devenia MCP abilities ecosystem. It gives an MCP-capable agent a focused, authenticated way to work with SitePress work inside WordPress through MCP.

It also provides `[mcp_wpml_language_flag]`, a frontend shortcode that renders a linked flag for another active WPML language using WPML language data.

## Changelog

### 0.3.39

- Improved translation integrity shared-term detection so shared neutral terms
  such as `design` and `aluminium` are not reported as untranslated source text.

### 0.3.38

- Improved translation integrity shared-term detection so legitimate shared
  material names such as `plexiglass` and `lexan` are not reported as
  untranslated source text.

### 0.3.37

- Improved translation integrity shared-term detection so legitimate shared
  product and technology terms such as `PRIVA-LITE` and `intelligent` are not
  reported as untranslated source text.

### 0.3.36

- Improved translation integrity frontend marker defaults so global Trustpilot
  locale markup is not reported as untranslated page content.

### 0.3.35

- Improved translation integrity shared-term detection so layout terms and
  domain-neutral glass terminology are ignored instead of being reported as
  untranslated source text.

### 0.3.34

- Improved translation integrity detection so Elementor global style tokens are
  ignored instead of being reported as untranslated source text.

### 0.3.33

- Improved `wpml/list-posts` so `lang` works as an alias for `target_lang`,
  making search queries easier to call consistently from agents.

### 0.3.32

- Added `wpml/list-posts` for language-scoped WPML post/page/CPT queries,
  including category, status, search, order, pagination, and translation
  metadata in the result.

### 0.3.31

- Improved the Elementor translation-sibling adapter registration so the WPML
  provider binds to Elementor's guarded-write seam across normal WordPress load
  order variations.

### 0.3.30

- Added a WPML translation sibling provider for Elementor guarded writes, so
  translated Elementor documents can be preserved independently during
  WPML-linked page updates.

### 0.3.29

- Continued the architecture split by moving translation mutation, link audits, language-switcher recovery, translation shell/integrity checks, and Elementor gallery media abilities into dedicated modules.

### 0.3.28

- Fixed `wpml/find-translation-candidates` to always include the already linked WPML target when one exists, even when the source-language title does not match the translated title.

### 0.3.27

- Added `wpml/find-translation-candidates` to find reusable target-language publish/draft/trash candidates before creating duplicate translations.

### 0.3.26

- Added `wpml/get-post-translations` for read-only WPML translation-group lookup across posts, pages, and custom post types.
- Started the architecture split by moving read-only language and translation query abilities into a dedicated module.

### 0.3.25

- Added `wpml/set-post-language-details` for registering WPML language details on existing post/CPT items that have no language metadata yet.

**Example:** "Handle this WordPress maintenance task directly." - The agent can inspect the site, call the relevant ability, and return the result without making the human click through wp-admin for every step.

## The Real Workflow

In practice, the human should not have to memorize every ability name.

The normal pattern is:

1. install the base MCP stack
2. install only the add-ons the site actually needs
3. let the agent discover the available abilities
4. give the agent a clear task with boundaries
5. verify the result in WordPress

The human's job is mostly to describe the goal.
The agent's job is to figure out the mechanics.

## Why This Feels Different

Most WordPress automation still leaves the repetitive part to the human.

This plugin is different because the agent can act inside the site through a narrow, authenticated ability surface:

- inspect current site state before changing anything
- run the specific action needed for the task
- return structured results that are easy to verify
- keep the workflow inside WordPress instead of a separate checklist

That changes the experience from:

- `Here is what you should do in wp-admin`

to:

- `Tell the agent what needs doing, and let it carry out the work`

## Before vs After

### Before

- ask the AI what to do
- copy the answer into WordPress by hand
- click through wp-admin for the repetitive bits
- postpone maintenance because the task is tedious

### After

- tell the agent what needs doing
- let it inspect the relevant WordPress state
- let it run the targeted ability
- verify the result and move on

## Who It Is For

This is a good fit for:

- agencies managing WordPress sites with AI-assisted maintenance
- operators who want agents to do real WordPress work instead of producing instructions
- teams already using MCP Expose Abilities
- sites where this WordPress area is updated often enough to deserve automation

It is especially useful when the manual version is repetitive enough that important maintenance gets delayed.

## Documentation

Start with the main plugin page and base stack documentation:

- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)
- [Install Order and Dependencies](https://github.com/bjornfix/mcp-expose-abilities/wiki/Install-Order-and-Dependencies)

If you are using an AI agent, the simplest instruction is often just:

- `Read https://github.com/bjornfix/mcp-expose-abilities and figure out the stack before making changes.`

## Start Here

If you are new to the stack, use this order:

1. Install **Abilities API**.
2. Install **MCP Adapter**.
3. Install **MCP Expose Abilities**.
4. Install **MCP Abilities - SitePress**.
5. Confirm the new abilities appear in discovery.
6. Give the agent a clear task that uses this add-on.

If you skip base-stack verification and start with add-ons immediately, troubleshooting gets harder than it needs to be.

## Abilities (25)

| Ability | Description |
|---------|-------------|
| `wpml/list-page-translation-status` | List translation status for source pages and target languages |
| `wpml/ensure-page-translation` | Create and link a target translation shell for a source page |
| `wpml/ensure-post-translation` | Create and link a target translation shell for a source post, page, or custom post type |
| `wpml/update-translated-post-url` | Update translated post slug, categories, and primary category in the target WPML language context |
| `wpml/audit-elementor-language-assets` | Audit/fix translated Elementor global widget template references and Trustpilot locales |
| `wpml/detect-untranslated-content` | Detect copied/untranslated source-language fragments in target content |
| `wpml/list-active-languages` | List active WPML languages with normalized metadata |
| `wpml/list-posts` | List posts, pages, or custom post types in an explicit WPML language context |
| `wpml/get-element-language-details` | Read normalized WPML language details for a page/post element |
| `wpml/get-post-translations` | Read the WPML translation group for a post/page/CPT |
| `wpml/find-translation-candidates` | Find reusable target-language translation candidates before creating duplicates |
| `wpml/set-post-language-details` | Register WPML language details for an existing post/CPT item |
| `wpml/link-post-translation` | Link an existing post/CPT item as a WPML translation |
| `wpml/audit-translated-links` | Audit or fix translated content links that still point to source-language originals |
| `wpml/audit-translated-links-batch` | Batch-audit translated links across translated posts |
| `wpml/audit-translation-coverage` | Report missing or stale target-language translations |
| `wpml/get-language-switcher-settings` | Read normalized WPML language-switcher settings and overview |
| `wpml/list-language-switcher-slots` | Inspect language-switcher slots across statics, menus, and sidebars |
| `wpml/validate-language-switcher-settings` | Validate WPML language-switcher option structure before changes |
| `wpml/reset-language-switcher-settings` | Delete switcher settings so WPML can rebuild them |
| `wpml/rebuild-language-switcher-settings` | Reset and re-read switcher settings through a recovery path |
| `wpml/audit-translation-integrity` | Audit translated content for source text and source URL segments |
| `wpml/remove-yoast-redirect` | Remove an exact Yoast Premium redirect |
| `wpml/repair-elementor-gallery-media` | Repair translated Elementor gallery media metadata/files |
| `wpml/audit-elementor-gallery-media` | Audit translated Elementor galleries and media captions |
| `wpml/update-media-captions-batch` | Batch update translated media captions |

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

### 0.3.24

- Cleared Plugin Check warnings for the public language-flag shortcode release.

### 0.3.23

- Added a public WPML-powered language flag shortcode for theme/header inserts.

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

## Contributing

PRs welcome. Keep changes focused on the plugin's WordPress ability surface and preserve authenticated, explicit workflows.

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [GitHub Releases](https://github.com/bjornfix/mcp-abilities-sitepress/releases)

## Star and Share

If this plugin saves you time or makes WordPress maintenance easier to verify, please:

- star the repo
- share it with people running WordPress sites
- point them to the main plugin page so they can see what the ecosystem can actually do

Why do it?

Because agent-friendly open WordPress tooling helps more of the boring but important work get done.
