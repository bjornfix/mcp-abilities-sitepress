# Runtime dependencies

- [WordPress 6.9+](https://wordpress.org/documentation/wordpress-version/version-6-9/) supplies the native Abilities API and WordPress content, taxonomy, media, and permission Interfaces used by the plugin.
- [PHP 8.0+](https://www.php.net/releases/8.0/en.php) is the minimum PHP runtime for the plugin.
- [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/) registers the 35 SitePress operations as typed WordPress abilities.
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) transports registered WordPress abilities to authenticated MCP clients.
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/) exposes the registered public abilities through the controlled Devenia MCP surface.
- [WPML](https://wpml.org/) owns the language, translation-group, translated URL, language-switcher, and multilingual content state used by every registered operation.

- Native job administration and manual controls use WPML 4.7+ translation-management repositories, previous-job lookup, ATE client, state transitions and settings. WPML remains the owner of external translation work and registration.
