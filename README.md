# MCP Abilities - SitePress

SitePress (WPML) translation management for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-sitepress)](https://github.com/bjornfix/mcp-abilities-sitepress/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 0.3.2
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

## Abilities (10)

| Ability | Description |
|---------|-------------|
| `wpml/list-page-translation-status` | List translation status for source pages and target languages |
| `wpml/ensure-page-translation` | Create and link a target translation shell for a source page |
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
  "ability_name": "wpml/ensure-page-translation",
  "parameters": {
    "source_id": 123,
    "target_lang": "en",
    "copy_elementor_meta": true
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

## Changelog

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
