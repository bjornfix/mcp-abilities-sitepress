<?php
/**
 * Plugin Name: MCP Abilities - SitePress
 * Plugin URI: https://devenia.com
 * Description: WPML translation mapping and translation-shell helper abilities for MCP.
 * Version: 0.3.0
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_ready(): bool {
	return function_exists('wp_register_ability') && defined('ICL_SITEPRESS_VERSION');
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

function mcp_wpml_lang_details(int $page_id) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$details = apply_filters(
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		'wpml_element_language_details',
		null,
		array(
			'element_id'   => $page_id,
			'element_type' => 'post_page',
		)
	);
	return is_object($details) ? $details : null;
}

function mcp_wpml_get_active_languages(bool $skip_missing = false): array {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
	$languages = apply_filters(
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

function mcp_wpml_string_seems_human_text(string $value): bool {
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
	if ('' === trim($term)) {
		return 0;
	}
	$pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/ui';
	$count = preg_match_all($pattern, $text);
	return false === $count ? 0 : (int) $count;
}

function mcp_wpml_text_tokens(string $text, int $min_len = 5): array {
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

		$details = mcp_wpml_lang_details($id);
		if (!$details) {
			return array('success' => false, 'message' => 'Could not resolve WPML language details.');
		}

		return array(
			'success' => true,
			'id' => $id,
			'post_type' => (string) $post->post_type,
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
			$id = mcp_wpml_target_id($source_id, $target_lang);
			if ($id <= 0) {
				return array(
					'success'    => false,
					'source_id'  => $source_id,
					'target_lang'=> $target_lang,
					'message'    => 'No target-language page found for source_id.',
				);
			}
		}
		if ($source_id <= 0 && $id > 0) {
			if ('' === $source_lang) {
				$details = mcp_wpml_lang_details($id);
				if ($details && !empty($details->source_language_code)) {
					$source_lang = (string) $details->source_language_code;
				}
			}
			if ('' !== $source_lang) {
				$source_id = mcp_wpml_target_id($id, $source_lang);
			}
		}
		if ($source_id <= 0) {
			return array(
				'success' => false,
				'id' => $id > 0 ? $id : null,
				'message' => 'Could not resolve source page. Provide source_id (or source_lang with id).',
			);
		}

		$post = get_post($id);
		if (!$post || 'page' !== $post->post_type) {
			return array('success' => false, 'message' => 'Target page not found.');
		}
		if (!current_user_can('edit_post', $id)) {
			return array('success' => false, 'message' => 'You do not have permission to inspect this page.');
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
			'description' => 'Language-agnostic check for untranslated source-language content in a WPML target page.',
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

}

add_action('wp_abilities_api_init', 'mcp_wpml_register_abilities');
