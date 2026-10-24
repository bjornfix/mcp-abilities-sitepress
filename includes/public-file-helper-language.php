<?php
/**
 * Preserve the owning Formidable renderer and translate its missing file helper.
 *
 * @package MCP_Abilities_SitePress
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Return only the missing Norwegian Formidable Pro catalog entry. */
function mcp_wpml_public_file_helper_language( $translated, $text, $domain ) {
	if ( $translated !== $text || is_admin() || 'formidable-pro' !== $domain || 'Maximum file size: %s' !== $text ) {
		return $translated;
	}
	if ( 'no' !== apply_filters( 'wpml_current_language', null ) ) {
		return $translated;
	}
	return 'Maksimal filstørrelse: %s';
}
add_filter( 'gettext', 'mcp_wpml_public_file_helper_language', 20, 3 );
