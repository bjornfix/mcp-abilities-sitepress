# MCP Abilities - SitePress

SitePress (WPML) translation management for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-sitepress)](https://github.com/bjornfix/mcp-abilities-sitepress/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 0.2.5
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

This add-on plugin exposes SitePress (WPML) translation workflows through MCP (Model Context Protocol). Your AI assistant can inspect translation mapping, create missing translation shells, and run untranslated-content checks before publish.

**Part of the [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/) ecosystem.**

## Requirements

- WordPress 6.9+
- PHP 8.0+
- [Abilities API](https://github.com/WordPress/abilities-api) plugin
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin
- [WPML](https://wpml.org/) with SitePress active

## Installation

1. Install the required plugins (Abilities API, MCP Adapter, WPML/SitePress)
2. Download the latest release from [Releases](https://github.com/bjornfix/mcp-abilities-sitepress/releases)
3. Upload via WordPress Admin > Plugins > Add New > Upload Plugin
4. Activate the plugin

## Abilities (3)

| Ability | Description |
|---------|-------------|
| `wpml/list-page-translation-status` | List translation status for source pages and target languages |
| `wpml/ensure-page-translation` | Create and link a target translation shell for a source page |
| `wpml/detect-untranslated-content` | Detect copied/untranslated source-language fragments in target content |

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

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/)
- [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
- [All Add-on Plugins](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
