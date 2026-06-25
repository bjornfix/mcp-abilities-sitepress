<?php
/**
 * WPML language-switcher inspection and recovery abilities.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_register_language_switcher_abilities(): void {
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

}
