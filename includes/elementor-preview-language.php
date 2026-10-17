<?php
/**
 * Keep native signed Elementor previews in the previewed post's WPML language.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

use Elementor\Modules\Mcp\Preview\Preview_Token;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Align request language after Elementor has validated and selected its preview.
 *
 * Elementor owns the token, snapshot, permissions, rendering and privacy headers.
 * This Adapter only supplies WPML context for that existing main-query selection.
 *
 * @param WP $wp Parsed WordPress request.
 */
function mcp_wpml_elementor_preview_language(WP $wp): void {
	if (!defined('ICL_SITEPRESS_VERSION') || is_admin()
		|| (defined('REST_REQUEST') && REST_REQUEST)
		|| (defined('WP_CLI') && WP_CLI)
		|| !class_exists(Preview_Token::class)
		|| !is_callable(array(Preview_Token::class, 'decode'))
		|| !is_callable(array(Preview_Token::class, 'secret'))
		|| !is_callable(array(Preview_Token::class, 'is_expired'))) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor verifies its own signed, expiring token below.
	$raw_token = $_GET[Preview_Token::QUERY_ARG] ?? null;
	if (!is_string($raw_token) || '' === $raw_token) {
		return;
	}

	$claims = Preview_Token::decode(sanitize_text_field(wp_unslash($raw_token)), Preview_Token::secret());
	if (null === $claims || Preview_Token::is_expired($claims, time())) {
		return;
	}

	$post_id = (int) $claims['post_id'];
	if ($post_id <= 0 || (int) ($wp->query_vars['p'] ?? 0) !== $post_id
		|| (int) wp_is_post_revision((int) $claims['revision_id']) !== $post_id) {
		return;
	}

	$post = get_post($post_id);
	if (!$post || 'revision' === $post->post_type || ($wp->query_vars['post_type'] ?? '') !== $post->post_type) {
		return;
	}

	$details = mcp_wpml_lang_details($post_id, (string) $post->post_type);
	$language = is_object($details) ? ($details->language_code ?? '') : '';
	if (!is_string($language) || '' === $language) {
		return;
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
	if ($language !== apply_filters('wpml_current_language', null)) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML; request context only, without a cookie.
		do_action('wpml_switch_language', $language);
	}
}

// Elementor's native Public_Preview_Handler activates on parse_request priority1.
add_action('parse_request', 'mcp_wpml_elementor_preview_language', 2);
