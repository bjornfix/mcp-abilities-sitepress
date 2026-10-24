<?php
/**
 * Use standard i18n for a missing public Formidable Pro file-size helper.
 *
 * @package MCP_Abilities_SitePress
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserve native translations and use this adapter's standard catalog fallback.
 *
 * @param string $translated Existing translated text.
 * @param string $text       Original gettext message.
 * @param string $domain     Owning plugin text domain.
 * @return string
 */
function mcp_wpml_public_file_helper_language( $translated, $text, $domain ) {
	if ( $translated !== $text || is_admin() || 'formidable-pro' !== $domain || 'Maximum file size: %s' !== $text ) {
		return $translated;
	}
	/* translators: %s: Maximum upload size supplied by Formidable Pro. */
	return __( 'Maximum file size: %s', 'mcp-abilities-sitepress' );
}
add_filter( 'gettext', 'mcp_wpml_public_file_helper_language', 20, 3 );
