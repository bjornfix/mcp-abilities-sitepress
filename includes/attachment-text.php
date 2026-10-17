<?php
/** WPML attachment text resolution; image identity and native renderers stay authoritative. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MCP_WPML_Attachment_Text {
	private static bool $reading = false;
	private static array $cache = array();
	const STRING_DOMAIN = 'MCP Media Text';

	public static function frontend(): bool {
		return defined('ICL_SITEPRESS_VERSION') && !is_admin()
			&& !(defined('REST_REQUEST') && REST_REQUEST)
			&& !(defined('WP_CLI') && WP_CLI);
	}

	public static function reading(): bool { return self::$reading; }

	/** Read native fields without allowing the public alt filter to translate source evidence. */
	private static function raw(WP_Post $post): array {
		return array('title' => (string) $post->post_title, 'caption' => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
			'alt' => (string) get_post_meta($post->ID, '_wp_attachment_image_alt', true));
	}

	/** Translate text only. Never substitute an attachment ID, image URL, dimensions or file. */
	public static function get(int $id): array {
		$post = get_post($id);
		if (!$post instanceof WP_Post || 'attachment' !== $post->post_type) { return array(); }
		$was_reading = self::$reading;
		self::$reading = true;
		try {
			$source = self::raw($post);
			if (!self::frontend()) { return $source; }
			$public_source = is_post_publicly_viewable($post);
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML language hook.
			$lang = apply_filters('wpml_current_language', null);
			$details = mcp_wpml_lang_details($id, 'attachment');
			$source_lang = $details ? (string) ($details->language_code ?? '') : '';
			if (!is_string($lang) || '' === $lang || 'all' === $lang || '' === $source_lang || $lang === $source_lang) { return $source; }
			$key = $id . ':' . $lang;
			if (isset(self::$cache[$key])) { return self::$cache[$key]; }
			$target_id = mcp_wpml_target_id_for_post_type($id, 'attachment', $lang);
			$target = $target_id > 0 && $target_id !== $id ? get_post($target_id) : null;
			$translated = array();
			if ($target instanceof WP_Post && 'attachment' === $target->post_type
				&& is_post_publicly_viewable($target)) {
				$translated = self::raw($target);
			}
			$result = $source;
			foreach ($source as $field => $value) {
				if (isset($translated[$field]) && '' !== trim($translated[$field])) {
					$safe = self::safe($field, $translated[$field]);
					if ('' !== trim($safe)) { $result[$field] = $safe; continue; }
				}
				if (!$public_source || '' === trim($value)) { continue; }
				// A missing media record/field uses WPML's existing String Translation store.
				// Register the public source text, not a new attachment or page-specific overlay.
				$name = 'attachment-' . $id . '-' . $field;
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML dynamic-string registration.
				do_action('wpml_register_single_string', self::STRING_DOMAIN, $name, $value, false, $source_lang);
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML String Translation lookup.
				$text = apply_filters('wpml_translate_single_string', $value, self::STRING_DOMAIN, $name, $lang);
				if (is_string($text) && '' !== trim($text) && $text !== $value) { $result[$field] = self::safe($field, $text); }
			}
			self::$cache[$key] = $result;
			return $result;
		} finally { self::$reading = $was_reading; }
	}

	private static function safe(string $field, string $text): string {
		return in_array($field, array('title', 'alt'), true) ? sanitize_text_field($text) : wp_kses_post($text);
	}
}

/** Native metadata seam also covers retained-ID Loop images and carousel accessible labels. */
function mcp_wpml_frontend_attachment_alt($value, $id, $key, $single) {
	if (null !== $value || '_wp_attachment_image_alt' !== $key || !MCP_WPML_Attachment_Text::frontend() || MCP_WPML_Attachment_Text::reading()) { return $value; }
	$text = MCP_WPML_Attachment_Text::get((int) $id);
	if (!isset($text['alt']) || '' === trim($text['alt'])) { return $value; }
	return $single ? $text['alt'] : array($text['alt']);
}
add_filter('get_post_metadata', 'mcp_wpml_frontend_attachment_alt', 20, 4);

/** Elementor Control_Media uses get_the_title even for custom-size image tooltips. */
function mcp_wpml_frontend_attachment_title($title, $id) {
	if (!is_string($title) || '' === trim($title) || !MCP_WPML_Attachment_Text::frontend() || MCP_WPML_Attachment_Text::reading()) { return $title; }
	$post = get_post((int) $id);
	if (!$post instanceof WP_Post || 'attachment' !== $post->post_type || $title !== (string) $post->post_title) { return $title; }
	$text = MCP_WPML_Attachment_Text::get((int) $id);
	return isset($text['title']) && '' !== trim($text['title']) ? $text['title'] : $title;
}
// Resolve the native attachment title before WordPress texturizes it; preserve explicit filtered titles.
add_filter('the_title', 'mcp_wpml_frontend_attachment_title', 5, 2);

/** Keep native Images_Manager lifecycle hooks; adapt only its textual lightbox lookup. */
function mcp_wpml_register_elementor_attachment_text(): void {
	if (!defined('ICL_SITEPRESS_VERSION') || !class_exists('Elementor\\Plugin') || !class_exists('Elementor\\Images_Manager') || !method_exists('Elementor\\Images_Manager', 'get_lightbox_image_attributes')) { return; }
	$plugin = \Elementor\Plugin::$instance;
	if (!$plugin || !isset($plugin->images_manager) || get_class($plugin->images_manager) !== 'Elementor\\Images_Manager') { return; }
	require_once __DIR__ . '/elementor-text-images-manager.php';
	$plugin->images_manager = new MCP_WPML_Elementor_Text_Images_Manager($plugin->images_manager);
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native Elementor initialization hook.
add_action('elementor/init', 'mcp_wpml_register_elementor_attachment_text', 100);

/** Preserve the native Gallery controls/renderer; supply language-specific metadata at its protected seam. */
function mcp_wpml_register_elementor_gallery_text($manager): void {
	if (!defined('ICL_SITEPRESS_VERSION') || !class_exists('ElementorPro\\Modules\\Gallery\\Widgets\\Gallery') || !method_exists('ElementorPro\\Modules\\Gallery\\Widgets\\Gallery', 'get_image_data')) { return; }
	$widget = $manager->get_widget_types('gallery');
	if (!$widget || get_class($widget) !== 'ElementorPro\\Modules\\Gallery\\Widgets\\Gallery') { return; }
	require_once __DIR__ . '/elementor-text-gallery.php';
	$manager->unregister('gallery');
	$manager->register(new MCP_WPML_Elementor_Text_Gallery());
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native Elementor widget registration hook.
add_action('elementor/widgets/register', 'mcp_wpml_register_elementor_gallery_text', 100);
