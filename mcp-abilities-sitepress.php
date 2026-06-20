<?php
/**
 * Plugin Name: MCP Abilities - SitePress
 * Plugin URI: https://devenia.com
 * Description: WPML translation mapping and translation-shell helper abilities for MCP.
 * Version: 0.3.24
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 * Text Domain: mcp-abilities-sitepress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_ready(): bool {
	return function_exists('wp_register_ability') && defined('ICL_SITEPRESS_VERSION');
}

function mcp_wpml_filesystem() {
	global $wp_filesystem;

	if (!function_exists('WP_Filesystem')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if (!WP_Filesystem()) {
		return null;
	}

	return is_object($wp_filesystem) ? $wp_filesystem : null;
}

function mcp_wpml_default_lang(): string {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$lang = apply_filters('wpml_default_language', null);
	return is_string($lang) && '' !== $lang ? $lang : 'en';
}

function mcp_wpml_target_id(int $source_id, string $target_lang): int {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$id = apply_filters('wpml_object_id', $source_id, 'page', false, $target_lang);
	return is_numeric($id) ? (int) $id : 0;
}

function mcp_wpml_target_id_for_post_type(int $source_id, string $post_type, string $target_lang): int {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$id = apply_filters('wpml_object_id', $source_id, $post_type, false, $target_lang);
	return is_numeric($id) ? (int) $id : 0;
}

function mcp_wpml_element_type_for_post_type(string $post_type): string {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$element_type = apply_filters('wpml_element_type', 'post_' . $post_type);
	return is_string($element_type) && '' !== $element_type ? $element_type : 'post_' . $post_type;
}

function mcp_wpml_lang_details(int $post_id, string $post_type = '') {
	if ('' === $post_type) {
		$post = get_post($post_id);
		$post_type = $post ? (string) $post->post_type : 'page';
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$details = apply_filters(
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		'wpml_element_language_details',
		null,
		array(
			'element_id'   => $post_id,
			'element_type' => mcp_wpml_element_type_for_post_type($post_type),
		)
	);
	return is_object($details) ? $details : null;
}

function mcp_wpml_with_language(string $language_code, callable $callback) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$previous = apply_filters('wpml_current_language', null);
	$previous = is_string($previous) ? $previous : '';

	if ('' !== $language_code) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Action provided by WPML plugin.
		do_action('wpml_switch_language', $language_code);
	}

	try {
		return $callback();
	} finally {
		if ('' !== $previous) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Action provided by WPML plugin.
			do_action('wpml_switch_language', $previous);
		}
	}
}

function mcp_wpml_post_has_term(int $post_id, int $term_id, string $taxonomy): bool {
	$terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
	if (is_wp_error($terms)) {
		return false;
	}

	return in_array($term_id, array_map('intval', $terms), true);
}

function mcp_wpml_get_active_languages(bool $skip_missing = false): array {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$languages = apply_filters(
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		'wpml_active_languages',
		null,
		array(
			'skip_missing' => $skip_missing ? 1 : 0,
			'orderby'      => 'code',
			'order'        => 'asc',
		)
	);

	return is_array($languages) ? $languages : array();
}

function mcp_wpml_shortcode_positive_int($value): int {
	if (is_int($value)) {
		return max(0, $value);
	}

	if (is_string($value) && preg_match('/^\d+$/', $value)) {
		return max(0, (int) $value);
	}

	return 0;
}

function mcp_wpml_language_flag_shortcode($atts = array()): string {
	$atts = shortcode_atts(
		array(
			'target_lang' => '',
			'width'       => '',
			'height'      => '',
			'class'       => '',
		),
		is_array($atts) ? $atts : array(),
		'mcp_wpml_language_flag'
	);

	$languages = mcp_wpml_get_active_languages(false);
	if (empty($languages)) {
		return '';
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$current = apply_filters('wpml_current_language', null);
	$current = is_string($current) ? $current : '';
	$target_code = sanitize_key((string) $atts['target_lang']);
	$target = array();

	if ('' !== $target_code && isset($languages[$target_code]) && is_array($languages[$target_code])) {
		$target = $languages[$target_code];
	}

	if (empty($target)) {
		foreach ($languages as $code => $language) {
			if (!is_array($language)) {
				continue;
			}

			$code = is_string($code) ? $code : (string) ($language['language_code'] ?? '');
			$is_current = ('' !== $current && $code === $current) || !empty($language['active']);
			if (!$is_current) {
				$target = $language;
				break;
			}
		}
	}

	if (empty($target)) {
		return '';
	}

	$url = isset($target['url']) ? (string) $target['url'] : '';
	$flag_url = isset($target['country_flag_url']) ? (string) $target['country_flag_url'] : '';
	if ('' === $url || '' === $flag_url) {
		return '';
	}

	$label = (string) ($target['native_name'] ?? $target['translated_name'] ?? $target['language_code'] ?? '');
	$label = '' !== $label ? $label : __('Change language', 'mcp-abilities-sitepress');
	$width = mcp_wpml_shortcode_positive_int($atts['width']);
	$height = mcp_wpml_shortcode_positive_int($atts['height']);
	$class = sanitize_html_class((string) $atts['class']);

	$img_attrs = array(
		'src' => esc_url($flag_url),
		'alt' => esc_attr($label),
	);
	if ($width > 0) {
		$img_attrs['width'] = (string) $width;
	}
	if ($height > 0) {
		$img_attrs['height'] = (string) $height;
	}

	$img = '<img';
	foreach ($img_attrs as $name => $value) {
		$img .= ' ' . $name . '="' . $value . '"';
	}
	$img .= '>';

	$link_class = '' !== $class ? ' class="' . esc_attr($class) . '"' : '';

	return sprintf(
		'<a%s href="%s" aria-label="%s">%s</a>',
		$link_class,
		esc_url($url),
		esc_attr($label),
		$img
	);
}

function mcp_wpml_register_frontend_shortcodes(): void {
	add_shortcode('mcp_wpml_language_flag', 'mcp_wpml_language_flag_shortcode');
}

add_action('init', 'mcp_wpml_register_frontend_shortcodes');

function mcp_wpml_language_switcher_option(): array {
	$value = get_option('wpml_language_switcher', array());
	return is_array($value) ? $value : array();
}

function mcp_wpml_normalize_scalar($value) {
	if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || null === $value) {
		return $value;
	}

	if (is_array($value)) {
		$out = array();
		foreach ($value as $key => $item) {
			$out[(string) $key] = mcp_wpml_normalize_scalar($item);
		}
		return $out;
	}

	if (is_object($value)) {
		$out = array(
			'__class' => get_class($value),
		);

		if (method_exists($value, 'get')) {
			$out['__has_get'] = true;
		}

		foreach (get_object_vars($value) as $key => $item) {
			$out[(string) $key] = mcp_wpml_normalize_scalar($item);
		}

		return $out;
	}

	return (string) $value;
}

function mcp_wpml_find_element_by_id(array $elements, string $element_id): ?array {
	foreach ($elements as $element) {
		if (!is_array($element)) {
			continue;
		}
		if (($element['id'] ?? '') === $element_id) {
			return $element;
		}
		if (!empty($element['elements']) && is_array($element['elements'])) {
			$found = mcp_wpml_find_element_by_id($element['elements'], $element_id);
			if (null !== $found) {
				return $found;
			}
		}
	}

	return null;
}

function mcp_wpml_attachment_size_file_exists(int $attachment_id, string $size): array {
	$meta = wp_get_attachment_metadata($attachment_id);
	if (!is_array($meta)) {
		return array(
			'has_metadata' => false,
			'has_size'     => false,
			'exists'       => false,
			'path'         => '',
			'url'          => '',
		);
	}

	$uploads = wp_get_upload_dir();
	$file = isset($meta['file']) ? (string) $meta['file'] : '';
	$dir = '' !== $file ? dirname($file) : '';
	$size_file = isset($meta['sizes'][$size]['file']) ? (string) $meta['sizes'][$size]['file'] : '';
	$path = '' !== $size_file ? trailingslashit((string) $uploads['basedir']) . trailingslashit($dir) . $size_file : '';
	$url = '' !== $size_file ? trailingslashit((string) $uploads['baseurl']) . trailingslashit($dir) . $size_file : '';

	return array(
		'has_metadata' => true,
		'has_size'     => '' !== $size_file,
		'exists'       => '' !== $path && file_exists($path),
		'path'         => $path,
		'url'          => $url,
	);
}

function mcp_wpml_collect_gallery_widgets(array $elements, array &$galleries, array $post_info): void {
	foreach ($elements as $element) {
		if (!is_array($element)) {
			continue;
		}

		$settings = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : array();
		$widget_type = isset($element['widgetType']) ? (string) $element['widgetType'] : '';
		$attachment_ids = array();
		$keys = array('gallery', 'wp_gallery', 'carousel', 'slides');

		foreach ($keys as $key) {
			if (empty($settings[$key]) || !is_array($settings[$key])) {
				continue;
			}
			foreach ($settings[$key] as $item) {
				if (!is_array($item)) {
					continue;
				}
				$candidates = array(
					$item['id'] ?? 0,
					$item['attachment_id'] ?? 0,
					$item['image']['id'] ?? 0,
					$item['background_image']['id'] ?? 0,
				);
				foreach ($candidates as $candidate) {
					$id = is_numeric($candidate) ? (int) $candidate : 0;
					if ($id > 0) {
						$attachment_ids[] = $id;
					}
				}
			}
		}

		$attachment_ids = array_values(array_unique($attachment_ids));
		if (!empty($attachment_ids) && (str_contains($widget_type, 'gallery') || str_contains($widget_type, 'carousel') || !empty($settings['gallery']) || !empty($settings['wp_gallery']))) {
			$galleries[] = array_merge(
				$post_info,
				array(
					'element_id'     => isset($element['id']) ? (string) $element['id'] : '',
					'widget_type'    => $widget_type,
					'attachment_ids' => $attachment_ids,
					'count'          => count($attachment_ids),
				)
			);
		}

		if (!empty($element['elements']) && is_array($element['elements'])) {
			mcp_wpml_collect_gallery_widgets($element['elements'], $galleries, $post_info);
		}
	}
}

function mcp_wpml_gallery_widgets_for_post(int $post_id, string $post_type = ''): array {
	$post = get_post($post_id);
	if (!$post) {
		return array();
	}
	$raw = get_post_meta($post_id, '_elementor_data', true);
	$data = is_string($raw) && '' !== $raw ? json_decode($raw, true) : array();
	if (!is_array($data)) {
		return array();
	}
	$details = mcp_wpml_lang_details($post_id, '' !== $post_type ? $post_type : (string) $post->post_type);
	$lang = $details && isset($details->language_code) ? (string) $details->language_code : '';
	$galleries = array();
	mcp_wpml_collect_gallery_widgets(
		$data,
		$galleries,
		array(
			'post_id'   => $post_id,
			'post_type' => (string) $post->post_type,
			'lang'      => $lang,
			'title'     => get_the_title($post_id),
			'link'      => get_permalink($post_id),
		)
	);
	return $galleries;
}

function mcp_wpml_gallery_attachment_caption_issues(array $attachment_ids, string $target_lang = 'en'): array {
	$issues = array();
	foreach (array_values(array_unique(array_map('intval', $attachment_ids))) as $attachment_id) {
		if ($attachment_id <= 0) {
			continue;
		}
		$details = mcp_wpml_lang_details($attachment_id, 'attachment');
		$lang = $details && isset($details->language_code) ? (string) $details->language_code : '';
		if ($target_lang !== $lang) {
			continue;
		}
		$title = (string) get_post_field('post_title', $attachment_id, 'raw');
		$caption = (string) get_post_field('post_excerpt', $attachment_id, 'raw');
		$description = (string) get_post_field('post_content', $attachment_id, 'raw');
		if (mcp_wpml_text_has_norwegian_markers($title . "\n" . $caption . "\n" . $description)) {
			$issues[] = array(
				'id'          => $attachment_id,
				'title'       => $title,
				'caption'     => $caption,
				'description' => $description,
			);
		}
	}
	return $issues;
}

function mcp_wpml_text_has_norwegian_markers(string $text): bool {
	$haystack = mb_strtolower(wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), 'UTF-8');
	$haystack = str_replace('garderobemekka', '', $haystack);
	if ('' === trim($haystack)) {
		return false;
	}

	$markers = array(
		'skyvedør', 'skyvedører', 'garderobeløsning', 'garderobeløsningen', 'garderobeinnredning',
		'innredning', 'entré', 'soverom', 'svarte', 'sorte', 'sølv', 'sotet', 'klart speil', 'speil',
		'sprosser', 'på mål', 'etter mål', 'måltilpasset', 'skreddersydd', 'oppbevaring',
		'hvitt', 'sort', 'svart', 'treverk', 'åpen', 'lukket', 'med benk', 'praktisk',
		'hjørnegarderobe', 'vaskerom', 'rundt dørene', 'brons efarget', 'bronsefarget',
	);
	foreach ($markers as $marker) {
		if (preg_match('/(^|[^\p{L}])' . preg_quote($marker, '/') . '($|[^\p{L}])/u', $haystack)) {
			return true;
		}
	}

	return false;
}

function mcp_wpml_describe_slot($slot): array {
	$type = gettype($slot);
	$class = is_object($slot) ? get_class($slot) : '';
	$data = mcp_wpml_normalize_scalar($slot);
	$is_empty = false;
	$has_get = false;
	$suspicious = false;
	$reason = '';

	if (is_array($slot)) {
		$is_empty = empty($slot);
		$suspicious = !$is_empty;
		$reason = $suspicious ? 'Array slot payload is risky for WPML language switcher state.' : '';
	} elseif (is_object($slot)) {
		$vars = get_object_vars($slot);
		$is_empty = empty($vars);
		$has_get = method_exists($slot, 'get');
		$suspicious = !$is_empty && !$has_get;
		$reason = $suspicious ? 'Non-empty object slot payload without get() method is risky for WPML language switcher state.' : '';
	} else {
		$is_empty = empty($slot);
		$suspicious = !$is_empty;
		$reason = $suspicious ? 'Unexpected non-object slot payload type.' : '';
	}

	return array(
		'type'       => $type,
		'class'      => $class,
		'is_empty'   => $is_empty,
		'has_get'    => $has_get,
		'suspicious' => $suspicious,
		'reason'     => $reason,
		'data'       => $data,
	);
}

function mcp_wpml_collect_language_switcher_slots(array $settings): array {
	$slots = array();

	$statics = isset($settings['statics']) && is_array($settings['statics']) ? $settings['statics'] : array();
	foreach ($statics as $slot_name => $slot_value) {
		$slots[] = array_merge(
			array(
				'group' => 'statics',
				'name'  => (string) $slot_name,
			),
			mcp_wpml_describe_slot($slot_value)
		);
	}

	foreach (array('menus', 'sidebars') as $group) {
		$group_value = $settings[$group] ?? array();
		if (!is_array($group_value)) {
			$slots[] = array(
				'group'      => $group,
				'name'       => '__invalid_group__',
				'type'       => gettype($group_value),
				'class'      => is_object($group_value) ? get_class($group_value) : '',
				'is_empty'   => empty($group_value),
				'has_get'    => false,
				'suspicious' => true,
				'reason'     => 'Expected array group for language switcher settings.',
				'data'       => mcp_wpml_normalize_scalar($group_value),
			);
			continue;
		}

		foreach ($group_value as $slot_name => $slot_value) {
			$slots[] = array_merge(
				array(
					'group' => $group,
					'name'  => (string) $slot_name,
				),
				mcp_wpml_describe_slot($slot_value)
			);
		}
	}

	return $slots;
}

function mcp_wpml_language_switcher_overview(array $settings): array {
	$slots = mcp_wpml_collect_language_switcher_slots($settings);
	$suspicious = array_values(array_filter($slots, static function (array $slot): bool {
		return !empty($slot['suspicious']);
	}));

	return array(
		'languages_order' => isset($settings['languages_order']) && is_array($settings['languages_order']) ? array_values($settings['languages_order']) : array(),
		'menu_slot_count' => isset($settings['menus']) && is_array($settings['menus']) ? count($settings['menus']) : 0,
		'sidebar_slot_count' => isset($settings['sidebars']) && is_array($settings['sidebars']) ? count($settings['sidebars']) : 0,
		'static_slot_count' => isset($settings['statics']) && is_array($settings['statics']) ? count($settings['statics']) : 0,
		'slot_count' => count($slots),
		'suspicious_slot_count' => count($suspicious),
		'suspicious_slots' => array_map(
			static function (array $slot): array {
				return array(
					'group'  => $slot['group'],
					'name'   => $slot['name'],
					'type'   => $slot['type'],
					'class'  => $slot['class'],
					'reason' => $slot['reason'],
				);
			},
			$suspicious
		),
	);
}

function mcp_wpml_copy_elementor_meta(int $source_id, int $target_id): void {
	$keys = array(
		'_elementor_data',
		'_elementor_edit_mode',
		'_elementor_template_type',
		'_elementor_page_settings',
		'_elementor_version',
	);
	foreach ($keys as $key) {
		if (metadata_exists('post', $source_id, $key)) {
			$value = get_post_meta($source_id, $key, true);
			if (is_string($value)) {
				// Preserve escaped JSON payloads used by Elementor meta fields.
				$value = wp_slash($value);
			}
			update_post_meta($target_id, $key, $value);
		}
	}
	delete_post_meta($target_id, '_elementor_css');
}

function mcp_wpml_copy_selected_post_meta(int $source_id, int $target_id, array $keys): array {
	$copied = array();
	foreach ($keys as $key) {
		$key = (string) $key;
		if ('' === $key || !metadata_exists('post', $source_id, $key)) {
			continue;
		}

		$value = get_post_meta($source_id, $key, true);
		if (is_string($value)) {
			$value = wp_slash($value);
		}
		update_post_meta($target_id, $key, $value);
		$copied[] = $key;
	}

	return $copied;
}

function mcp_wpml_translate_term_id(int $term_id, string $taxonomy, string $target_lang): int {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$translated = apply_filters('wpml_object_id', $term_id, $taxonomy, false, $target_lang);
	return is_numeric($translated) && (int) $translated > 0 ? (int) $translated : $term_id;
}

function mcp_wpml_copy_object_terms(int $source_id, int $target_id, string $post_type, string $target_lang): array {
	$copied = array();
	$taxonomies = get_object_taxonomies($post_type, 'objects');
	if (!is_array($taxonomies)) {
		return $copied;
	}

	foreach ($taxonomies as $taxonomy => $object) {
		if (!$object || empty($object->show_ui)) {
			continue;
		}

		$term_ids = wp_get_object_terms($source_id, (string) $taxonomy, array('fields' => 'ids'));
		if (is_wp_error($term_ids) || empty($term_ids)) {
			continue;
		}

		$target_term_ids = array_values(array_unique(array_map(
			static function ($term_id) use ($taxonomy, $target_lang): int {
				return mcp_wpml_translate_term_id((int) $term_id, (string) $taxonomy, $target_lang);
			},
			$term_ids
		)));

		$result = wp_set_object_terms($target_id, $target_term_ids, (string) $taxonomy, false);
		if (!is_wp_error($result)) {
			$copied[(string) $taxonomy] = array_map('intval', $target_term_ids);
		}
	}

	return $copied;
}

function mcp_wpml_internal_url_to_post_id(string $url): int {
	$url = trim(html_entity_decode($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	if ('' === $url || str_starts_with($url, '#') || preg_match('/^(?:mailto|tel|sms|javascript):/i', $url)) {
		return 0;
	}

	$home = wp_parse_url(home_url('/'));
	$host = isset($home['host']) ? strtolower((string) $home['host']) : '';
	$absolute = $url;

	if (str_starts_with($url, '//')) {
		$scheme = isset($home['scheme']) ? (string) $home['scheme'] : 'https';
		$absolute = $scheme . ':' . $url;
	} elseif (str_starts_with($url, '/')) {
		if (str_starts_with($url, '/wp-content/') || str_starts_with($url, '/wp-admin/') || str_starts_with($url, '/wp-includes/')) {
			return 0;
		}
		$absolute = home_url($url);
	} elseif (!preg_match('/^https?:\/\//i', $url)) {
		return 0;
	}

	$parts = wp_parse_url($absolute);
	if (!$parts || empty($parts['host']) || strtolower((string) $parts['host']) !== $host) {
		return 0;
	}
	if (!empty($parts['path']) && preg_match('/\/wp-content\/|\/wp-admin\/|\/wp-includes\//', (string) $parts['path'])) {
		return 0;
	}

	$clean = (isset($parts['scheme']) ? $parts['scheme'] : 'https') . '://' . $parts['host'] . (isset($parts['path']) ? $parts['path'] : '/');
	return (int) url_to_postid($clean);
}

function mcp_wpml_internal_page_like_url(string $url): array {
	$url = trim(html_entity_decode($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	if ('' === $url || str_starts_with($url, '#') || preg_match('/^(?:mailto|tel|sms|javascript):/i', $url)) {
		return array('internal' => false, 'page_like' => false, 'path' => '');
	}

	$home = wp_parse_url(home_url('/'));
	$host = isset($home['host']) ? strtolower((string) $home['host']) : '';
	$absolute = $url;

	if (str_starts_with($url, '//')) {
		$scheme = isset($home['scheme']) ? (string) $home['scheme'] : 'https';
		$absolute = $scheme . ':' . $url;
	} elseif (str_starts_with($url, '/')) {
		$absolute = home_url($url);
	} elseif (!preg_match('/^https?:\/\//i', $url)) {
		return array('internal' => false, 'page_like' => false, 'path' => '');
	}

	$parts = wp_parse_url($absolute);
	if (!$parts || empty($parts['host']) || strtolower((string) $parts['host']) !== $host) {
		return array('internal' => false, 'page_like' => false, 'path' => '');
	}

	$path = isset($parts['path']) ? (string) $parts['path'] : '/';
	$path = '/' . ltrim($path, '/');
	if ('/' === $path || preg_match('~^/(?:wp-content|wp-admin|wp-includes|wp-json|xmlrpc\.php)(?:/|$)~', $path)) {
		return array('internal' => true, 'page_like' => false, 'path' => $path);
	}

	$basename = basename(rtrim($path, '/'));
	if (str_contains($basename, '.') || preg_match('~\.(?:jpg|jpeg|png|gif|webp|avif|svg|css|js|json|xml|pdf|zip|woff2?|ttf|eot|ico)$~i', $basename)) {
		return array('internal' => true, 'page_like' => false, 'path' => $path);
	}

	return array('internal' => true, 'page_like' => true, 'path' => $path);
}

function mcp_wpml_url_looks_target_language(string $url, string $target_lang): bool {
	if ('' === $target_lang) {
		return false;
	}

	$path = '';
	if (str_starts_with($url, '/')) {
		$path = (string) (wp_parse_url($url, PHP_URL_PATH) ?: '');
	} elseif (preg_match('/^https?:\/\//i', $url)) {
		$parts = wp_parse_url($url);
		$path = isset($parts['path']) ? (string) $parts['path'] : '';
	}

	$prefix = '/' . trim($target_lang, '/') . '/';
	return '/' . trim($target_lang, '/') === rtrim($path, '/') || str_starts_with($path, $prefix);
}

function mcp_wpml_extract_urls_from_string(string $value): array {
	$decoded = html_entity_decode(str_replace('\\/', '/', $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$ok = preg_match_all('~(?:https?:)?//[^\s"\'<>)\]]+|(?:(?<=["\'\s(=])|^)/[A-Za-z0-9][^\s"\'<>)\]]+~', $decoded, $matches);
	if (false === $ok || empty($matches[0])) {
		return array();
	}

	$urls = array();
	foreach ($matches[0] as $url) {
		$url = rtrim((string) $url, '.,;:');
		if ('' !== $url) {
			$urls[$url] = true;
		}
	}
	return array_keys($urls);
}

function mcp_wpml_collect_urls_recursive($node, array &$urls, int $depth = 0): void {
	if ($depth > 24) {
		return;
	}
	if (is_string($node)) {
		foreach (mcp_wpml_extract_urls_from_string($node) as $url) {
			$urls[$url] = true;
		}
		return;
	}
	if (!is_array($node) && !is_object($node)) {
		return;
	}

	$iterable = is_object($node) ? get_object_vars($node) : $node;
	foreach ($iterable as $value) {
		mcp_wpml_collect_urls_recursive($value, $urls, $depth + 1);
	}
}

function mcp_wpml_replacement_url_like_original(string $original_url, string $target_url): string {
	$target_parts = wp_parse_url($target_url);
	$original_parts = wp_parse_url(str_starts_with($original_url, '/') ? home_url($original_url) : $original_url);
	if (!$target_parts) {
		return $target_url;
	}

	$out = $target_url;
	if (!empty($original_parts['query'])) {
		$out .= (str_contains($out, '?') ? '&' : '?') . $original_parts['query'];
	}
	if (!empty($original_parts['fragment'])) {
		$out .= '#' . $original_parts['fragment'];
	}

	if (str_starts_with($original_url, '/')) {
		return wp_make_link_relative($out);
	}
	return $out;
}

function mcp_wpml_replace_url_variants(string $haystack, string $from, string $to): array {
	$count = 0;
	$variants = array(
		$from => $to,
		str_replace('/', '\\/', $from) => str_replace('/', '\\/', $to),
		esc_url($from) => esc_url($to),
		esc_url_raw($from) => esc_url_raw($to),
	);

	foreach ($variants as $needle => $replacement) {
		if ('' === $needle || !str_contains($haystack, $needle)) {
			continue;
		}
		$n = substr_count($haystack, $needle);
		$haystack = str_replace($needle, $replacement, $haystack);
		$count += $n;
	}

	return array($haystack, $count);
}

function mcp_wpml_status_filter(string $status) {
	return 'any' === $status ? array('publish', 'draft', 'pending', 'private') : $status;
}

function mcp_wpml_default_ignore_terms(): array {
	return array(
		'http',
		'https',
		'www',
		'com',
		'org',
		'html',
		'elementor',
		'widget',
		'class',
		'style',
		'true',
		'false',
		'elementor',
		'elements',
		'settings',
		'widgettype',
		'eltype',
		'editor',
		'container',
		'section',
		'column',
		'desktop',
		'tablet',
		'mobile',
		'padding',
		'margin',
		'typography',
		'rem',
		'px',
	);
}

function mcp_wpml_elementor_excluded_keys(): array {
	return array(
		'_id' => true,
		'id' => true,
		'eltype' => true,
		'widgettype' => true,
		'isinner' => true,
		'url' => true,
		'link' => true,
		'href' => true,
		'src' => true,
		'size' => true,
		'unit' => true,
		'css_classes' => true,
		'html_tag' => true,
		'animation' => true,
		'icon' => true,
		'selected_icon' => true,
		'background_background' => true,
		'background_color' => true,
		'text_color' => true,
		'global_colors' => true,
		'custom_css' => true,
		'margin' => true,
		'padding' => true,
		'gap' => true,
		'align' => true,
		'content_width' => true,
		'display_conditions' => true,
	);
}

function mcp_wpml_string_seems_human_text($value): bool {
	if (!is_string($value) && !is_numeric($value)) {
		return false;
	}
	$value = (string) $value;
	$value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$value = trim(preg_replace('/\s+/u', ' ', $value));
	if ('' === $value) {
		return false;
	}

	$len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	if ($len < 2 || $len > 600) {
		return false;
	}
	if (preg_match('/https?:\/\//i', $value)) {
		return false;
	}
	if (preg_match('/\.(?:jpg|jpeg|png|gif|svg|webp|pdf)(?:\?.*)?$/i', $value)) {
		return false;
	}
	if (!preg_match('/\p{L}/u', $value)) {
		return false;
	}
	if (preg_match('/^[a-z0-9_\-\.\/:#]+$/i', $value) && !preg_match('/\s/u', $value)) {
		return false;
	}
	if (preg_match('/^[\[\]\{\}\(\),;:_\-#0-9\.\/\\\\]+$/u', $value)) {
		return false;
	}

	return true;
}

function mcp_wpml_collect_elementor_text_values($node, array &$parts, int $depth = 0): void {
	if ($depth > 24) {
		return;
	}
	if (!is_array($node) && !is_object($node)) {
		return;
	}

	$excluded = mcp_wpml_elementor_excluded_keys();
	$iterable = is_object($node) ? get_object_vars($node) : $node;

	foreach ($iterable as $key => $value) {
		if (is_array($value) || is_object($value)) {
			mcp_wpml_collect_elementor_text_values($value, $parts, $depth + 1);
			continue;
		}
		if (!is_string($value)) {
			continue;
		}

		$key_l = is_string($key) ? (function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key)) : '';
		if ('' !== $key_l && isset($excluded[$key_l])) {
			continue;
		}
		if (!mcp_wpml_string_seems_human_text($value)) {
			continue;
		}

		$parts[] = trim(wp_strip_all_tags($value));
	}
}

function mcp_wpml_collect_text_for_detection(int $page_id, bool $include_elementor): string {
	$post = get_post($page_id);
	if (!$post) {
		return '';
	}

	$parts = array(
		(string) $post->post_title,
		(string) $post->post_excerpt,
		wp_strip_all_tags((string) $post->post_content),
	);

	if ($include_elementor) {
		$elementor_raw = get_post_meta($page_id, '_elementor_data', true);
		if (is_string($elementor_raw) && '' !== trim($elementor_raw)) {
			$decoded = json_decode($elementor_raw, true);
			if (is_array($decoded)) {
				mcp_wpml_collect_elementor_text_values($decoded, $parts);
			}
		}
	}

	$text = implode("\n", $parts);
	return html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcp_wpml_count_term_hits(string $text, string $term): int {
	$text = (string) $text;
	$term = (string) $term;
	if ('' === trim($term)) {
		return 0;
	}
	$pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/ui';
	$count = preg_match_all($pattern, $text);
	return false === $count ? 0 : (int) $count;
}

function mcp_wpml_text_tokens(string $text, int $min_len = 5): array {
	$text = (string) $text;
	$min_len = max(2, $min_len);
	$pattern = '/[\p{L}\p{N}][\p{L}\p{N}\-_]{' . ($min_len - 1) . ',}/u';
	$ok = preg_match_all($pattern, $text, $m);
	if (false === $ok || empty($m[0])) {
		return array();
	}

	$tokens = array();
	foreach ($m[0] as $raw) {
		$token = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
		$tokens[$token] = ($tokens[$token] ?? 0) + 1;
	}
	return $tokens;
}

function mcp_wpml_shared_term_hits(string $source_text, string $target_text, array $ignore_terms, int $min_len, int $min_source_count, int $min_target_count, int $max_terms): array {
	$source_text = (string) $source_text;
	$target_text = (string) $target_text;
	$source_tokens = mcp_wpml_text_tokens($source_text, $min_len);
	if (empty($source_tokens)) {
		return array();
	}

	$target_text_l = function_exists('mb_strtolower') ? mb_strtolower($target_text, 'UTF-8') : strtolower($target_text);
	$ignore = array();
	foreach ($ignore_terms as $t) {
		$t = trim((string) $t);
		if ('' !== $t) {
			$ignore[function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t)] = true;
		}
	}

	$hits = array();
	foreach ($source_tokens as $token => $source_count) {
		$token = (string) $token;
		if ($source_count < $min_source_count || isset($ignore[$token]) || preg_match('/\d/', $token)) {
			continue;
		}
		$target_count = mcp_wpml_count_term_hits($target_text_l, $token);
		if ($target_count >= max(1, $min_target_count)) {
			$hits[] = array(
				'term'         => $token,
				'source_count' => (int) $source_count,
				'target_count' => (int) $target_count,
			);
		}
	}

	usort($hits, function (array $a, array $b): int {
		return ($b['target_count'] <=> $a['target_count']) ?: ($b['source_count'] <=> $a['source_count']);
	});

	return array_slice($hits, 0, max(1, $max_terms));
}

function mcp_wpml_exact_segment_hits(string $source_text, string $target_text, int $min_chars, int $max_hits): array {
	$source_text = (string) $source_text;
	$target_text = (string) $target_text;
	$min_chars = max(20, $min_chars);
	$segments = preg_split('/(?:[\r\n]+|(?<=[\.\!\?])\s+)/u', $source_text);
	if (!is_array($segments)) {
		return array();
	}

	$target_l = function_exists('mb_strtolower') ? mb_strtolower($target_text, 'UTF-8') : strtolower($target_text);
	$hits = array();
	$seen = array();

	foreach ($segments as $seg) {
		$seg = trim(preg_replace('/\s+/u', ' ', (string) $seg));
		$seg_len = function_exists('mb_strlen') ? mb_strlen($seg, 'UTF-8') : strlen($seg);
		if ($seg_len < $min_chars) {
			continue;
		}
		$key = function_exists('mb_strtolower') ? mb_strtolower($seg, 'UTF-8') : strtolower($seg);
		if (isset($seen[$key])) {
			continue;
		}
		$seen[$key] = true;
		$pos = function_exists('mb_stripos') ? mb_stripos($target_l, $key, 0, 'UTF-8') : stripos($target_l, $key);
		if (false !== $pos) {
			$hits[] = $seg;
			if (count($hits) >= max(1, $max_hits)) {
				break;
			}
		}
	}

	return $hits;
}

function mcp_wpml_configured_option_translation($value, string $option_name) {
	if (!is_string($value) || '' === $option_name) {
		return $value;
	}
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$lang = apply_filters('wpml_current_language', null);
	$lang = is_string($lang) ? $lang : '';
	if ('' === $lang || $lang === mcp_wpml_default_lang()) {
		return $value;
	}
	$translations = get_option('mcp_wpml_option_translations', array());
	if (is_array($translations) && isset($translations[$option_name]) && is_array($translations[$option_name])) {
		$translated = $translations[$option_name][$lang] ?? '';
		if (is_string($translated) && '' !== $translated) {
			return $translated;
		}
	}
	return $value;
}

add_filter('option_blogdescription', static function ($value) {
	return mcp_wpml_configured_option_translation($value, 'blogdescription');
});

function mcp_wpml_path_segments(string $url): array {
	$path = (string) (wp_parse_url($url, PHP_URL_PATH) ?: '');
	$parts = array_filter(explode('/', trim($path, '/')), static function (string $part): bool {
		return '' !== $part;
	});
	return array_values($parts);
}

function mcp_wpml_target_permalink_issues(int $source_id, int $target_id, string $target_lang): array {
	$issues = array();
	$source = get_post($source_id);
	$target = get_post($target_id);
	if (!$source || !$target) {
		return $issues;
	}

	$target_url = (string) get_permalink($target_id);
	$target_path = '/' . trim((string) (wp_parse_url($target_url, PHP_URL_PATH) ?: ''), '/') . '/';
	if ('' !== $target_lang && $target_lang !== mcp_wpml_default_lang() && !str_starts_with($target_path, '/' . trim($target_lang, '/') . '/')) {
		$issues[] = array(
			'reason' => 'target_url_missing_language_prefix',
			'url'    => $target_url,
		);
	}

	if ('en' === $target_lang && str_contains($target_path, '/uncategorized-no/')) {
		$issues[] = array(
			'reason' => 'source_language_uncategorized_segment_in_target_url',
			'url'    => $target_url,
			'segment'=> 'uncategorized-no',
		);
	}

	$source_segments = array();
	foreach (mcp_wpml_path_segments((string) get_permalink($source_id)) as $segment) {
		$source_segments[$segment] = true;
	}
	if ('' !== (string) $source->post_name) {
		$source_segments[(string) $source->post_name] = true;
	}
	if ('post' === (string) $source->post_type) {
		$terms = wp_get_post_categories($source_id, array('fields' => 'all'));
		if (!is_wp_error($terms)) {
			foreach ($terms as $term) {
				if (!empty($term->slug)) {
					$source_segments[(string) $term->slug] = true;
				}
			}
		}
	}

	$target_segments = array_fill_keys(mcp_wpml_path_segments($target_url), true);
	foreach (array_keys($source_segments) as $segment) {
		$segment = trim((string) $segment);
		if ('' === $segment || strlen($segment) < 4 || $segment === $target_lang || is_numeric($segment)) {
			continue;
		}
		if (isset($target_segments[$segment])) {
			$issues[] = array(
				'reason'  => 'source_slug_segment_in_target_url',
				'url'     => $target_url,
				'segment' => $segment,
			);
		}
	}

	return $issues;
}

function mcp_wpml_register_abilities(): void {
	if (!mcp_wpml_ready()) {
		return;
	}

	$list_active_languages = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$skip_missing = !empty($input['skip_missing']);
		$languages = mcp_wpml_get_active_languages($skip_missing);
		$rows = array();

		foreach ($languages as $code => $language) {
			if (!is_array($language)) {
				continue;
			}

			$rows[] = array(
				'code'                => (string) ($language['code'] ?? $code),
				'native_name'         => (string) ($language['native_name'] ?? ''),
				'translated_name'     => (string) ($language['translated_name'] ?? ''),
				'default_locale'      => (string) ($language['default_locale'] ?? ''),
				'language_code'       => (string) ($language['language_code'] ?? ''),
				'default'             => !empty($language['default_locale']) && ((string) ($language['code'] ?? $code) === mcp_wpml_default_lang()),
				'active'              => !empty($language['active']),
				'missing'             => !empty($language['missing']),
				'country_flag_url'    => (string) ($language['country_flag_url'] ?? ''),
				'url'                 => (string) ($language['url'] ?? ''),
			);
		}

		return array(
			'success'      => true,
			'skip_missing' => $skip_missing,
			'default_lang' => mcp_wpml_default_lang(),
			'languages'    => $rows,
			'total'        => count($rows),
		);
	};

	wp_register_ability(
		'wpml/list-active-languages',
		array(
			'label' => 'List Active Languages',
			'description' => 'Lists active WPML languages with normalized metadata for safe automation.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'properties' => array(
					'skip_missing' => array('type' => 'boolean', 'default' => false),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'skip_missing' => array('type' => 'boolean'),
					'default_lang' => array('type' => 'string'),
					'languages' => array('type' => 'array'),
					'total' => array('type' => 'integer'),
				),
			),
			'execute_callback' => $list_active_languages,
			'permission_callback' => function (): bool {
				return current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => true,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$get_element_language_details = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$id = isset($input['id']) ? (int) $input['id'] : 0;

		if ($id <= 0) {
			return array('success' => false, 'message' => 'id is required.');
		}

		$post = get_post($id);
		if (!$post) {
			return array('success' => false, 'message' => 'Element not found.');
		}

		$element_type = mcp_wpml_element_type_for_post_type((string) $post->post_type);
		$details = mcp_wpml_lang_details($id, (string) $post->post_type);
		if (!$details) {
			return array('success' => false, 'message' => 'Could not resolve WPML language details.');
		}

		return array(
			'success' => true,
			'id' => $id,
			'post_type' => (string) $post->post_type,
			'element_type' => $element_type,
			'post_status' => (string) $post->post_status,
			'title' => (string) get_the_title($id),
			'trid' => isset($details->trid) ? (int) $details->trid : 0,
			'language_code' => (string) ($details->language_code ?? ''),
			'source_language_code' => (string) ($details->source_language_code ?? ''),
		);
	};

	wp_register_ability(
		'wpml/get-element-language-details',
		array(
			'label' => 'Get Element Language Details',
			'description' => 'Returns normalized WPML language details for a page/post element.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'required' => array('id'),
				'properties' => array(
					'id' => array('type' => 'integer'),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'id' => array('type' => 'integer'),
					'post_type' => array('type' => 'string'),
					'element_type' => array('type' => 'string'),
					'post_status' => array('type' => 'string'),
					'title' => array('type' => 'string'),
					'trid' => array('type' => 'integer'),
					'language_code' => array('type' => 'string'),
					'source_language_code' => array('type' => 'string'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $get_element_language_details,
			'permission_callback' => function (): bool {
				return current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => true,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$link_post_translation = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$source_id = isset($input['source_id']) ? (int) $input['source_id'] : 0;
		$target_id = isset($input['target_id']) ? (int) $input['target_id'] : 0;
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : '';

		if ($source_id <= 0 || $target_id <= 0 || '' === $target_lang) {
			return array('success' => false, 'message' => 'source_id, target_id, and target_lang are required.');
		}

		$source = get_post($source_id);
		$target = get_post($target_id);
		if (!$source || !$target) {
			return array('success' => false, 'message' => 'Source or target post not found.');
		}
		if ((string) $source->post_type !== (string) $target->post_type) {
			return array(
				'success' => false,
				'message' => 'Source and target post types must match.',
				'source_post_type' => (string) $source->post_type,
				'target_post_type' => (string) $target->post_type,
			);
		}

		$post_type = (string) $source->post_type;
		$element_type = mcp_wpml_element_type_for_post_type($post_type);
		$source_details = mcp_wpml_lang_details($source_id, $post_type);
		if (!$source_details || empty($source_details->trid) || empty($source_details->language_code)) {
			return array('success' => false, 'message' => 'Could not read source WPML language details.');
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		do_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
			'wpml_set_element_language_details',
			array(
				'element_id'           => $target_id,
				'element_type'         => $element_type,
				'trid'                 => (int) $source_details->trid,
				'language_code'        => $target_lang,
				'source_language_code' => (string) $source_details->language_code,
				'check_duplicates'     => false,
			)
		);

		clean_post_cache($source_id);
		clean_post_cache($target_id);
		$target_details = mcp_wpml_lang_details($target_id, $post_type);

		return array(
			'success' => true,
			'source_id' => $source_id,
			'target_id' => $target_id,
			'post_type' => $post_type,
			'element_type' => $element_type,
			'trid' => (int) $source_details->trid,
			'source_lang' => (string) $source_details->language_code,
			'target_lang' => $target_lang,
			'target_details' => $target_details ? mcp_wpml_normalize_scalar($target_details) : array(),
			'message' => 'Post linked as WPML translation.',
		);
	};

	wp_register_ability(
		'wpml/link-post-translation',
		array(
			'label' => 'Link Post Translation',
			'description' => 'Links an existing target post/CPT item to an existing source post/CPT item as a WPML translation. Supports Elementor library templates and other translated post types.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'required' => array('source_id', 'target_id', 'target_lang'),
				'properties' => array(
					'source_id' => array('type' => 'integer'),
					'target_id' => array('type' => 'integer'),
					'target_lang' => array('type' => 'string'),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'source_id' => array('type' => 'integer'),
					'target_id' => array('type' => 'integer'),
					'post_type' => array('type' => 'string'),
					'element_type' => array('type' => 'string'),
					'trid' => array('type' => 'integer'),
					'source_lang' => array('type' => 'string'),
					'target_lang' => array('type' => 'string'),
					'target_details' => array('type' => 'object'),
					'message' => array('type' => 'string'),
					'source_post_type' => array('type' => 'string'),
					'target_post_type' => array('type' => 'string'),
				),
			),
			'execute_callback' => $link_post_translation,
			'permission_callback' => function (): bool {
				return current_user_can('manage_options');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => false,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$audit_translated_links = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$id = isset($input['id']) ? (int) $input['id'] : 0;
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : '';
		$include_content = !array_key_exists('include_content', $input) || (bool) $input['include_content'];
		$include_elementor = !array_key_exists('include_elementor', $input) || (bool) $input['include_elementor'];
		$fix = !empty($input['fix']);

		if ($id <= 0) {
			return array('success' => false, 'message' => 'id is required.');
		}

		$post = get_post($id);
		if (!$post) {
			return array('success' => false, 'message' => 'Post not found.');
		}

		$details = mcp_wpml_lang_details($id, (string) $post->post_type);
		if ('' === $target_lang && $details && !empty($details->language_code)) {
			$target_lang = (string) $details->language_code;
		}
		if ('' === $target_lang) {
			return array('success' => false, 'message' => 'target_lang is required when the post language cannot be resolved.');
		}

		$sources = array();
		if ($include_content) {
			$sources['post_content'] = (string) $post->post_content;
		}

		$elementor_raw = '';
		if ($include_elementor) {
			$elementor_raw = get_post_meta($id, '_elementor_data', true);
			if (is_string($elementor_raw) && '' !== trim($elementor_raw)) {
				$elementor_urls = array();
				$decoded = json_decode($elementor_raw, true);
				if (is_array($decoded)) {
					mcp_wpml_collect_urls_recursive($decoded, $elementor_urls);
				} else {
					foreach (mcp_wpml_extract_urls_from_string($elementor_raw) as $url) {
						$elementor_urls[$url] = true;
					}
				}
				$sources['_elementor_data'] = array_keys($elementor_urls);
			}
		}

		$issues_by_url = array();
		foreach ($sources as $source => $payload) {
			$urls = is_array($payload) ? $payload : mcp_wpml_extract_urls_from_string((string) $payload);
			foreach ($urls as $url) {
				if (mcp_wpml_url_looks_target_language((string) $url, $target_lang)) {
					continue;
				}
				$linked_id = mcp_wpml_internal_url_to_post_id((string) $url);
				if ($linked_id <= 0) {
					$url_info = mcp_wpml_internal_page_like_url((string) $url);
					if (!empty($url_info['internal']) && !empty($url_info['page_like'])) {
						$key = $source . '|' . $url . '|unresolved';
						$issues_by_url[$key] = array(
							'source' => $source,
							'url' => (string) $url,
							'reason' => 'unresolved_internal_url',
							'path' => (string) ($url_info['path'] ?? ''),
							'target_lang' => $target_lang,
							'replacement_url' => '',
							'message' => 'Internal page-like URL is not under the target language prefix and could not be resolved to a post. Check whether it should link to a translated page.',
						);
					}
					continue;
				}
				$linked = get_post($linked_id);
				if (!$linked) {
					continue;
				}
				$linked_details = mcp_wpml_lang_details($linked_id, (string) $linked->post_type);
				$linked_lang = $linked_details ? (string) ($linked_details->language_code ?? '') : '';
				if ($linked_lang === $target_lang) {
					continue;
				}

				$translated_id = mcp_wpml_target_id_for_post_type($linked_id, (string) $linked->post_type, $target_lang);
				if ($translated_id <= 0 || $translated_id === $linked_id) {
					continue;
				}
				$translated_url = get_permalink($translated_id);
				if (!is_string($translated_url) || '' === $translated_url) {
					continue;
				}

				$replacement_url = mcp_wpml_replacement_url_like_original((string) $url, $translated_url);
				if ($replacement_url === (string) $url) {
					continue;
				}
				$key = $source . '|' . $url . '|' . $replacement_url;
				$issues_by_url[$key] = array(
					'source' => $source,
					'url' => (string) $url,
					'reason' => 'source_language_original',
					'linked_id' => $linked_id,
					'linked_post_type' => (string) $linked->post_type,
					'linked_lang' => $linked_lang,
					'translated_id' => $translated_id,
					'target_lang' => $target_lang,
					'replacement_url' => $replacement_url,
				);
			}
		}

		$issues = array_values($issues_by_url);
		$content_replacements = 0;
		$elementor_replacements = 0;

		if ($fix && !empty($issues)) {
			$new_content = (string) $post->post_content;
			$new_elementor_raw = is_string($elementor_raw) ? $elementor_raw : '';

			foreach ($issues as $issue) {
				if (empty($issue['replacement_url'])) {
					continue;
				}
				if ('post_content' === $issue['source']) {
					list($new_content, $n) = mcp_wpml_replace_url_variants($new_content, $issue['url'], $issue['replacement_url']);
					$content_replacements += $n;
				} elseif ('_elementor_data' === $issue['source'] && '' !== $new_elementor_raw) {
					list($new_elementor_raw, $n) = mcp_wpml_replace_url_variants($new_elementor_raw, $issue['url'], $issue['replacement_url']);
					$elementor_replacements += $n;
				}
			}

			if ($content_replacements > 0 && $new_content !== (string) $post->post_content) {
				$result = wp_update_post(
					array(
						'ID' => $id,
						'post_content' => $new_content,
					),
					true
				);
				if (is_wp_error($result)) {
					return array('success' => false, 'message' => $result->get_error_message());
				}
			}
			if ($elementor_replacements > 0 && '' !== $new_elementor_raw) {
				update_post_meta($id, '_elementor_data', wp_slash($new_elementor_raw));
				delete_post_meta($id, '_elementor_css');
			}
			if ($content_replacements > 0 || $elementor_replacements > 0) {
				clean_post_cache($id);
			}
		}

		return array(
			'success' => true,
			'id' => $id,
			'post_type' => (string) $post->post_type,
			'title' => (string) get_the_title($id),
			'post_lang' => $details ? (string) ($details->language_code ?? '') : '',
			'target_lang' => $target_lang,
			'fix' => $fix,
			'issue_count' => count($issues),
			'issues' => $issues,
			'content_replacements' => $content_replacements,
			'elementor_replacements' => $elementor_replacements,
			'message' => empty($issues)
				? 'No internal links to source-language originals found.'
				: ($fix ? 'Internal links audited and replacements applied where possible.' : 'Internal links to source-language originals found. Run with fix=true to replace them.'),
		);
	};

	wp_register_ability(
		'wpml/audit-translated-links',
		array(
			'label' => 'Audit Translated Links',
			'description' => 'Audits a translated post/page/template for internal links that point to source-language originals when a target-language translation exists. Optionally replaces them with translated URLs.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'required' => array('id'),
				'properties' => array(
					'id' => array('type' => 'integer'),
					'target_lang' => array('type' => 'string', 'description' => 'Target language code. Defaults to the post language.'),
					'include_content' => array('type' => 'boolean', 'default' => true),
					'include_elementor' => array('type' => 'boolean', 'default' => true),
					'fix' => array('type' => 'boolean', 'default' => false),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'id' => array('type' => 'integer'),
					'post_type' => array('type' => 'string'),
					'title' => array('type' => 'string'),
					'post_lang' => array('type' => 'string'),
					'target_lang' => array('type' => 'string'),
					'fix' => array('type' => 'boolean'),
					'issue_count' => array('type' => 'integer'),
					'issues' => array('type' => 'array'),
					'content_replacements' => array('type' => 'integer'),
					'elementor_replacements' => array('type' => 'integer'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $audit_translated_links,
			'permission_callback' => function (): bool {
				return current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => false,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$audit_translated_links_batch = function ($input = array()) use ($audit_translated_links): array {
		$input = is_array($input) ? $input : array();
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : '';
		$post_type = isset($input['post_type']) ? sanitize_key((string) $input['post_type']) : 'page';
		$status = isset($input['status']) ? (string) $input['status'] : 'publish';
		$limit = isset($input['limit']) ? max(1, min(500, (int) $input['limit'])) : 100;
		$fix = !empty($input['fix']);
		$include_content = !array_key_exists('include_content', $input) || (bool) $input['include_content'];
		$include_elementor = !array_key_exists('include_elementor', $input) || (bool) $input['include_elementor'];

		$ids = array();
		if (!empty($input['ids']) && is_array($input['ids'])) {
			foreach ($input['ids'] as $id) {
				$id = (int) $id;
				if ($id > 0) {
					$ids[$id] = true;
				}
			}
		} else {
			$query = new WP_Query(
				array(
					'post_type' => $post_type,
					'post_status' => $status,
					'posts_per_page' => $limit,
					'fields' => 'ids',
					'orderby' => 'ID',
					'order' => 'ASC',
					'no_found_rows' => true,
				)
			);

			foreach ($query->posts as $id) {
				$id = (int) $id;
				if ($id <= 0) {
					continue;
				}
				if ('' !== $target_lang) {
					$post = get_post($id);
					$details = $post ? mcp_wpml_lang_details($id, (string) $post->post_type) : null;
					if (!$details || (string) ($details->language_code ?? '') !== $target_lang) {
						continue;
					}
				}
				$ids[$id] = true;
			}
		}

		$results = array();
		$total_issues = 0;
		$total_content_replacements = 0;
		$total_elementor_replacements = 0;

		foreach (array_keys($ids) as $id) {
			$result = $audit_translated_links(
				array(
					'id' => (int) $id,
					'target_lang' => $target_lang,
					'include_content' => $include_content,
					'include_elementor' => $include_elementor,
					'fix' => $fix,
				)
			);

			$results[] = $result;
			$total_issues += isset($result['issue_count']) ? (int) $result['issue_count'] : 0;
			$total_content_replacements += isset($result['content_replacements']) ? (int) $result['content_replacements'] : 0;
			$total_elementor_replacements += isset($result['elementor_replacements']) ? (int) $result['elementor_replacements'] : 0;
		}

		return array(
			'success' => true,
			'target_lang' => $target_lang,
			'post_type' => empty($input['ids']) ? $post_type : '',
			'status' => empty($input['ids']) ? $status : '',
			'fix' => $fix,
			'scanned_count' => count($results),
			'total_issue_count' => $total_issues,
			'total_content_replacements' => $total_content_replacements,
			'total_elementor_replacements' => $total_elementor_replacements,
			'results' => $results,
			'message' => $total_issues > 0
				? ($fix ? 'Batch translated-link audit completed and replacements were applied where possible.' : 'Batch translated-link audit found issues.')
				: 'Batch translated-link audit found no issues.',
		);
	};

	wp_register_ability(
		'wpml/audit-translated-links-batch',
		array(
			'label' => 'Audit Translated Links Batch',
			'description' => 'Runs translated-link audit across explicit IDs or all posts of a post type in a target language. Optionally applies replacements with fix=true.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'properties' => array(
					'ids' => array(
						'type' => 'array',
						'items' => array('type' => 'integer'),
						'description' => 'Explicit post/template IDs to scan. When set, post_type/status/limit are ignored.',
					),
					'target_lang' => array('type' => 'string', 'description' => 'Target language code. Used to filter queried posts and resolve replacement URLs.'),
					'post_type' => array('type' => 'string', 'default' => 'page'),
					'status' => array('type' => 'string', 'default' => 'publish'),
					'limit' => array('type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 500),
					'include_content' => array('type' => 'boolean', 'default' => true),
					'include_elementor' => array('type' => 'boolean', 'default' => true),
					'fix' => array('type' => 'boolean', 'default' => false),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'target_lang' => array('type' => 'string'),
					'post_type' => array('type' => 'string'),
					'status' => array('type' => 'string'),
					'fix' => array('type' => 'boolean'),
					'scanned_count' => array('type' => 'integer'),
					'total_issue_count' => array('type' => 'integer'),
					'total_content_replacements' => array('type' => 'integer'),
					'total_elementor_replacements' => array('type' => 'integer'),
					'results' => array('type' => 'array'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $audit_translated_links_batch,
			'permission_callback' => function (): bool {
				return current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => false,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$audit_translation_coverage = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$source_lang = isset($input['source_lang']) ? sanitize_key((string) $input['source_lang']) : mcp_wpml_default_lang();
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : '';
		$status = isset($input['status']) ? (string) $input['status'] : 'publish';
		$limit = isset($input['limit']) ? max(1, min(2000, (int) $input['limit'])) : 500;
		$include_stale = !array_key_exists('include_stale', $input) || (bool) $input['include_stale'];

		if ('' === $target_lang) {
			foreach (mcp_wpml_get_active_languages(false) as $language) {
				$code = isset($language['code']) ? sanitize_key((string) $language['code']) : '';
				if ('' !== $code && $code !== $source_lang) {
					$target_lang = $code;
					break;
				}
			}
		}
		if ('' === $source_lang || '' === $target_lang) {
			return array('success' => false, 'message' => 'source_lang and target_lang are required.');
		}

		$post_types = array('page', 'post', 'elementor_library');
		if (!empty($input['post_types']) && is_array($input['post_types'])) {
			$post_types = array();
			foreach ($input['post_types'] as $post_type) {
				$post_type = sanitize_key((string) $post_type);
				if ('' !== $post_type) {
					$post_types[] = $post_type;
				}
			}
			$post_types = array_values(array_unique($post_types));
		}
		if (empty($post_types)) {
			return array('success' => false, 'message' => 'At least one post type is required.');
		}

		$ids = array();
		if (!empty($input['ids']) && is_array($input['ids'])) {
			foreach ($input['ids'] as $id) {
				$id = (int) $id;
				if ($id > 0) {
					$ids[$id] = true;
				}
			}
		} else {
			$query = new WP_Query(
				array(
					'post_type' => $post_types,
					'post_status' => mcp_wpml_status_filter($status),
					'posts_per_page' => $limit,
					'fields' => 'ids',
					'orderby' => 'modified',
					'order' => 'DESC',
					'no_found_rows' => true,
				)
			);
			foreach ($query->posts as $id) {
				$id = (int) $id;
				if ($id > 0) {
					$ids[$id] = true;
				}
			}
		}

		$items = array();
		$scanned = 0;
		$source_count = 0;
		$missing_count = 0;
		$stale_count = 0;

		foreach (array_keys($ids) as $id) {
			$post = get_post((int) $id);
			if (!$post || !in_array((string) $post->post_type, $post_types, true)) {
				continue;
			}
			$scanned++;

			$details = mcp_wpml_lang_details((int) $id, (string) $post->post_type);
			$lang = $details ? (string) ($details->language_code ?? '') : '';
			if ($lang !== $source_lang) {
				continue;
			}
			$source_count++;

			$target_id = mcp_wpml_target_id_for_post_type((int) $id, (string) $post->post_type, $target_lang);
			$target = $target_id > 0 ? get_post($target_id) : null;
			if (!$target || 'trash' === (string) $target->post_status) {
				$missing_count++;
				$items[] = array(
					'reason' => 'missing_translation',
					'id' => (int) $id,
					'post_type' => (string) $post->post_type,
					'title' => (string) get_the_title((int) $id),
					'status' => (string) $post->post_status,
					'source_lang' => $source_lang,
					'target_lang' => $target_lang,
					'source_modified' => (string) $post->post_modified,
					'source_url' => (string) get_permalink((int) $id),
					'target_id' => 0,
					'target_url' => '',
				);
				continue;
			}

			if ($include_stale && strtotime((string) $target->post_modified_gmt) < strtotime((string) $post->post_modified_gmt)) {
				$stale_count++;
				$items[] = array(
					'reason' => 'stale_translation',
					'id' => (int) $id,
					'post_type' => (string) $post->post_type,
					'title' => (string) get_the_title((int) $id),
					'status' => (string) $post->post_status,
					'source_lang' => $source_lang,
					'target_lang' => $target_lang,
					'source_modified' => (string) $post->post_modified,
					'source_url' => (string) get_permalink((int) $id),
					'target_id' => (int) $target_id,
					'target_status' => (string) $target->post_status,
					'target_modified' => (string) $target->post_modified,
					'target_url' => (string) get_permalink((int) $target_id),
				);
			}
		}

		return array(
			'success' => true,
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
			'post_types' => $post_types,
			'status' => $status,
			'limit' => $limit,
			'scanned_count' => $scanned,
			'source_count' => $source_count,
			'missing_count' => $missing_count,
			'stale_count' => $stale_count,
			'issue_count' => count($items),
			'items' => $items,
			'message' => empty($items) ? 'Translation coverage audit found no issues.' : 'Translation coverage audit found missing or stale translations.',
		);
	};

	wp_register_ability(
		'wpml/audit-translation-coverage',
		array(
			'label' => 'Audit Translation Coverage',
			'description' => 'Scans source-language posts/pages/templates and reports missing or stale target-language translations.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'properties' => array(
					'source_lang' => array('type' => 'string', 'description' => 'Source language code. Defaults to the WPML default language.'),
					'target_lang' => array('type' => 'string', 'description' => 'Target language code. Defaults to the first non-source active language.'),
					'post_types' => array(
						'type' => 'array',
						'items' => array('type' => 'string'),
						'default' => array('page', 'post', 'elementor_library'),
					),
					'status' => array('type' => 'string', 'default' => 'publish'),
					'limit' => array('type' => 'integer', 'default' => 500, 'minimum' => 1, 'maximum' => 2000),
					'include_stale' => array('type' => 'boolean', 'default' => true),
					'ids' => array('type' => 'array', 'items' => array('type' => 'integer'), 'description' => 'Optional explicit source IDs to scan.'),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'source_lang' => array('type' => 'string'),
					'target_lang' => array('type' => 'string'),
					'post_types' => array('type' => 'array'),
					'status' => array('type' => 'string'),
					'limit' => array('type' => 'integer'),
					'scanned_count' => array('type' => 'integer'),
					'source_count' => array('type' => 'integer'),
					'missing_count' => array('type' => 'integer'),
					'stale_count' => array('type' => 'integer'),
					'issue_count' => array('type' => 'integer'),
					'items' => array('type' => 'array'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $audit_translation_coverage,
			'permission_callback' => function (): bool {
				return current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => true,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$get_language_switcher_settings = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$include_raw = !empty($input['include_raw']);
		$settings = mcp_wpml_language_switcher_option();
		$overview = mcp_wpml_language_switcher_overview($settings);

		$response = array(
			'success' => true,
			'overview' => $overview,
			'settings' => array(
				'migrated' => isset($settings['migrated']) ? (int) $settings['migrated'] : 0,
				'converted_menu_ids' => isset($settings['converted_menu_ids']) ? (int) $settings['converted_menu_ids'] : 0,
				'languages_order' => $overview['languages_order'],
				'link_empty' => isset($settings['link_empty']) ? (int) $settings['link_empty'] : 0,
				'additional_css' => (string) ($settings['additional_css'] ?? ''),
				'copy_parameters' => (string) ($settings['copy_parameters'] ?? ''),
			),
		);

		if ($include_raw) {
			$response['raw'] = mcp_wpml_normalize_scalar($settings);
		}

		return $response;
	};

	wp_register_ability(
		'wpml/get-language-switcher-settings',
		array(
			'label' => 'Get Language Switcher Settings',
			'description' => 'Returns normalized WPML language switcher settings with overview and optional raw normalized payload.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'properties' => array(
					'include_raw' => array('type' => 'boolean', 'default' => false),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'overview' => array('type' => 'object'),
					'settings' => array('type' => 'object'),
					'raw' => array('type' => 'object'),
				),
			),
			'execute_callback' => $get_language_switcher_settings,
			'permission_callback' => function (): bool {
				return current_user_can('manage_options');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => true,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$list_language_switcher_slots = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$only_suspicious = !empty($input['only_suspicious']);
		$slots = mcp_wpml_collect_language_switcher_slots(mcp_wpml_language_switcher_option());

		if ($only_suspicious) {
			$slots = array_values(array_filter($slots, static function (array $slot): bool {
				return !empty($slot['suspicious']);
			}));
		}

		return array(
			'success' => true,
			'only_suspicious' => $only_suspicious,
			'slots' => $slots,
			'total' => count($slots),
		);
	};

	wp_register_ability(
		'wpml/list-language-switcher-slots',
		array(
			'label' => 'List Language Switcher Slots',
			'description' => 'Lists WPML language-switcher slots across statics, menus, and sidebars with type and risk metadata.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'properties' => array(
					'only_suspicious' => array('type' => 'boolean', 'default' => false),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'only_suspicious' => array('type' => 'boolean'),
					'slots' => array('type' => 'array'),
					'total' => array('type' => 'integer'),
				),
			),
			'execute_callback' => $list_language_switcher_slots,
			'permission_callback' => function (): bool {
				return current_user_can('manage_options');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => true,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$validate_language_switcher_settings = function ($input = array()): array {
		$settings = mcp_wpml_language_switcher_option();
		$overview = mcp_wpml_language_switcher_overview($settings);
		$is_valid = 0 === (int) $overview['suspicious_slot_count'];

		return array(
			'success' => true,
			'valid' => $is_valid,
			'overview' => $overview,
			'message' => $is_valid
				? 'No suspicious WPML language switcher slots detected.'
				: 'Suspicious WPML language switcher slots detected. Review before modifying switcher state.',
		);
	};

	wp_register_ability(
		'wpml/validate-language-switcher-settings',
		array(
			'label' => 'Validate Language Switcher Settings',
			'description' => 'Validates WPML language-switcher option structure and flags suspicious slot payloads before writes/recovery.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'valid' => array('type' => 'boolean'),
					'overview' => array('type' => 'object'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $validate_language_switcher_settings,
			'permission_callback' => function (): bool {
				return current_user_can('manage_options');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => true,
					'destructive' => false,
					'idempotent' => true,
				),
			),
		)
	);

	$reset_language_switcher_settings = function ($input = array()): array {
		$deleted = delete_option('wpml_language_switcher');
		wp_cache_delete('wpml_language_switcher', 'options');

		return array(
			'success' => true,
			'deleted' => (bool) $deleted,
			'message' => $deleted
				? 'WPML language switcher settings deleted. WPML can rebuild them on next access.'
				: 'WPML language switcher settings were already absent or unchanged.',
		);
	};

	wp_register_ability(
		'wpml/reset-language-switcher-settings',
		array(
			'label' => 'Reset Language Switcher Settings',
			'description' => 'Deletes the WPML language switcher option so WPML can rebuild it from defaults/internal state.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'deleted' => array('type' => 'boolean'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $reset_language_switcher_settings,
			'permission_callback' => function (): bool {
				return current_user_can('manage_options');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => false,
					'destructive' => true,
					'idempotent' => true,
				),
			),
		)
	);

	$rebuild_language_switcher_settings = function ($input = array()): array {
		delete_option('wpml_language_switcher');
		wp_cache_delete('wpml_language_switcher', 'options');
		$settings = mcp_wpml_language_switcher_option();
		$overview = mcp_wpml_language_switcher_overview($settings);

		return array(
			'success' => true,
			'overview' => $overview,
			'settings' => mcp_wpml_normalize_scalar($settings),
			'message' => 'WPML language switcher settings reset and re-read.',
		);
	};

	wp_register_ability(
		'wpml/rebuild-language-switcher-settings',
		array(
			'label' => 'Rebuild Language Switcher Settings',
			'description' => 'Deletes and re-reads the WPML language switcher option to force a safe rebuild path.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'overview' => array('type' => 'object'),
					'settings' => array('type' => 'object'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $rebuild_language_switcher_settings,
			'permission_callback' => function (): bool {
				return current_user_can('manage_options');
			},
			'meta' => array(
				'annotations' => array(
					'readonly' => false,
					'destructive' => true,
					'idempotent' => true,
				),
			),
		)
	);

		$list_page_translation_status = function ($input = array()): array {
			$input = is_array($input) ? $input : array();

			$source_lang = isset($input['source_lang']) && '' !== (string) $input['source_lang'] ? sanitize_key((string) $input['source_lang']) : mcp_wpml_default_lang();
			$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : 'en';
			$status      = isset($input['status']) ? sanitize_key((string) $input['status']) : 'publish';
			$per_page    = isset($input['per_page']) ? max(1, min(200, (int) $input['per_page'])) : 50;
			$page        = isset($input['page']) ? max(1, (int) $input['page']) : 1;
			$allowed_orderby = array('modified', 'date', 'title', 'menu_order', 'ID');
			$orderby     = isset($input['orderby']) && in_array((string) $input['orderby'], $allowed_orderby, true) ? (string) $input['orderby'] : 'modified';
			$order_raw   = isset($input['order']) ? strtoupper((string) $input['order']) : 'DESC';
			$order       = in_array($order_raw, array('ASC', 'DESC'), true) ? $order_raw : 'DESC';

		$q = new WP_Query(
			array(
				'post_type'           => 'page',
				'post_status'         => mcp_wpml_status_filter($status),
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'orderby'             => $orderby,
				'order'               => $order,
				'lang'                => $source_lang,
				'suppress_filters'    => false,
				'ignore_sticky_posts' => true,
			)
		);

		$rows = array();
		foreach ($q->posts as $post) {
			$source_id = (int) $post->ID;
			$target_id = mcp_wpml_target_id($source_id, $target_lang);
			$rows[] = array(
				'source_id'       => $source_id,
				'source_title'    => (string) get_the_title($source_id),
				'source_status'   => (string) get_post_status($source_id),
				'source_link'     => (string) get_permalink($source_id),
				'target_lang'     => $target_lang,
				'target_id'       => $target_id,
				'target_title'    => $target_id > 0 ? (string) get_the_title($target_id) : '',
				'target_status'   => $target_id > 0 ? (string) get_post_status($target_id) : '',
				'target_link'     => $target_id > 0 ? (string) get_permalink($target_id) : '',
				'has_translation' => $target_id > 0,
			);
		}

		return array(
			'success'     => true,
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
			'pages'       => $rows,
			'total'       => (int) $q->found_posts,
			'total_pages' => (int) $q->max_num_pages,
		);
	};

	wp_register_ability(
		'wpml/list-page-translation-status',
		array(
			'label'       => 'List Page Translation Status',
			'description' => 'List source-language pages and target-language translation status.',
			'category'    => 'site',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
					'source_lang' => array('type' => 'string'),
					'target_lang' => array('type' => 'string', 'default' => 'en'),
					'status'      => array('type' => 'string', 'enum' => array('publish', 'draft', 'pending', 'private', 'any'), 'default' => 'publish'),
					'per_page'    => array('type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200),
					'page'        => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
					'orderby'     => array('type' => 'string', 'enum' => array('modified', 'date', 'title', 'menu_order', 'ID'), 'default' => 'modified'),
					'order'       => array('type' => 'string', 'enum' => array('ASC', 'DESC'), 'default' => 'DESC'),
					),
					'additionalProperties' => false,
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array('type' => 'boolean'),
						'source_lang' => array('type' => 'string'),
						'target_lang' => array('type' => 'string'),
						'pages'       => array('type' => 'array'),
						'total'       => array('type' => 'integer'),
						'total_pages' => array('type' => 'integer'),
					),
				),
				'execute_callback' => $list_page_translation_status,
				'permission_callback' => function (): bool {
					return current_user_can('edit_pages');
				},
				'meta' => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

	$ensure_post_translation = function ($input = array()): array {
		$input = is_array($input) ? $input : array();

		if (empty($input['source_id'])) {
			return array('success' => false, 'message' => 'source_id is required.');
		}

		$source_id           = (int) $input['source_id'];
		$target_lang         = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : 'en';
		$target_status       = isset($input['target_status']) ? sanitize_key((string) $input['target_status']) : 'draft';
		$copy_content        = !array_key_exists('copy_content', $input) || (bool) $input['copy_content'];
		$copy_excerpt        = !array_key_exists('copy_excerpt', $input) || (bool) $input['copy_excerpt'];
		$copy_elementor      = !array_key_exists('copy_elementor', $input) || (bool) $input['copy_elementor'];
		$copy_featured_image = !array_key_exists('copy_featured_image', $input) || (bool) $input['copy_featured_image'];
		$copy_taxonomies     = !array_key_exists('copy_taxonomies', $input) || (bool) $input['copy_taxonomies'];
		$copy_selected_meta  = !array_key_exists('copy_selected_meta', $input) || (bool) $input['copy_selected_meta'];

		if ('' === $target_lang) {
			return array('success' => false, 'message' => 'target_lang is required.');
		}
		if (!in_array($target_status, array('draft', 'publish', 'pending', 'private'), true)) {
			return array('success' => false, 'message' => 'Invalid target_status.');
		}

		$source = get_post($source_id);
		if (!$source) {
			return array('success' => false, 'message' => 'Source post not found.');
		}

		$post_type = (string) $source->post_type;
		if (in_array($post_type, array('revision', 'nav_menu_item', 'attachment'), true)) {
			return array('success' => false, 'message' => 'Source post type is not supported.', 'post_type' => $post_type);
		}
		if (!post_type_exists($post_type)) {
			return array('success' => false, 'message' => 'Source post type does not exist.', 'post_type' => $post_type);
		}

		$details = mcp_wpml_lang_details($source_id, $post_type);
		if (!$details || empty($details->trid) || empty($details->language_code)) {
			return array('success' => false, 'message' => 'Could not read source WPML language details.');
		}

		$target_id = mcp_wpml_target_id_for_post_type($source_id, $post_type, $target_lang);
		if ($target_id > 0 && $target_id !== $source_id) {
			return array(
				'success'       => true,
				'created'       => false,
				'source_id'     => $source_id,
				'target_id'     => $target_id,
				'post_type'     => $post_type,
				'target_lang'   => $target_lang,
				'target_status' => (string) get_post_status($target_id),
				'target_link'   => (string) get_permalink($target_id),
				'message'       => 'Translation already exists.',
			);
		}

		$post_type_object = get_post_type_object($post_type);
		$parent_target = 0;
		if ($post_type_object && !empty($post_type_object->hierarchical) && (int) $source->post_parent > 0) {
			$parent_target = mcp_wpml_target_id_for_post_type((int) $source->post_parent, $post_type, $target_lang);
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => $target_status,
				'post_title'   => (string) $source->post_title,
				'post_content' => $copy_content ? (string) $source->post_content : '',
				'post_excerpt' => $copy_excerpt ? (string) $source->post_excerpt : '',
				'post_parent'  => $parent_target,
				'menu_order'   => (int) $source->menu_order,
			),
			true
		);
		if (is_wp_error($new_id)) {
			return array('success' => false, 'message' => $new_id->get_error_message());
		}
		$new_id = (int) $new_id;

		$element_type = mcp_wpml_element_type_for_post_type($post_type);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		do_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
			'wpml_set_element_language_details',
			array(
				'element_id'           => $new_id,
				'element_type'         => $element_type,
				'trid'                 => (int) $details->trid,
				'language_code'        => $target_lang,
				'source_language_code' => (string) $details->language_code,
				'check_duplicates'     => false,
			)
		);

		$copied_meta = array();
		if ($copy_elementor) {
			mcp_wpml_copy_elementor_meta($source_id, $new_id);
			$copied_meta[] = '_elementor_data';
		}
		if ($copy_selected_meta) {
			$copied_meta = array_values(array_unique(array_merge(
				$copied_meta,
				mcp_wpml_copy_selected_post_meta($source_id, $new_id, array('_wp_page_template'))
			)));
		}

		$featured_image_id = 0;
		if ($copy_featured_image) {
			$featured_image_id = (int) get_post_thumbnail_id($source_id);
			if ($featured_image_id > 0) {
				set_post_thumbnail($new_id, $featured_image_id);
			}
		}

		$copied_taxonomies = array();
		if ($copy_taxonomies) {
			$copied_taxonomies = mcp_wpml_copy_object_terms($source_id, $new_id, $post_type, $target_lang);
		}

		clean_post_cache($source_id);
		clean_post_cache($new_id);

		return array(
			'success'           => true,
			'created'           => true,
			'source_id'         => $source_id,
			'target_id'         => $new_id,
			'post_type'         => $post_type,
			'element_type'      => $element_type,
			'source_lang'       => (string) $details->language_code,
			'target_lang'       => $target_lang,
			'target_status'     => (string) get_post_status($new_id),
			'target_link'       => (string) get_permalink($new_id),
			'featured_image_id' => $featured_image_id,
			'copied_meta'       => $copied_meta,
			'copied_taxonomies' => $copied_taxonomies,
			'message'           => 'Translation created and linked.',
		);
	};

	wp_register_ability(
		'wpml/ensure-post-translation',
		array(
			'label'       => 'Ensure Post Translation',
			'description' => 'Create and link a target-language WPML translation shell for a source post, page, or custom post type.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array('source_id'),
				'properties' => array(
					'source_id'           => array('type' => 'integer'),
					'target_lang'         => array('type' => 'string', 'default' => 'en'),
					'target_status'       => array('type' => 'string', 'enum' => array('draft', 'publish', 'pending', 'private'), 'default' => 'draft'),
					'copy_content'        => array('type' => 'boolean', 'default' => true),
					'copy_excerpt'        => array('type' => 'boolean', 'default' => true),
					'copy_elementor'      => array('type' => 'boolean', 'default' => true),
					'copy_featured_image' => array('type' => 'boolean', 'default' => true),
					'copy_taxonomies'     => array('type' => 'boolean', 'default' => true),
					'copy_selected_meta'  => array('type' => 'boolean', 'default' => true),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success'           => array('type' => 'boolean'),
					'created'           => array('type' => 'boolean'),
					'source_id'         => array('type' => 'integer'),
					'target_id'         => array('type' => 'integer'),
					'post_type'         => array('type' => 'string'),
					'element_type'      => array('type' => 'string'),
					'source_lang'       => array('type' => 'string'),
					'target_lang'       => array('type' => 'string'),
					'target_status'     => array('type' => 'string'),
					'target_link'       => array('type' => 'string'),
					'featured_image_id' => array('type' => 'integer'),
					'copied_meta'       => array('type' => 'array'),
					'copied_taxonomies' => array('type' => 'object'),
					'message'           => array('type' => 'string'),
				),
			),
			'execute_callback' => $ensure_post_translation,
			'permission_callback' => function (): bool {
				return current_user_can('edit_posts') || current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	$update_translated_post_url = function ($input = array()): array {
		$input = is_array($input) ? $input : array();

		if (empty($input['id'])) {
			return array('success' => false, 'message' => 'id is required.');
		}

		$post_id     = (int) $input['id'];
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : '';
		$slug        = isset($input['slug']) ? sanitize_title((string) $input['slug']) : '';
		$category_ids = isset($input['category_ids']) && is_array($input['category_ids'])
			? array_values(array_filter(array_map('intval', $input['category_ids'])))
			: array();
		$primary_category_id = isset($input['primary_category_id']) ? (int) $input['primary_category_id'] : 0;
		$permalink_manager_uri = isset($input['permalink_manager_uri'])
			? trim(sanitize_text_field((string) $input['permalink_manager_uri']), " \t\n\r\0\x0B/")
			: '';

		if ('' === $slug && empty($category_ids) && $primary_category_id <= 0) {
			return array('success' => false, 'message' => 'Provide slug, category_ids, or primary_category_id.');
		}

		$post = get_post($post_id);
		if (!$post) {
			return array('success' => false, 'message' => 'Post not found.');
		}

		$post_type = (string) $post->post_type;
		$details = mcp_wpml_lang_details($post_id, $post_type);
		if (!$details || empty($details->language_code)) {
			return array('success' => false, 'message' => 'Could not read WPML language details.');
		}

		if ('' === $target_lang) {
			$target_lang = (string) $details->language_code;
		}
		if ((string) $details->language_code !== $target_lang) {
			return array(
				'success'       => false,
				'id'            => $post_id,
				'post_type'     => $post_type,
				'language_code' => (string) $details->language_code,
				'target_lang'   => $target_lang,
				'message'       => 'Post language does not match target_lang.',
			);
		}

		$result = mcp_wpml_with_language($target_lang, static function () use ($post_id, $post_type, $slug, $category_ids, $primary_category_id, $permalink_manager_uri): array {
			global $wpdb;

			$before = get_post($post_id);
			$before_link = (string) get_permalink($post_id);
			$before_slug = $before ? (string) $before->post_name : '';

			$updates = array('ID' => $post_id);
			if ('' !== $slug) {
				$updates['post_name'] = $slug;
			}

			if (count($updates) > 1) {
				$updated = wp_update_post(wp_slash($updates), true);
				if (is_wp_error($updated)) {
					return array('success' => false, 'message' => $updated->get_error_message());
				}
			}

			if ('' !== $slug) {
				$after_update = get_post($post_id);
				if ($after_update && (string) $after_update->post_name !== $slug) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fallback for preserving an explicit translated slug when core filters override wp_update_post().
					$wpdb->update(
						$wpdb->posts,
						array('post_name' => $slug),
						array('ID' => $post_id),
						array('%s'),
						array('%d')
					);
				}
				delete_post_meta($post_id, '_wp_old_slug');
			}

			if (!empty($category_ids) && 'post' === $post_type) {
				wp_set_post_categories($post_id, $category_ids, false);
			}

			if ($primary_category_id > 0 && 'post' === $post_type) {
				if (!mcp_wpml_post_has_term($post_id, $primary_category_id, 'category')) {
					wp_set_object_terms($post_id, array($primary_category_id), 'category', true);
				}
				update_post_meta($post_id, '_yoast_wpseo_primary_category', (string) $primary_category_id);
				update_post_meta($post_id, 'rank_math_primary_category', (string) $primary_category_id);
			}

			$pm_uri = $permalink_manager_uri;
			if ('' === $pm_uri && '' !== $slug && 'post' === $post_type) {
				$category = null;
				if ($primary_category_id > 0) {
					$category = get_term($primary_category_id, 'category');
				}
				if ((!$category || is_wp_error($category)) && !empty($category_ids)) {
					$category = get_term((int) $category_ids[0], 'category');
				}
				if ($category && !is_wp_error($category) && !empty($category->slug)) {
					$pm_uri = trim((string) $category->slug, '/') . '/' . $slug;
				}
			}
			if ('' !== $pm_uri) {
				$uris = get_option('permalink-manager-uris', array());
				if (is_array($uris)) {
					$uris[(string) $post_id] = $pm_uri;
					update_option('permalink-manager-uris', $uris, false);
				}
			}

			clean_post_cache($post_id);
			if (function_exists('wp_cache_flush')) {
				wp_cache_flush();
			}
			flush_rewrite_rules(false);

			$after = get_post($post_id);
			$terms = 'post' === $post_type ? wp_get_post_categories($post_id, array('fields' => 'all')) : array();
			$categories = array();
			if (!is_wp_error($terms)) {
				foreach ($terms as $term) {
					$categories[] = array(
						'id'   => (int) $term->term_id,
						'name' => (string) $term->name,
						'slug' => (string) $term->slug,
					);
				}
			}

			return array(
				'success'             => true,
				'id'                  => $post_id,
				'before_slug'         => $before_slug,
				'after_slug'          => $after ? (string) $after->post_name : '',
				'before_link'         => $before_link,
				'after_link'          => (string) get_permalink($post_id),
				'categories'          => $categories,
				'primary_category_id' => $primary_category_id,
				'permalink_manager_uri' => $pm_uri,
				'message'             => 'Translated post URL settings updated.',
			);
		});

		if (is_array($result)) {
			$result['language_code'] = (string) $details->language_code;
			$result['target_lang'] = $target_lang;
		}

		return is_array($result) ? $result : array('success' => false, 'message' => 'Unexpected update result.');
	};

	wp_register_ability(
		'wpml/update-translated-post-url',
		array(
			'label'       => 'Update Translated Post URL',
			'description' => 'Update a translated post slug, categories, and primary category in the post language context so WPML/Yoast permalinks do not keep source-language URL parts.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array('id'),
				'properties' => array(
					'id'                  => array('type' => 'integer'),
					'target_lang'         => array('type' => 'string', 'default' => 'en'),
					'slug'                => array('type' => 'string'),
					'category_ids'        => array(
						'type'  => 'array',
						'items' => array('type' => 'integer'),
					),
					'primary_category_id' => array('type' => 'integer'),
					'permalink_manager_uri' => array('type' => 'string'),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success'             => array('type' => 'boolean'),
					'id'                  => array('type' => 'integer'),
					'language_code'       => array('type' => 'string'),
					'target_lang'         => array('type' => 'string'),
					'before_slug'         => array('type' => 'string'),
					'after_slug'          => array('type' => 'string'),
					'before_link'         => array('type' => 'string'),
					'after_link'          => array('type' => 'string'),
					'categories'          => array('type' => 'array'),
					'primary_category_id' => array('type' => 'integer'),
					'permalink_manager_uri' => array('type' => 'string'),
					'message'             => array('type' => 'string'),
				),
			),
			'execute_callback' => $update_translated_post_url,
			'permission_callback' => function (): bool {
				return current_user_can('edit_posts');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	$audit_elementor_language_assets = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : 'en';
		$fix = !empty($input['fix']);
		$ids = isset($input['ids']) && is_array($input['ids']) ? array_values(array_filter(array_map('intval', $input['ids']))) : array();
		$post_types = isset($input['post_types']) && is_array($input['post_types']) ? array_map('sanitize_key', $input['post_types']) : array('page', 'post', 'elementor_library');
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'publish';
		$limit = isset($input['limit']) ? max(1, min(300, (int) $input['limit'])) : 100;

		if ('' === $target_lang) {
			return array('success' => false, 'message' => 'target_lang is required.');
		}

		$posts = array();
		if (!empty($ids)) {
			foreach ($ids as $id) {
				$post = get_post($id);
				if ($post) {
					$posts[] = $post;
				}
			}
		} else {
			$posts = get_posts(array(
				'post_type'      => $post_types,
				'post_status'    => mcp_wpml_status_filter($status),
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'all',
			));
		}

		$issues = array();
		$scanned = 0;
		$fixed_posts = array();
		$expected_trustpilot_locale = 'en' === $target_lang ? 'en-US' : '';

		foreach ($posts as $post) {
			$post_id = (int) $post->ID;
			$post_type = (string) $post->post_type;
			$details = mcp_wpml_lang_details($post_id, $post_type);
			if (!$details || (string) $details->language_code !== $target_lang) {
				continue;
			}

			$raw = get_post_meta($post_id, '_elementor_data', true);
			if (!is_string($raw) || '' === $raw) {
				continue;
			}
			$data = json_decode($raw, true);
			if (!is_array($data)) {
				continue;
			}

			$scanned++;
			$changed = false;

			$walk = function (&$nodes, array $path = array()) use (&$walk, &$issues, &$changed, $fix, $post_id, $post_type, $target_lang, $expected_trustpilot_locale): void {
				if (!is_array($nodes)) {
					return;
				}
				foreach ($nodes as &$node) {
					if (!is_array($node)) {
						continue;
					}
					$element_id = isset($node['id']) ? (string) $node['id'] : '';
					$widget_type = isset($node['widgetType']) ? (string) $node['widgetType'] : '';
					if ('global' === $widget_type && !empty($node['templateID'])) {
						$template_id = (int) $node['templateID'];
						$template_details = mcp_wpml_lang_details($template_id, 'elementor_library');
						$template_lang = $template_details && !empty($template_details->language_code) ? (string) $template_details->language_code : '';
						if ('' !== $template_lang && $template_lang !== $target_lang) {
							$translated_template_id = mcp_wpml_target_id_for_post_type($template_id, 'elementor_library', $target_lang);
							$issues[] = array(
								'post_id'                => $post_id,
								'post_type'              => $post_type,
								'element_id'             => $element_id,
								'path'                   => implode('>', $path),
								'reason'                 => 'global_widget_template_language_mismatch',
								'template_id'            => $template_id,
								'template_lang'          => $template_lang,
								'translated_template_id' => $translated_template_id,
							);
							if ($fix && $translated_template_id > 0 && $translated_template_id !== $template_id) {
								$node['templateID'] = $translated_template_id;
								$changed = true;
							}
						}
					}

					if (isset($node['settings']) && is_array($node['settings']) && '' !== $expected_trustpilot_locale) {
						foreach ($node['settings'] as $key => $value) {
							if (!is_string($value) || !str_contains($value, 'trustpilot-widget')) {
								continue;
							}
							if (preg_match('/data-locale="([^"]+)"/', $value, $match) && (string) $match[1] !== $expected_trustpilot_locale) {
								$issues[] = array(
									'post_id'         => $post_id,
									'post_type'       => $post_type,
									'element_id'      => $element_id,
									'path'            => implode('>', $path),
									'reason'          => 'trustpilot_locale_mismatch',
									'settings_key'    => (string) $key,
									'locale'          => (string) $match[1],
									'expected_locale' => $expected_trustpilot_locale,
								);
								if ($fix) {
									$value = preg_replace('/data-locale="[^"]+"/', 'data-locale="' . $expected_trustpilot_locale . '"', $value);
									$value = str_replace('https://no.trustpilot.com/', 'https://www.trustpilot.com/', (string) $value);
									$node['settings'][$key] = $value;
									$changed = true;
								}
							}
						}
					}

					if (isset($node['elements']) && is_array($node['elements'])) {
						$next_path = $path;
						$next_path[] = $element_id;
						$walk($node['elements'], $next_path);
					}
				}
				unset($node);
			};

			$walk($data);

			if ($fix && $changed) {
				update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($data)));
				delete_post_meta($post_id, '_elementor_css');
				clean_post_cache($post_id);
				$fixed_posts[] = $post_id;
			}
		}

		if ($fix && !empty($fixed_posts) && class_exists('\\Elementor\\Plugin')) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch (\Throwable $e) {
				// Cache clear failure should not hide successful data updates.
			}
		}

		return array(
			'success'          => true,
			'target_lang'      => $target_lang,
			'fix'              => $fix,
			'scanned_count'    => $scanned,
			'issue_count'      => count($issues),
			'issues'           => $issues,
			'fixed_post_ids'   => array_values(array_unique($fixed_posts)),
			'message'          => count($issues) > 0
				? 'Elementor language asset issues found.'
				: 'No Elementor language asset issues found.',
		);
	};

	wp_register_ability(
		'wpml/audit-elementor-language-assets',
		array(
			'label'       => 'Audit Elementor Language Assets',
			'description' => 'Audits translated Elementor content for source-language global widget template references and Trustpilot locale mismatches; optionally fixes them.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'target_lang' => array('type' => 'string', 'default' => 'en'),
					'fix'         => array('type' => 'boolean', 'default' => false),
					'ids'         => array('type' => 'array', 'items' => array('type' => 'integer')),
					'post_types'  => array('type' => 'array', 'items' => array('type' => 'string'), 'default' => array('page', 'post', 'elementor_library')),
					'status'      => array('type' => 'string', 'enum' => array('publish', 'draft', 'pending', 'private', 'any'), 'default' => 'publish'),
					'limit'       => array('type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 300),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array('type' => 'boolean'),
					'target_lang'    => array('type' => 'string'),
					'fix'            => array('type' => 'boolean'),
					'scanned_count'  => array('type' => 'integer'),
					'issue_count'    => array('type' => 'integer'),
					'issues'         => array('type' => 'array'),
					'fixed_post_ids' => array('type' => 'array'),
					'message'        => array('type' => 'string'),
				),
			),
			'execute_callback' => $audit_elementor_language_assets,
			'permission_callback' => function (): bool {
				return current_user_can('edit_posts') || current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	$ensure_page_translation = function ($input = array()): array {
		$input = is_array($input) ? $input : array();

		if (empty($input['source_id'])) {
			return array('success' => false, 'message' => 'source_id is required.');
		}
		$source_id      = (int) $input['source_id'];
		$target_lang    = isset($input['target_lang']) ? (string) $input['target_lang'] : 'en';
		$target_status  = isset($input['target_status']) ? (string) $input['target_status'] : 'draft';
		$copy_content   = !array_key_exists('copy_content', $input) || (bool) $input['copy_content'];
		$copy_excerpt   = !array_key_exists('copy_excerpt', $input) || (bool) $input['copy_excerpt'];
		$copy_elementor = !array_key_exists('copy_elementor', $input) || (bool) $input['copy_elementor'];

		$source = get_post($source_id);
		if (!$source || 'page' !== $source->post_type) {
			return array('success' => false, 'message' => 'Source page not found.');
		}

		$details = mcp_wpml_lang_details($source_id);
		if (!$details || empty($details->trid) || empty($details->language_code)) {
			return array('success' => false, 'message' => 'Could not read source WPML language details.');
		}

		$target_id = mcp_wpml_target_id($source_id, $target_lang);
		if ($target_id > 0) {
			return array(
				'success'       => true,
				'created'       => false,
				'source_id'     => $source_id,
				'target_id'     => $target_id,
				'target_lang'   => $target_lang,
				'target_status' => (string) get_post_status($target_id),
				'target_link'   => (string) get_permalink($target_id),
				'message'       => 'Translation already exists.',
			);
		}

		$parent_target = 0;
		if ((int) $source->post_parent > 0) {
			$parent_target = mcp_wpml_target_id((int) $source->post_parent, $target_lang);
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => $target_status,
				'post_title'   => (string) $source->post_title,
				'post_content' => $copy_content ? (string) $source->post_content : '',
				'post_excerpt' => $copy_excerpt ? (string) $source->post_excerpt : '',
				'post_parent'  => $parent_target,
			),
			true
		);
		if (is_wp_error($new_id)) {
			return array('success' => false, 'message' => $new_id->get_error_message());
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		do_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
			'wpml_set_element_language_details',
			array(
				'element_id'           => (int) $new_id,
				'element_type'         => 'post_page',
				'trid'                 => (int) $details->trid,
				'language_code'        => $target_lang,
				'source_language_code' => (string) $details->language_code,
				'check_duplicates'     => false,
			)
		);

		if ($copy_elementor) {
			mcp_wpml_copy_elementor_meta($source_id, (int) $new_id);
		}

		return array(
			'success'       => true,
			'created'       => true,
			'source_id'     => $source_id,
			'target_id'     => (int) $new_id,
			'target_lang'   => $target_lang,
			'target_status' => (string) get_post_status((int) $new_id),
			'target_link'   => (string) get_permalink((int) $new_id),
			'message'       => 'Translation created and linked.',
		);
	};

	wp_register_ability(
		'wpml/ensure-page-translation',
		array(
			'label'       => 'Ensure Page Translation',
			'description' => 'Create target-language page translation shell and link it in WPML.',
			'category'    => 'site',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array('source_id'),
				'properties' => array(
					'source_id'      => array('type' => 'integer'),
					'target_lang'    => array('type' => 'string', 'default' => 'en'),
					'target_status'  => array('type' => 'string', 'enum' => array('draft', 'publish', 'pending', 'private'), 'default' => 'draft'),
					'copy_content'   => array('type' => 'boolean', 'default' => true),
					'copy_excerpt'   => array('type' => 'boolean', 'default' => true),
					'copy_elementor' => array('type' => 'boolean', 'default' => true),
					),
					'additionalProperties' => false,
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array('type' => 'boolean'),
						'created'       => array('type' => 'boolean'),
						'source_id'     => array('type' => 'integer'),
						'target_id'     => array('type' => 'integer'),
						'target_lang'   => array('type' => 'string'),
						'target_status' => array('type' => 'string'),
						'target_link'   => array('type' => 'string'),
						'message'       => array('type' => 'string'),
					),
				),
				'execute_callback' => $ensure_page_translation,
				'permission_callback' => function (): bool {
					return current_user_can('edit_pages');
				},
				'meta' => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

	$audit_translation_integrity = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$source_lang = isset($input['source_lang']) ? sanitize_key((string) $input['source_lang']) : mcp_wpml_default_lang();
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : 'en';
		$post_types = isset($input['post_types']) && is_array($input['post_types']) ? array_map('sanitize_key', $input['post_types']) : array('page', 'post');
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'publish';
		$limit = isset($input['limit']) ? max(1, min(1000, (int) $input['limit'])) : 200;
		$include_elementor = !array_key_exists('include_elementor', $input) || (bool) $input['include_elementor'];
		$check_frontend = !empty($input['check_frontend']);
		$check_galleries = !array_key_exists('check_galleries', $input) || (bool) $input['check_galleries'];
		$frontend_timeout = isset($input['frontend_timeout']) ? max(2, min(20, (int) $input['frontend_timeout'])) : 8;
		$min_segment_chars = isset($input['min_segment_chars']) ? max(20, min(300, (int) $input['min_segment_chars'])) : 40;
		$min_shared_terms = isset($input['min_shared_terms_for_flag']) ? max(1, min(20, (int) $input['min_shared_terms_for_flag'])) : 2;
		$max_items = isset($input['max_items']) ? max(1, min(500, (int) $input['max_items'])) : 200;
		$ignore_terms = isset($input['ignore_terms']) && is_array($input['ignore_terms']) ? $input['ignore_terms'] : array();
		$frontend_markers = isset($input['frontend_markers']) && is_array($input['frontend_markers']) ? $input['frontend_markers'] : array();
		if (empty($frontend_markers) && 'en' === $target_lang) {
			$frontend_markers = array(
				'Book gratis',
				'Gratis hjemmebesøk',
				'Norskprodusert',
				'Når kvalitet',
				'ÅPNINGSTIDER',
				'KONTAKT OSS',
				'Vi er spesialister',
				'Hos oss får du',
				'Klar for din drømmegarderobe',
				'uncategorized-no',
				'data-locale="nb-NO"',
				'no.trustpilot.com',
			);
		}

		if ('' === $source_lang || '' === $target_lang || empty($post_types)) {
			return array('success' => false, 'message' => 'source_lang, target_lang, and post_types are required.');
		}

		$target_ids = array();
		if (!empty($input['source_ids']) && is_array($input['source_ids'])) {
			foreach ($input['source_ids'] as $source_id) {
				$source_id = (int) $source_id;
				$source_post = $source_id > 0 ? get_post($source_id) : null;
				if (!$source_post) {
					continue;
				}
				$target_id = mcp_wpml_target_id_for_post_type($source_id, (string) $source_post->post_type, $target_lang);
				if ($target_id > 0) {
					$target_ids[$target_id] = true;
				}
			}
		} elseif (!empty($input['ids']) && is_array($input['ids'])) {
			foreach ($input['ids'] as $id) {
				$id = (int) $id;
				if ($id > 0) {
					$target_ids[$id] = true;
				}
			}
		} else {
			$posts = mcp_wpml_with_language($target_lang, static function () use ($post_types, $status, $limit) {
				return get_posts(array(
					'post_type'        => $post_types,
					'post_status'      => mcp_wpml_status_filter($status),
					'posts_per_page'   => $limit,
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'fields'           => 'ids',
					'suppress_filters' => false,
				));
			});
			if (is_array($posts)) {
				foreach ($posts as $id) {
					$id = (int) $id;
					if ($id > 0) {
						$target_ids[$id] = true;
					}
				}
			}
		}

		$items = array();
		$scanned = 0;
		$frontend_checked = 0;
		$gallery_checked = 0;
		foreach (array_keys($target_ids) as $target_id) {
			$target = get_post((int) $target_id);
			if (!$target || !in_array((string) $target->post_type, $post_types, true)) {
				continue;
			}

			$details = mcp_wpml_lang_details((int) $target_id, (string) $target->post_type);
			if (!$details || (string) ($details->language_code ?? '') !== $target_lang) {
				continue;
			}
			$source_id = mcp_wpml_target_id_for_post_type((int) $target_id, (string) $target->post_type, $source_lang);
			$source = $source_id > 0 ? get_post($source_id) : null;
			if (!$source) {
				$items[] = array(
					'target_id' => (int) $target_id,
					'post_type' => (string) $target->post_type,
					'reason'    => 'source_translation_not_resolved',
					'target_url'=> (string) get_permalink((int) $target_id),
				);
				continue;
			}

			$scanned++;
			$issues = array();
			foreach (mcp_wpml_target_permalink_issues((int) $source_id, (int) $target_id, $target_lang) as $url_issue) {
				$issues[] = $url_issue;
			}

			if ($check_galleries) {
				$source_galleries = mcp_wpml_gallery_widgets_for_post((int) $source_id, (string) $source->post_type);
				$target_galleries = mcp_wpml_gallery_widgets_for_post((int) $target_id, (string) $target->post_type);
				if (!empty($source_galleries) || !empty($target_galleries)) {
					$gallery_checked++;
					$source_by_id = array();
					foreach ($source_galleries as $gallery) {
						$source_by_id[(string) $gallery['element_id']] = $gallery;
					}
					$target_by_id = array();
					foreach ($target_galleries as $gallery) {
						$target_by_id[(string) $gallery['element_id']] = $gallery;
					}
					foreach ($source_by_id as $element_id => $source_gallery) {
						if (!isset($target_by_id[$element_id])) {
							$issues[] = array(
								'reason' => 'gallery_missing_in_target',
								'element_id' => $element_id,
								'source_count' => (int) $source_gallery['count'],
							);
							continue;
						}
						if ((int) $source_gallery['count'] !== (int) $target_by_id[$element_id]['count']) {
							$issues[] = array(
								'reason' => 'gallery_item_count_mismatch',
								'element_id' => $element_id,
								'source_count' => (int) $source_gallery['count'],
								'target_count' => (int) $target_by_id[$element_id]['count'],
							);
						}
					}
					foreach ($target_by_id as $element_id => $target_gallery) {
						$caption_issues = mcp_wpml_gallery_attachment_caption_issues((array) $target_gallery['attachment_ids'], $target_lang);
						if (!empty($caption_issues)) {
							$issues[] = array(
								'reason' => 'gallery_caption_language_issues',
								'element_id' => $element_id,
								'count' => count($caption_issues),
								'examples' => array_slice($caption_issues, 0, 5),
							);
						}
					}
					$has_gallery_widget = false;
					foreach ($target_galleries as $target_gallery) {
						if ('gallery' === (string) ($target_gallery['widget_type'] ?? '')) {
							$has_gallery_widget = true;
							break;
						}
					}
					if ($has_gallery_widget) {
						$url = (string) get_permalink((int) $target_id);
						$response = wp_remote_get($url, array('timeout' => $frontend_timeout, 'redirection' => 3));
						if (is_wp_error($response)) {
							$issues[] = array(
								'reason' => 'gallery_frontend_fetch_failed',
								'message' => $response->get_error_message(),
							);
						} else {
							$body = (string) wp_remote_retrieve_body($response);
							if (!str_contains($body, 'elementor-widget-gallery')) {
								$issues[] = array('reason' => 'gallery_frontend_markup_missing');
							}
							$frontend_pos = strpos($body, '/elementor/assets/js/frontend.min.js');
							$egallery_pos = strpos($body, '/elementor/assets/lib/e-gallery/js/e-gallery.min.js');
							$pro_pos = strpos($body, '/elementor-pro/assets/js/frontend.min.js');
							if (false === $egallery_pos || false === $pro_pos || $egallery_pos > $pro_pos) {
								$issues[] = array(
									'reason' => 'gallery_frontend_asset_order_invalid',
									'elementor_frontend_pos' => false === $frontend_pos ? -1 : (int) $frontend_pos,
									'e_gallery_pos' => false === $egallery_pos ? -1 : (int) $egallery_pos,
									'pro_frontend_pos' => false === $pro_pos ? -1 : (int) $pro_pos,
									'message' => 'Elementor Pro gallery frontend can fail to initialize when e-gallery is missing or loaded after Elementor Pro frontend.',
								);
							}
						}
					}
				}
			}

			$shortcode_haystacks = array((string) $target->post_content);
			$elementor_raw = get_post_meta((int) $target_id, '_elementor_data', true);
			if (is_string($elementor_raw) && '' !== $elementor_raw) {
				$shortcode_haystacks[] = $elementor_raw;
			}
			foreach ($shortcode_haystacks as $haystack) {
				$ok = preg_match_all("/\\[insert\\s+page=[\\\"']?(\\d+)[\\\"']?/i", $haystack, $matches);
				if (false === $ok || empty($matches[1])) {
					continue;
				}
				foreach (array_unique(array_map('intval', $matches[1])) as $insert_id) {
					if ($insert_id <= 0) {
						continue;
					}
					$insert_post = get_post($insert_id);
					if (!$insert_post) {
						continue;
					}
					$insert_details = mcp_wpml_lang_details($insert_id, (string) $insert_post->post_type);
					$insert_lang = $insert_details && !empty($insert_details->language_code) ? (string) $insert_details->language_code : '';
					if ('' !== $insert_lang && $insert_lang !== $target_lang) {
						$translated_insert_id = mcp_wpml_target_id_for_post_type($insert_id, (string) $insert_post->post_type, $target_lang);
						$issues[] = array(
							'reason' => 'insert_page_shortcode_language_mismatch',
							'insert_page_id' => $insert_id,
							'insert_page_lang' => $insert_lang,
							'translated_insert_page_id' => $translated_insert_id,
						);
					}
				}
			}

			$source_text = mcp_wpml_collect_text_for_detection((int) $source_id, $include_elementor);
			$target_text = mcp_wpml_collect_text_for_detection((int) $target_id, $include_elementor);
			$segments = mcp_wpml_exact_segment_hits($source_text, $target_text, $min_segment_chars, 10);
			if (!empty($segments)) {
				$issues[] = array(
					'reason' => 'exact_source_text_segment_in_target',
					'count'  => count($segments),
					'examples' => $segments,
				);
			}
			$terms = mcp_wpml_shared_term_hits(
				$source_text,
				$target_text,
				array_merge(mcp_wpml_default_ignore_terms(), $ignore_terms),
				5,
				2,
				2,
				20
			);
			if (count($terms) >= $min_shared_terms) {
				$issues[] = array(
					'reason' => 'shared_source_terms_in_target',
					'count'  => count($terms),
					'terms'  => $terms,
				);
			}

			if ($check_frontend) {
				$frontend_checked++;
				$url = (string) get_permalink((int) $target_id);
				$response = wp_remote_get($url, array('timeout' => $frontend_timeout, 'redirection' => 3));
				if (is_wp_error($response)) {
					$issues[] = array(
						'reason'  => 'frontend_fetch_failed',
						'message' => $response->get_error_message(),
					);
				} else {
					$code = (int) wp_remote_retrieve_response_code($response);
					$body = (string) wp_remote_retrieve_body($response);
					if ($code < 200 || $code >= 400) {
						$issues[] = array(
							'reason' => 'frontend_http_error',
							'status' => $code,
						);
					}
					$found = array();
					foreach ($frontend_markers as $marker) {
						$marker = (string) $marker;
						if ('' !== $marker && str_contains($body, $marker)) {
							$found[] = $marker;
						}
					}
					if (!empty($found)) {
						$issues[] = array(
							'reason'  => 'frontend_source_language_markers_found',
							'markers' => $found,
						);
					}
				}
			}

			if (!empty($issues)) {
				$items[] = array(
					'target_id' => (int) $target_id,
					'source_id' => (int) $source_id,
					'post_type' => (string) $target->post_type,
					'title'     => (string) get_the_title((int) $target_id),
					'target_url'=> (string) get_permalink((int) $target_id),
					'source_url'=> (string) get_permalink((int) $source_id),
					'issues'    => $issues,
				);
				if (count($items) >= $max_items) {
					break;
				}
			}
		}

		return array(
			'success'          => true,
			'source_lang'      => $source_lang,
			'target_lang'      => $target_lang,
			'post_types'       => $post_types,
			'status'           => $status,
			'scanned_count'    => $scanned,
			'frontend_checked' => $frontend_checked,
			'gallery_checked'  => $gallery_checked,
			'issue_count'      => count($items),
			'items'            => $items,
			'message'          => empty($items) ? 'Translation integrity audit found no issues.' : 'Translation integrity audit found issues.',
		);
	};

	wp_register_ability(
		'wpml/audit-translation-integrity',
		array(
			'label'       => 'Audit Translation Integrity',
			'description' => 'Audits translated posts/pages for untranslated source text, source-language URL segments, and optional rendered frontend source-language markers.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'source_lang' => array('type' => 'string', 'default' => 'no'),
					'target_lang' => array('type' => 'string', 'default' => 'en'),
					'post_types'  => array('type' => 'array', 'items' => array('type' => 'string'), 'default' => array('page', 'post')),
					'status'      => array('type' => 'string', 'enum' => array('publish', 'draft', 'pending', 'private', 'any'), 'default' => 'publish'),
					'limit'       => array('type' => 'integer', 'default' => 200, 'minimum' => 1, 'maximum' => 1000),
					'ids'         => array('type' => 'array', 'items' => array('type' => 'integer'), 'description' => 'Optional explicit target IDs.'),
					'source_ids'  => array('type' => 'array', 'items' => array('type' => 'integer'), 'description' => 'Optional explicit source IDs to resolve into target translations.'),
					'include_elementor' => array('type' => 'boolean', 'default' => true),
					'check_frontend' => array('type' => 'boolean', 'default' => false),
					'check_galleries' => array('type' => 'boolean', 'default' => true, 'description' => 'Check Elementor gallery parity, translated media captions, and frontend gallery asset order.'),
					'frontend_timeout' => array('type' => 'integer', 'default' => 8, 'minimum' => 2, 'maximum' => 20),
					'frontend_markers' => array('type' => 'array', 'items' => array('type' => 'string')),
					'ignore_terms' => array('type' => 'array', 'items' => array('type' => 'string')),
					'min_segment_chars' => array('type' => 'integer', 'default' => 40, 'minimum' => 20, 'maximum' => 300),
					'min_shared_terms_for_flag' => array('type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 20),
					'max_items' => array('type' => 'integer', 'default' => 200, 'minimum' => 1, 'maximum' => 500),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'source_lang' => array('type' => 'string'),
					'target_lang' => array('type' => 'string'),
					'post_types' => array('type' => 'array'),
					'status' => array('type' => 'string'),
					'scanned_count' => array('type' => 'integer'),
					'frontend_checked' => array('type' => 'integer'),
					'gallery_checked' => array('type' => 'integer'),
					'issue_count' => array('type' => 'integer'),
					'items' => array('type' => 'array'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $audit_translation_integrity,
			'permission_callback' => function (): bool {
				return current_user_can('edit_posts') || current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	$detect_untranslated_content = function ($input = array()): array {
		$input = is_array($input) ? $input : array();

		$id               = isset($input['id']) ? (int) $input['id'] : 0;
		$source_id        = isset($input['source_id']) ? (int) $input['source_id'] : 0;
		$source_lang      = isset($input['source_lang']) ? (string) $input['source_lang'] : '';
		$target_lang      = isset($input['target_lang']) ? (string) $input['target_lang'] : 'en';
		$include_elem     = !array_key_exists('include_elementor', $input) || (bool) $input['include_elementor'];
		$ignore_terms     = isset($input['ignore_terms']) && is_array($input['ignore_terms']) ? $input['ignore_terms'] : array();
		$min_term_length  = isset($input['min_term_length']) ? max(2, min(15, (int) $input['min_term_length'])) : 5;
		$min_source_count = isset($input['min_source_count']) ? max(1, min(10, (int) $input['min_source_count'])) : 2;
		$min_target_count = isset($input['min_target_count_for_flag']) ? max(1, min(10, (int) $input['min_target_count_for_flag'])) : 2;
		$max_terms        = isset($input['max_terms']) ? max(1, min(100, (int) $input['max_terms'])) : 40;
		$min_shared_terms = isset($input['min_shared_terms_for_flag']) ? max(1, min(20, (int) $input['min_shared_terms_for_flag'])) : 2;
		$max_segments     = isset($input['max_segments']) ? max(1, min(50, (int) $input['max_segments'])) : 15;
		$min_segment_chars= isset($input['min_segment_chars']) ? max(20, min(300, (int) $input['min_segment_chars'])) : 40;

		if ($id <= 0 && $source_id <= 0) {
			return array('success' => false, 'message' => 'Provide either id (target page) or source_id.');
		}

		if ($id <= 0 && $source_id > 0) {
			$source_post = get_post($source_id);
			if (!$source_post) {
				return array(
					'success'    => false,
					'source_id'  => $source_id,
					'target_lang'=> $target_lang,
					'message'    => 'Source post not found.',
				);
			}
			$id = mcp_wpml_target_id_for_post_type($source_id, (string) $source_post->post_type, $target_lang);
			if ($id <= 0) {
				return array(
					'success'    => false,
					'source_id'  => $source_id,
					'target_lang'=> $target_lang,
					'message'    => 'No target-language post found for source_id.',
				);
			}
		}
		if ($source_id <= 0 && $id > 0) {
			$target_post = get_post($id);
			if ('' === $source_lang) {
				$details = $target_post ? mcp_wpml_lang_details($id, (string) $target_post->post_type) : null;
				if ($details && !empty($details->source_language_code)) {
					$source_lang = (string) $details->source_language_code;
				}
			}
			if ('' !== $source_lang && $target_post) {
				$source_id = mcp_wpml_target_id_for_post_type($id, (string) $target_post->post_type, $source_lang);
			}
		}
		if ($source_id <= 0) {
			return array(
				'success' => false,
				'id' => $id > 0 ? $id : null,
				'message' => 'Could not resolve source post. Provide source_id (or source_lang with id).',
			);
		}

		$post = get_post($id);
		if (!$post) {
			return array('success' => false, 'message' => 'Target post not found.');
		}
		if (!current_user_can('edit_post', $id)) {
			return array('success' => false, 'message' => 'You do not have permission to inspect this post.');
		}

		$source_text = mcp_wpml_collect_text_for_detection($source_id, $include_elem);
		$target_text = mcp_wpml_collect_text_for_detection($id, $include_elem);
		$terms = mcp_wpml_shared_term_hits(
			$source_text,
			$target_text,
			array_merge(mcp_wpml_default_ignore_terms(), $ignore_terms),
			$min_term_length,
			$min_source_count,
			$min_target_count,
			$max_terms
		);
		$segments = mcp_wpml_exact_segment_hits($source_text, $target_text, $min_segment_chars, $max_segments);
		$shared_terms_count = count($terms);
		$segment_hits_count = count($segments);
		$suspicious = $segment_hits_count > 0 || $shared_terms_count >= $min_shared_terms;

		return array(
			'success'             => true,
			'target_id'           => $id,
			'source_id'           => $source_id,
			'post_type'           => (string) $post->post_type,
			'source_lang'         => $source_lang,
			'target_lang'         => $target_lang,
			'title'               => (string) get_the_title($id),
			'status'              => (string) get_post_status($id),
			'link'                => (string) get_permalink($id),
			'source_text_length'  => strlen($source_text),
			'target_text_length'  => strlen($target_text),
			'min_target_count_for_flag' => $min_target_count,
			'min_shared_terms_for_flag' => $min_shared_terms,
			'shared_terms'        => $terms,
			'exact_segment_hits'  => $segments,
			'shared_terms_count'  => $shared_terms_count,
			'segment_hits_count'  => $segment_hits_count,
			'suspicious'          => $suspicious,
			'message'             => $suspicious
				? 'Possible untranslated content detected. Review before publish.'
				: 'No obvious untranslated source-language content detected.',
		);
	};

	wp_register_ability(
		'wpml/detect-untranslated-content',
		array(
			'label'       => 'Detect Untranslated Content',
			'description' => 'Language-agnostic check for untranslated source-language content in a WPML target post/page.',
			'category'    => 'site',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
					'id'                => array('type' => 'integer', 'description' => 'Target page ID.'),
					'source_id'         => array('type' => 'integer', 'description' => 'Source page ID (preferred).'),
					'source_lang'       => array('type' => 'string', 'description' => 'Optional source language code to resolve source_id when only id is given.'),
					'target_lang'       => array('type' => 'string', 'default' => 'en'),
					'include_elementor' => array('type' => 'boolean', 'default' => true),
					'ignore_terms'      => array(
						'type'  => 'array',
						'items' => array('type' => 'string'),
					),
					'min_term_length'  => array('type' => 'integer', 'default' => 5, 'minimum' => 2, 'maximum' => 15),
					'min_source_count' => array('type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 10),
					'min_target_count_for_flag' => array('type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 10),
					'max_terms'        => array('type' => 'integer', 'default' => 40, 'minimum' => 1, 'maximum' => 100),
					'min_shared_terms_for_flag' => array('type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 20),
					'min_segment_chars'=> array('type' => 'integer', 'default' => 40, 'minimum' => 20, 'maximum' => 300),
					'max_segments'     => array('type' => 'integer', 'default' => 15, 'minimum' => 1, 'maximum' => 50),
					),
					'additionalProperties' => false,
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'success'                    => array('type' => 'boolean'),
						'target_id'                  => array('type' => 'integer'),
						'source_id'                  => array('type' => 'integer'),
						'post_type'                  => array('type' => 'string'),
						'source_lang'                => array('type' => 'string'),
						'target_lang'                => array('type' => 'string'),
						'title'                      => array('type' => 'string'),
						'status'                     => array('type' => 'string'),
						'link'                       => array('type' => 'string'),
						'source_text_length'         => array('type' => 'integer'),
						'target_text_length'         => array('type' => 'integer'),
						'min_target_count_for_flag'  => array('type' => 'integer'),
						'min_shared_terms_for_flag'  => array('type' => 'integer'),
						'shared_terms'               => array('type' => 'array'),
						'exact_segment_hits'         => array('type' => 'array'),
						'shared_terms_count'         => array('type' => 'integer'),
						'segment_hits_count'         => array('type' => 'integer'),
						'suspicious'                 => array('type' => 'boolean'),
						'message'                    => array('type' => 'string'),
					),
				),
				'execute_callback' => $detect_untranslated_content,
				'permission_callback' => function (): bool {
					return current_user_can('edit_pages');
				},
				'meta' => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

	$remove_yoast_redirect = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$origin = isset($input['origin']) ? trim((string) $input['origin'], " \t\n\r\0\x0B/") : '';
		$url = isset($input['url']) ? trim((string) $input['url'], " \t\n\r\0\x0B/") : '';
		$clean_htaccess = !array_key_exists('clean_htaccess', $input) || (bool) $input['clean_htaccess'];

		if ('' === $origin || '' === $url) {
			return array(
				'success' => false,
				'message' => 'Provide both origin and url without leading slash.',
			);
		}

		$removed = array(
			'base' => array(),
			'export_plain' => array(),
		);

		$base = get_option('wpseo-premium-redirects-base', array());
		if (is_array($base)) {
			foreach ($base as $key => $redirect) {
				if (
					is_array($redirect)
					&& isset($redirect['origin'], $redirect['url'])
					&& trim((string) $redirect['origin'], '/') === $origin
					&& trim((string) $redirect['url'], '/') === $url
				) {
					$removed['base'][(string) $key] = $redirect;
					unset($base[$key]);
				}
			}
			if (!empty($removed['base'])) {
				update_option('wpseo-premium-redirects-base', $base, false);
			}
		}

		$plain = get_option('wpseo-premium-redirects-export-plain', array());
		if (is_array($plain)) {
			foreach ($plain as $key => $redirect) {
				if (
					trim((string) $key, '/') === $origin
					&& is_array($redirect)
					&& isset($redirect['url'])
					&& trim((string) $redirect['url'], '/') === $url
				) {
					$removed['export_plain'][(string) $key] = $redirect;
					unset($plain[$key]);
				}
			}
			if (!empty($removed['export_plain'])) {
				update_option('wpseo-premium-redirects-export-plain', $plain, false);
			}
		}

		$htaccess = array(
			'checked' => false,
			'writable' => false,
			'changed' => false,
			'removed_lines' => array(),
			'path' => '',
		);

			if ($clean_htaccess) {
				$path = trailingslashit(ABSPATH) . '.htaccess';
				$filesystem = mcp_wpml_filesystem();
				$htaccess['checked'] = true;
				$htaccess['path'] = $path;
				$htaccess['writable'] = is_object($filesystem) && $filesystem->is_writable($path);
				if (is_object($filesystem) && $filesystem->exists($path) && $htaccess['writable']) {
					$contents = (string) $filesystem->get_contents($path);
					$lines = preg_split("/(\r\n|\n|\r)/", $contents);
					$new_lines = array();
					foreach ($lines as $line) {
					$normalized = trim((string) $line);
					if (false !== strpos($normalized, $origin) && false !== strpos($normalized, $url)) {
						$htaccess['removed_lines'][] = $line;
						continue;
					}
					$new_lines[] = $line;
					}
					if (!empty($htaccess['removed_lines'])) {
						$ending = false !== strpos($contents, "\r\n") ? "\r\n" : "\n";
						$filesystem->put_contents($path, implode($ending, $new_lines), FS_CHMOD_FILE);
						$htaccess['changed'] = true;
					}
				}
		}

		return array(
			'success' => true,
			'origin' => $origin,
			'url' => $url,
			'removed' => $removed,
			'htaccess' => $htaccess,
			'message' => 'Yoast redirect options cleaned; .htaccess cleaned when a matching line was found.',
		);
	};

	wp_register_ability(
		'wpml/remove-yoast-redirect',
		array(
			'label'       => 'Remove Yoast Redirect',
			'description' => 'Remove one exact Yoast Premium redirect from redirect options and matching .htaccess export lines.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array('origin', 'url'),
				'properties' => array(
					'origin' => array('type' => 'string', 'description' => 'Redirect origin path without domain.'),
					'url' => array('type' => 'string', 'description' => 'Redirect target path without domain.'),
					'clean_htaccess' => array('type' => 'boolean', 'default' => true),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'origin' => array('type' => 'string'),
					'url' => array('type' => 'string'),
					'removed' => array('type' => 'object'),
					'htaccess' => array('type' => 'object'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $remove_yoast_redirect,
			'permission_callback' => function (): bool {
				return current_user_can('manage_options');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	$repair_elementor_gallery_media = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$page_id = isset($input['page_id']) ? (int) $input['page_id'] : 0;
		$element_id = isset($input['element_id']) ? trim((string) $input['element_id']) : '';
		$attachment_ids = array();
		$size = isset($input['size']) ? sanitize_key((string) $input['size']) : 'medium';
		$dry_run = !empty($input['dry_run']);
		$force = !empty($input['force']);

		if (isset($input['attachment_ids']) && is_array($input['attachment_ids'])) {
			foreach ($input['attachment_ids'] as $attachment_id) {
				$attachment_id = (int) $attachment_id;
				if ($attachment_id > 0) {
					$attachment_ids[] = $attachment_id;
				}
			}
		}

		if ($page_id > 0 && '' !== $element_id) {
			$raw = get_post_meta($page_id, '_elementor_data', true);
			$data = is_string($raw) && '' !== $raw ? json_decode($raw, true) : array();
			if (!is_array($data)) {
				return array(
					'success' => false,
					'message' => 'Elementor data could not be decoded.',
				);
			}

			$element = mcp_wpml_find_element_by_id($data, $element_id);
			if (null === $element) {
				return array(
					'success' => false,
					'message' => 'Element not found in Elementor data.',
				);
			}

			$gallery = $element['settings']['gallery'] ?? array();
			if (!is_array($gallery)) {
				$gallery = array();
			}

			foreach ($gallery as $item) {
				$attachment_id = is_array($item) && isset($item['id']) ? (int) $item['id'] : 0;
				if ($attachment_id > 0) {
					$attachment_ids[] = $attachment_id;
				}
			}
		}

		$attachment_ids = array_values(array_unique(array_filter($attachment_ids)));
		if (empty($attachment_ids)) {
			return array(
				'success' => false,
				'message' => 'Provide attachment_ids or a page_id plus element_id containing a gallery.',
			);
		}

		if (!function_exists('wp_generate_attachment_metadata')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$items = array();
		foreach ($attachment_ids as $attachment_id) {
			$post = get_post($attachment_id);
			if (!$post || 'attachment' !== $post->post_type) {
				$items[] = array(
					'id' => $attachment_id,
					'success' => false,
					'message' => 'Attachment not found.',
				);
				continue;
			}

			$before = mcp_wpml_attachment_size_file_exists($attachment_id, $size);
			$file = get_attached_file($attachment_id);
			if (!is_string($file) || '' === $file || !file_exists($file)) {
				$items[] = array(
					'id' => $attachment_id,
					'success' => false,
					'before' => $before,
					'message' => 'Original attachment file is missing.',
				);
				continue;
			}

			$regenerated = false;
			$error = '';
			if (!$dry_run && ($force || !$before['has_size'] || !$before['exists'])) {
				$new_meta = wp_generate_attachment_metadata($attachment_id, $file);
				if (is_array($new_meta) && !empty($new_meta)) {
					wp_update_attachment_metadata($attachment_id, $new_meta);
					clean_attachment_cache($attachment_id);
					$regenerated = true;
				} else {
					$error = 'wp_generate_attachment_metadata returned no metadata.';
				}
			}

			$after = mcp_wpml_attachment_size_file_exists($attachment_id, $size);
			$items[] = array(
				'id' => $attachment_id,
				'title' => get_the_title($attachment_id),
				'file' => wp_basename($file),
				'size' => $size,
				'before' => $before,
				'after' => $after,
				'regenerated' => $regenerated,
				'success' => '' === $error && ($dry_run || $after['exists']),
				'message' => '' !== $error ? $error : ($regenerated ? 'Metadata regenerated.' : 'No regeneration needed.'),
			);
		}

		if (!$dry_run) {
			if (class_exists('\\Elementor\\Plugin')) {
				try {
					\Elementor\Plugin::$instance->files_manager->clear_cache();
				} catch (\Throwable $e) {
					unset($e);
				}
			}
			if ($page_id > 0) {
				clean_post_cache($page_id);
			}
		}

		$failed = array_values(array_filter($items, function ($item): bool {
			return empty($item['success']);
		}));

		return array(
			'success' => empty($failed),
			'page_id' => $page_id,
			'element_id' => $element_id,
			'size' => $size,
			'dry_run' => $dry_run,
			'force' => $force,
			'count' => count($items),
			'failed_count' => count($failed),
			'items' => $items,
			'message' => empty($failed)
				? 'Translated media thumbnail metadata is present and files exist.'
				: 'Some translated media thumbnail files are still missing or could not be regenerated.',
		);
	};

	wp_register_ability(
		'wpml/repair-elementor-gallery-media',
		array(
			'label'       => 'Repair Elementor Gallery Media',
			'description' => 'Regenerate missing thumbnail metadata/files for WPML-translated media used by an Elementor gallery.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'page_id' => array('type' => 'integer', 'description' => 'Page/post containing the Elementor gallery.'),
					'element_id' => array('type' => 'string', 'description' => 'Elementor gallery element ID.'),
					'attachment_ids' => array(
						'type' => 'array',
						'items' => array('type' => 'integer'),
						'description' => 'Optional explicit attachment IDs to repair.',
					),
					'size' => array('type' => 'string', 'default' => 'medium', 'description' => 'Attachment size to verify after regeneration.'),
					'force' => array('type' => 'boolean', 'default' => false, 'description' => 'Regenerate metadata even when the requested size file already exists.'),
					'dry_run' => array('type' => 'boolean', 'default' => false),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'page_id' => array('type' => 'integer'),
					'element_id' => array('type' => 'string'),
					'size' => array('type' => 'string'),
					'dry_run' => array('type' => 'boolean'),
					'force' => array('type' => 'boolean'),
					'count' => array('type' => 'integer'),
					'failed_count' => array('type' => 'integer'),
					'items' => array('type' => 'array'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $repair_elementor_gallery_media,
			'permission_callback' => function (): bool {
				return current_user_can('upload_files') && current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	$audit_elementor_galleries = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$post_types = isset($input['post_types']) && is_array($input['post_types']) ? array_map('sanitize_key', $input['post_types']) : array('page', 'post');
		$languages = isset($input['languages']) && is_array($input['languages']) ? array_map('sanitize_key', $input['languages']) : array();
		$size = isset($input['size']) ? sanitize_key((string) $input['size']) : 'medium';
		$repair_thumbnails = !empty($input['repair_thumbnails']);
		$force = !empty($input['force']);
		$caption_audit = !array_key_exists('caption_audit', $input) || (bool) $input['caption_audit'];

		$galleries = array();
		$scanned_posts = 0;
		$query_languages = !empty($languages) ? $languages : array('');
		foreach ($query_languages as $query_language) {
			mcp_wpml_with_language(
				(string) $query_language,
				function () use ($post_types, $languages, &$galleries, &$scanned_posts): void {
					$query = new WP_Query(
						array(
							'post_type'      => $post_types,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'orderby'        => 'ID',
							'order'          => 'ASC',
							// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This audit intentionally targets published posts that have Elementor data.
							'meta_query'     => array(
								array(
									'key'     => '_elementor_data',
									'compare' => 'EXISTS',
								),
							),
						)
					);

					foreach ($query->posts as $post_id) {
						$post_id = (int) $post_id;
						$post = get_post($post_id);
						if (!$post) {
							continue;
						}
						$details = mcp_wpml_lang_details($post_id, (string) $post->post_type);
						$lang = $details && isset($details->language_code) ? (string) $details->language_code : '';
						if (!empty($languages) && !in_array($lang, $languages, true)) {
							continue;
						}
						$raw = get_post_meta($post_id, '_elementor_data', true);
						$data = is_string($raw) && '' !== $raw ? json_decode($raw, true) : array();
						if (!is_array($data)) {
							continue;
						}
						$scanned_posts++;
						mcp_wpml_collect_gallery_widgets(
							$data,
							$galleries,
							array(
								'post_id'   => $post_id,
								'post_type' => (string) $post->post_type,
								'lang'      => $lang,
								'title'     => get_the_title($post_id),
								'link'      => get_permalink($post_id),
							)
						);
					}
				}
			);
		}

		$attachment_usage = array();
		foreach ($galleries as $gallery) {
			foreach ($gallery['attachment_ids'] as $attachment_id) {
				$attachment_id = (int) $attachment_id;
				if (!isset($attachment_usage[$attachment_id])) {
					$attachment_usage[$attachment_id] = array();
				}
				$attachment_usage[$attachment_id][] = array(
					'post_id'      => $gallery['post_id'],
					'lang'         => $gallery['lang'],
					'element_id'   => $gallery['element_id'],
					'widget_type'  => $gallery['widget_type'],
				);
			}
		}

		if ($repair_thumbnails && !function_exists('wp_generate_attachment_metadata')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$media = array();
		$caption_issues = array();
		$failed = array();
		$regenerated_count = 0;
		foreach ($attachment_usage as $attachment_id => $usage) {
			$attachment_id = (int) $attachment_id;
			$post = get_post($attachment_id);
			if (!$post || 'attachment' !== $post->post_type) {
				$failed[] = array('id' => $attachment_id, 'message' => 'Attachment not found.');
				continue;
			}

			$before = mcp_wpml_attachment_size_file_exists($attachment_id, $size);
			$after = $before;
			$regenerated = false;
			$error = '';
			if ($repair_thumbnails && ($force || !$before['has_size'] || !$before['exists'])) {
				$file = get_attached_file($attachment_id);
				if (!is_string($file) || '' === $file || !file_exists($file)) {
					$error = 'Original attachment file is missing.';
				} else {
					$new_meta = wp_generate_attachment_metadata($attachment_id, $file);
					if (is_array($new_meta) && !empty($new_meta)) {
						wp_update_attachment_metadata($attachment_id, $new_meta);
						clean_attachment_cache($attachment_id);
						$regenerated = true;
						$regenerated_count++;
					} else {
						$error = 'wp_generate_attachment_metadata returned no metadata.';
					}
				}
				$after = mcp_wpml_attachment_size_file_exists($attachment_id, $size);
			}

			if ('' !== $error) {
				$failed[] = array('id' => $attachment_id, 'message' => $error, 'before' => $before, 'after' => $after);
			}

			$details = mcp_wpml_lang_details($attachment_id, 'attachment');
			$lang = $details && isset($details->language_code) ? (string) $details->language_code : '';
			$title = (string) get_post_field('post_title', $attachment_id, 'raw');
			$caption = (string) get_post_field('post_excerpt', $attachment_id, 'raw');
			$description = (string) get_post_field('post_content', $attachment_id, 'raw');
			$source_id = 0;
			if ('en' === $lang) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML media translation lookup.
				$translated = apply_filters('wpml_object_id', $attachment_id, 'attachment', false, 'no');
				$source_id = is_numeric($translated) ? (int) $translated : 0;
			}

			$item = array(
				'id'          => $attachment_id,
				'lang'        => $lang,
				'title'       => $title,
				'caption'     => $caption,
				'description' => $description,
				'source_id'   => $source_id,
				'usage'       => $usage,
				'before'      => $before,
				'after'       => $after,
				'regenerated' => $regenerated,
			);
			$media[] = $item;

			if ($caption_audit && 'en' === $lang) {
				$combined = $title . "\n" . $caption . "\n" . $description;
				if (mcp_wpml_text_has_norwegian_markers($combined)) {
					$item['source_title'] = $source_id > 0 ? (string) get_post_field('post_title', $source_id, 'raw') : '';
					$item['source_caption'] = $source_id > 0 ? (string) get_post_field('post_excerpt', $source_id, 'raw') : '';
					$item['source_description'] = $source_id > 0 ? (string) get_post_field('post_content', $source_id, 'raw') : '';
					$caption_issues[] = $item;
				}
			}
		}

		if ($repair_thumbnails && class_exists('\\Elementor\\Plugin')) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch (\Throwable $e) {
				unset($e);
			}
		}

		return array(
			'success'             => empty($failed),
			'post_types'          => $post_types,
			'languages'           => $languages,
			'size'                => $size,
			'repair_thumbnails'   => $repair_thumbnails,
			'force'               => $force,
			'scanned_posts'       => $scanned_posts,
			'gallery_count'       => count($galleries),
			'attachment_count'    => count($media),
			'regenerated_count'   => $regenerated_count,
			'caption_issue_count' => count($caption_issues),
			'failed_count'        => count($failed),
			'galleries'           => $galleries,
			'media'               => $media,
			'caption_issues'      => $caption_issues,
			'failed'              => $failed,
			'message'             => empty($failed) ? 'Elementor gallery media audit completed.' : 'Some gallery attachments could not be repaired.',
		);
	};

	wp_register_ability(
		'wpml/audit-elementor-gallery-media',
		array(
			'label'       => 'Audit Elementor Gallery Media',
			'description' => 'Find published Elementor galleries, optionally regenerate gallery thumbnails, and report English media captions that still look Norwegian.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'post_types' => array('type' => 'array', 'items' => array('type' => 'string'), 'default' => array('page', 'post')),
					'languages' => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Optional WPML language filter, e.g. ["en"].'),
					'size' => array('type' => 'string', 'default' => 'medium'),
					'repair_thumbnails' => array('type' => 'boolean', 'default' => false),
					'force' => array('type' => 'boolean', 'default' => false),
					'caption_audit' => array('type' => 'boolean', 'default' => true),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'gallery_count' => array('type' => 'integer'),
					'attachment_count' => array('type' => 'integer'),
					'regenerated_count' => array('type' => 'integer'),
					'caption_issue_count' => array('type' => 'integer'),
					'failed_count' => array('type' => 'integer'),
					'galleries' => array('type' => 'array'),
					'media' => array('type' => 'array'),
					'caption_issues' => array('type' => 'array'),
					'failed' => array('type' => 'array'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $audit_elementor_galleries,
			'permission_callback' => function (): bool {
				return current_user_can('upload_files') && current_user_can('edit_pages');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	$update_media_captions_batch = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$updates = isset($input['updates']) && is_array($input['updates']) ? $input['updates'] : array();
		$items = array();
		foreach ($updates as $update) {
			if (!is_array($update)) {
				continue;
			}
			$id = isset($update['id']) ? (int) $update['id'] : 0;
			$post = $id > 0 ? get_post($id) : null;
			if (!$post || 'attachment' !== $post->post_type) {
				$items[] = array('id' => $id, 'success' => false, 'message' => 'Attachment not found.');
				continue;
			}
			$postarr = array('ID' => $id);
			if (isset($update['title'])) {
				$postarr['post_title'] = sanitize_text_field((string) $update['title']);
			}
			if (isset($update['caption'])) {
				$postarr['post_excerpt'] = sanitize_text_field((string) $update['caption']);
			}
			if (isset($update['description'])) {
				$postarr['post_content'] = wp_kses_post((string) $update['description']);
			}
			$result = wp_update_post(wp_slash($postarr), true);
			if (is_wp_error($result)) {
				$items[] = array('id' => $id, 'success' => false, 'message' => $result->get_error_message());
				continue;
			}
			if (isset($update['alt_text'])) {
				update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field((string) $update['alt_text']));
			}
			clean_attachment_cache($id);
			$items[] = array('id' => $id, 'success' => true, 'title' => get_the_title($id));
		}
		$failed = array_values(array_filter($items, function ($item): bool {
			return empty($item['success']);
		}));
		return array(
			'success' => empty($failed),
			'count' => count($items),
			'failed_count' => count($failed),
			'items' => $items,
			'message' => empty($failed) ? 'Media captions updated.' : 'Some media captions could not be updated.',
		);
	};

	wp_register_ability(
		'wpml/update-media-captions-batch',
		array(
			'label'       => 'Update Media Captions Batch',
			'description' => 'Bulk update attachment title/caption/description fields for translated gallery media.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array('updates'),
				'properties' => array(
					'updates' => array(
						'type' => 'array',
						'items' => array(
							'type' => 'object',
							'properties' => array(
								'id' => array('type' => 'integer'),
								'title' => array('type' => 'string'),
								'caption' => array('type' => 'string'),
								'description' => array('type' => 'string'),
								'alt_text' => array('type' => 'string'),
							),
						),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'count' => array('type' => 'integer'),
					'failed_count' => array('type' => 'integer'),
					'items' => array('type' => 'array'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $update_media_captions_batch,
			'permission_callback' => function (): bool {
				return current_user_can('upload_files');
			},
			'meta' => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

}

add_action('wp_abilities_api_init', 'mcp_wpml_register_abilities');
