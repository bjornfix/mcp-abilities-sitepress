<?php
/**
 * WPML translation mutation abilities.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_register_translation_mutation_abilities(): void {
	$set_post_language_details = function ($input = array()): array {
		$input       = is_array($input) ? $input : array();
		$id          = isset($input['id']) ? (int) $input['id'] : 0;
		$lang        = isset($input['language_code']) ? sanitize_key((string) $input['language_code']) : '';
		$source_lang = isset($input['source_language_code']) ? sanitize_key((string) $input['source_language_code']) : '';

		if ($id <= 0 || '' === $lang) {
			return array('success' => false, 'message' => 'id and language_code are required.');
		}

		$post = get_post($id);
		if (!$post) {
			return array('success' => false, 'message' => 'Post not found.');
		}

		$post_type = (string) $post->post_type;
		if (in_array($post_type, array('revision', 'nav_menu_item'), true)) {
			return array('success' => false, 'message' => 'Post type is not supported.', 'post_type' => $post_type);
		}

		$element_type = mcp_wpml_element_type_for_post_type($post_type);
		$existing     = mcp_wpml_lang_details($id, $post_type);
		if ($existing && !empty($existing->trid) && !empty($existing->language_code)) {
			return array(
				'success'              => true,
				'created'              => false,
				'id'                   => $id,
				'post_type'            => $post_type,
				'element_type'         => $element_type,
				'trid'                 => (int) $existing->trid,
				'language_code'        => (string) $existing->language_code,
				'source_language_code' => (string) ($existing->source_language_code ?? ''),
				'message'              => 'Post already has WPML language details.',
			);
		}

		$details = array(
			'element_id'           => $id,
			'element_type'         => $element_type,
			'language_code'        => $lang,
			'source_language_code' => $source_lang,
			'check_duplicates'     => false,
		);
		if (!empty($input['trid'])) {
			$details['trid'] = (int) $input['trid'];
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		do_action('wpml_set_element_language_details', $details);

		clean_post_cache($id);
		$updated = mcp_wpml_lang_details($id, $post_type);
		if (!$updated || empty($updated->trid) || empty($updated->language_code)) {
			return array('success' => false, 'message' => 'WPML language details were not created.', 'post_type' => $post_type);
		}

		return array(
			'success'              => true,
			'created'              => true,
			'id'                   => $id,
			'post_type'            => $post_type,
			'element_type'         => $element_type,
			'trid'                 => (int) $updated->trid,
			'language_code'        => (string) $updated->language_code,
			'source_language_code' => (string) ($updated->source_language_code ?? ''),
			'message'              => 'WPML language details created.',
		);
	};

	wp_register_ability(
		'wpml/set-post-language-details',
		array(
			'label'       => 'Set Post Language Details',
			'description' => 'Registers WPML language details for an existing post/CPT item that has no language metadata yet.',
			'category'    => 'site',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array('id', 'language_code'),
				'properties' => array(
					'id'                   => array('type' => 'integer'),
					'language_code'        => array('type' => 'string'),
					'source_language_code' => array('type' => 'string'),
					'trid'                 => array('type' => 'integer'),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success'              => array('type' => 'boolean'),
					'created'              => array('type' => 'boolean'),
					'id'                   => array('type' => 'integer'),
					'post_type'            => array('type' => 'string'),
					'element_type'         => array('type' => 'string'),
					'trid'                 => array('type' => 'integer'),
					'language_code'        => array('type' => 'string'),
					'source_language_code' => array('type' => 'string'),
					'message'              => array('type' => 'string'),
				),
			),
			'execute_callback' => $set_post_language_details,
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

}
