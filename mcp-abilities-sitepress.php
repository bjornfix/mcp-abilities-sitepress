<?php
/**
 * Plugin Name: MCP Abilities - SitePress
 * Plugin URI: https://devenia.com
 * Description: WPML translation mapping and translation-shell helper abilities for MCP.
 * Version: 0.3.42
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 * Text Domain: mcp-abilities-sitepress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/includes/translation-query-abilities.php';
require_once __DIR__ . '/includes/translation-mutation-abilities.php';
require_once __DIR__ . '/includes/language-switcher-abilities.php';
require_once __DIR__ . '/includes/translation-link-audit-abilities.php';
require_once __DIR__ . '/includes/translation-shell-abilities.php';
require_once __DIR__ . '/includes/translation-integrity-abilities.php';
require_once __DIR__ . '/includes/elementor-media-abilities.php';

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

function mcp_wpml_element_type_for_taxonomy(string $taxonomy): string {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$element_type = apply_filters('wpml_element_type', 'tax_' . $taxonomy);
	return is_string($element_type) && '' !== $element_type ? $element_type : 'tax_' . $taxonomy;
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

function mcp_wpml_term_taxonomy_id(int $term_id, string $taxonomy): int {
	$term = get_term($term_id, $taxonomy);
	if (!$term || is_wp_error($term) || empty($term->term_taxonomy_id)) {
		return 0;
	}

	return (int) $term->term_taxonomy_id;
}

function mcp_wpml_get_term_by_term_taxonomy_id(int $term_taxonomy_id) {
	if ($term_taxonomy_id <= 0) {
		return null;
	}

	$terms = get_terms(
		array(
			'hide_empty'       => false,
			'number'           => 1,
			'term_taxonomy_id' => array($term_taxonomy_id),
		)
	);

	if (is_wp_error($terms) || empty($terms) || !is_array($terms)) {
		return null;
	}

	$term = reset($terms);
	return $term instanceof WP_Term ? $term : null;
}

function mcp_wpml_term_lang_details(int $term_id, string $taxonomy) {
	$term_taxonomy_id = mcp_wpml_term_taxonomy_id($term_id, $taxonomy);
	if ($term_taxonomy_id <= 0) {
		return null;
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$details = apply_filters(
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		'wpml_element_language_details',
		null,
		array(
			'element_id'   => $term_taxonomy_id,
			'element_type' => mcp_wpml_element_type_for_taxonomy($taxonomy),
		)
	);
	return is_object($details) ? $details : null;
}

function mcp_wpml_term_link(WP_Term $term): string {
	$link = get_term_link($term);
	return is_wp_error($link) ? '' : (string) $link;
}

function mcp_wpml_elementor_translation_sibling_post_ids(array $sibling_ids, int $post_id, WP_Post $post): array {
	if (!defined('ICL_SITEPRESS_VERSION')) {
		return $sibling_ids;
	}

	$element_type = mcp_wpml_element_type_for_post_type((string) $post->post_type);
	$details = mcp_wpml_lang_details($post_id, (string) $post->post_type);
	if (!$details || empty($details->trid)) {
		return $sibling_ids;
	}

	$wpml_translations_hook = 'wpml_get_element_translations';
	$translations = call_user_func_array('apply_filters', array($wpml_translations_hook, null, $details->trid, $element_type));
	if (!is_array($translations)) {
		return $sibling_ids;
	}

	foreach ($translations as $translation) {
		if (is_object($translation) && !empty($translation->element_id)) {
			$sibling_ids[] = (int) $translation->element_id;
		}
	}

	return array_values(
		array_unique(
			array_filter(
				array_map('intval', $sibling_ids),
				static function (int $sibling_id) use ($post_id): bool {
					return $sibling_id > 0 && $sibling_id !== $post_id;
				}
			)
		)
	);
}

function mcp_wpml_register_elementor_translation_sibling_filter(): void {
	static $registered = false;

	if ($registered) {
		return;
	}

	if (!function_exists('mcp_abilities_elementor_translation_sibling_filter_name')) {
		return;
	}

	$filter_name = mcp_abilities_elementor_translation_sibling_filter_name();
	if (!has_filter($filter_name, 'mcp_wpml_elementor_translation_sibling_post_ids')) {
		add_filter($filter_name, 'mcp_wpml_elementor_translation_sibling_post_ids', 10, 3);
	}

	$registered = true;
}

add_action('plugins_loaded', 'mcp_wpml_register_elementor_translation_sibling_filter', 99);
add_action('init', 'mcp_wpml_register_elementor_translation_sibling_filter', 1);
add_action('wp_loaded', 'mcp_wpml_register_elementor_translation_sibling_filter', 1);

function mcp_wpml_render_translated_contact_form_7_shortcode($return, string $tag, $attr) {
	if ('contact-form-7' !== $tag || !defined('ICL_SITEPRESS_VERSION') || !function_exists('wpcf7_contact_form')) {
		return $return;
	}

	if (!is_array($attr)) {
		return $return;
	}

	$form_id = 0;
	if (isset($attr['id'])) {
		$form_id = mcp_wpml_shortcode_positive_int($attr['id']);
	} elseif (isset($attr[0])) {
		$form_id = mcp_wpml_shortcode_positive_int($attr[0]);
	}

	if ($form_id <= 0) {
		return $return;
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$current_lang = apply_filters('wpml_current_language', null);
	$current_lang = is_string($current_lang) ? $current_lang : '';
	if ('' === $current_lang || $current_lang === mcp_wpml_default_lang()) {
		return $return;
	}

	$current_details = mcp_wpml_lang_details($form_id, 'wpcf7_contact_form');
	if ($current_details && !empty($current_details->language_code) && $current_lang === (string) $current_details->language_code) {
		return $return;
	}

	$translated_id = mcp_wpml_target_id_for_post_type($form_id, 'wpcf7_contact_form', $current_lang);
	if ($translated_id <= 0 || $translated_id === $form_id) {
		return $return;
	}

	$translated = get_post($translated_id);
	if (!$translated || 'wpcf7_contact_form' !== (string) $translated->post_type || 'publish' !== (string) $translated->post_status) {
		return $return;
	}

	$translated_details = mcp_wpml_lang_details($translated_id, 'wpcf7_contact_form');
	if ($translated_details && !empty($translated_details->language_code) && $current_lang !== (string) $translated_details->language_code) {
		return $return;
	}

	$attr['id'] = (string) $translated_id;
	if (isset($attr[0])) {
		$attr[0] = (string) $translated_id;
	}

	global $shortcode_tags;
	if (empty($shortcode_tags[$tag]) || !is_callable($shortcode_tags[$tag])) {
		return $return;
	}

	return call_user_func($shortcode_tags[$tag], $attr, '', $tag);
}

add_filter('pre_do_shortcode_tag', 'mcp_wpml_render_translated_contact_form_7_shortcode', 10, 3);

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
		'center',
		'left',
		'right',
		'glass',
		'intelligent',
		'priva-lite',
		'plexiglass',
		'lexan',
		'design',
		'aluminium',
		'rem',
		'px',
	);
}

function mcp_wpml_elementor_excluded_keys(): array {
	return array(
		'__globals__' => true,
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
		'button_background_color' => true,
		'button_text_color' => true,
		'link_color' => true,
		'title_color' => true,
		'text_color' => true,
		'typography_typography' => true,
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
		$key_l = is_string($key) ? (function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key)) : '';
		if ('' !== $key_l && isset($excluded[$key_l])) {
			continue;
		}
		if (is_array($value) || is_object($value)) {
			mcp_wpml_collect_elementor_text_values($value, $parts, $depth + 1);
			continue;
		}
		if (!is_string($value)) {
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

	mcp_wpml_register_translation_query_abilities();

	mcp_wpml_register_translation_mutation_abilities();

	mcp_wpml_register_translation_link_audit_abilities();

	mcp_wpml_register_language_switcher_abilities();

	mcp_wpml_register_translation_shell_abilities();

	mcp_wpml_register_translation_integrity_abilities();

	mcp_wpml_register_elementor_media_abilities();
}

add_action('wp_abilities_api_init', 'mcp_wpml_register_abilities');
