# MCP Abilities - SitePress

Small bridge plugin that exposes WPML translation mapping as MCP abilities.

**Stable tag:** 0.2.5

## Abilities

- `wpml/list-page-translation-status`
- `wpml/ensure-page-translation`
- `wpml/detect-untranslated-content`

## Why this exists

WPML handles translation, but MCP workflows still need explicit translation
linkage controls (source page -> target page). This plugin adds that control
layer so translation can run page-by-page without guessing IDs, and adds an
automatic language-agnostic QA check before publish.

## Notes

- Plugin page: [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- Requires `abilities-api` and WPML (`sitepress-multilingual-cms`) to be active.
- `wpml/ensure-page-translation` can create target translation shells and copy
  Elementor metadata from source pages.
- `wpml/detect-untranslated-content` compares source and target page text and
  flags likely untranslated leftovers (shared terms + exact copied segments).
- Detection tuning defaults:
  - `min_target_count_for_flag=2` (ignore one-off shared terms in target)
  - `min_shared_terms_for_flag=2` (do not flag on a single shared term alone)
  - Use `ignore_terms` for known shared brand/location words.
- All abilities now publish output schemas and MCP annotations for better AI interoperability.
