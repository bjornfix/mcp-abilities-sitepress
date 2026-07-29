<?php
/**
 * WPML translation status, shell creation, URL, and Elementor asset abilities.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_register_translation_shell_abilities(): void {
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
									$value = preg_replace('#https://[a-z]{2}(?:-[a-z]{2})?\.trustpilot\.com/#i', 'https://www.trustpilot.com/', (string) $value);
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

}
