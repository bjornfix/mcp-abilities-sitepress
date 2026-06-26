<?php
/**
 * Read-only WPML language and translation query abilities.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_register_translation_query_abilities(): void {
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
				'code'             => (string) ($language['code'] ?? $code),
				'native_name'      => (string) ($language['native_name'] ?? ''),
				'translated_name'  => (string) ($language['translated_name'] ?? ''),
				'default_locale'   => (string) ($language['default_locale'] ?? ''),
				'language_code'    => (string) ($language['language_code'] ?? ''),
				'default'          => !empty($language['default_locale']) && ((string) ($language['code'] ?? $code) === mcp_wpml_default_lang()),
				'active'           => !empty($language['active']),
				'missing'          => !empty($language['missing']),
				'country_flag_url' => (string) ($language['country_flag_url'] ?? ''),
				'url'              => (string) ($language['url'] ?? ''),
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

	$get_post_translations = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$id = isset($input['id']) ? (int) $input['id'] : 0;
		$include_missing = !array_key_exists('include_missing', $input) || (bool) $input['include_missing'];

		if ($id <= 0) {
			return array('success' => false, 'message' => 'id is required.');
		}

		$post = get_post($id);
		if (!$post) {
			return array('success' => false, 'message' => 'Post not found.');
		}

		$post_type = (string) $post->post_type;
		$element_type = mcp_wpml_element_type_for_post_type($post_type);
		$details = mcp_wpml_lang_details($id, $post_type);
		if (!$details || empty($details->trid)) {
			return array(
				'success' => false,
				'id' => $id,
				'post_type' => $post_type,
				'element_type' => $element_type,
				'message' => 'Could not resolve WPML translation group.',
			);
		}

		$trid = (int) $details->trid;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
		$translations_raw = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML plugin.
			'wpml_get_element_translations',
			null,
			$trid,
			$element_type
		);
		$translations_raw = is_array($translations_raw) ? $translations_raw : array();
		$translations = array();
		$by_language = array();

		foreach ($translations_raw as $language_code => $translation) {
			$translation = is_object($translation) ? $translation : (object) (is_array($translation) ? $translation : array());
			$translation_id = isset($translation->element_id) ? (int) $translation->element_id : 0;
			$language_code = isset($translation->language_code) && '' !== (string) $translation->language_code
				? (string) $translation->language_code
				: (string) $language_code;
			$translation_post = $translation_id > 0 ? get_post($translation_id) : null;
			$translation_status = $translation_post ? (string) $translation_post->post_status : '';
			$row = array(
				'language_code' => $language_code,
				'id' => $translation_id,
				'post_type' => $translation_post ? (string) $translation_post->post_type : '',
				'post_status' => $translation_status,
				'title' => $translation_post ? (string) get_the_title($translation_id) : '',
				'slug' => $translation_post ? (string) $translation_post->post_name : '',
				'link' => $translation_post && 'trash' !== $translation_status ? (string) get_permalink($translation_id) : '',
				'trid' => $trid,
				'source_language_code' => isset($translation->source_language_code) ? (string) $translation->source_language_code : '',
				'is_original' => !empty($translation->original),
				'is_requested' => $translation_id === $id,
				'has_translation' => $translation_id > 0 && null !== $translation_post,
			);
			$translations[] = $row;
			if ('' !== $language_code) {
				$by_language[$language_code] = $row;
			}
		}

		if ($include_missing) {
			foreach (mcp_wpml_get_active_languages(false) as $language_code => $language) {
				if (!is_array($language)) {
					continue;
				}
				$language_code = (string) ($language['code'] ?? $language['language_code'] ?? $language_code);
				if ('' === $language_code || isset($by_language[$language_code])) {
					continue;
				}

				$row = array(
					'language_code' => $language_code,
					'id' => 0,
					'post_type' => '',
					'post_status' => '',
					'title' => '',
					'slug' => '',
					'link' => '',
					'trid' => $trid,
					'source_language_code' => '',
					'is_original' => false,
					'is_requested' => false,
					'has_translation' => false,
				);
				$translations[] = $row;
				$by_language[$language_code] = $row;
			}
		}

		return array(
			'success' => true,
			'id' => $id,
			'post_type' => $post_type,
			'element_type' => $element_type,
			'trid' => $trid,
			'language_code' => (string) ($details->language_code ?? ''),
			'source_language_code' => (string) ($details->source_language_code ?? ''),
			'translations' => $translations,
			'by_language' => $by_language,
			'total' => count($translations),
		);
	};

	wp_register_ability(
		'wpml/get-post-translations',
		array(
			'label' => 'Get Post Translations',
			'description' => 'Returns the WPML translation group for a post, page, or custom post type, including translation IDs, languages, statuses, and links.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'required' => array('id'),
				'properties' => array(
					'id' => array('type' => 'integer'),
					'include_missing' => array('type' => 'boolean', 'default' => true),
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
					'trid' => array('type' => 'integer'),
					'language_code' => array('type' => 'string'),
					'source_language_code' => array('type' => 'string'),
					'translations' => array('type' => 'array'),
					'by_language' => array('type' => 'object'),
					'total' => array('type' => 'integer'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $get_post_translations,
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

	$list_posts = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : '';
		$post_type = isset($input['post_type']) && '' !== (string) $input['post_type'] ? sanitize_key((string) $input['post_type']) : 'post';
		$per_page = isset($input['per_page']) ? max(1, min(100, (int) $input['per_page'])) : 10;
		$page = isset($input['page']) ? max(1, (int) $input['page']) : 1;
		$include_totals = !empty($input['include_totals']);
		$statuses = isset($input['statuses']) && is_array($input['statuses'])
			? array_values(array_filter(array_map('sanitize_key', $input['statuses'])))
			: array(sanitize_key((string) ($input['status'] ?? 'publish')));
		$orderby = isset($input['orderby']) ? sanitize_key((string) $input['orderby']) : 'date';
		$order = isset($input['order']) && 'ASC' === strtoupper((string) $input['order']) ? 'ASC' : 'DESC';
		$search = isset($input['search']) ? sanitize_text_field((string) $input['search']) : '';
		$category_id = isset($input['category_id']) ? (int) $input['category_id'] : 0;
		$author_id = isset($input['author_id']) ? (int) $input['author_id'] : 0;

		if ('' === $target_lang) {
			return array('success' => false, 'message' => 'target_lang is required.');
		}
		if (!post_type_exists($post_type)) {
			return array('success' => false, 'message' => 'Invalid post_type.');
		}
		if (empty($statuses) || in_array('any', $statuses, true)) {
			$statuses = array('publish', 'draft', 'pending', 'private', 'future');
		}

		$allowed_orderby = array('date', 'modified', 'title', 'ID', 'id', 'name', 'slug', 'post_name');
		if (!in_array($orderby, $allowed_orderby, true)) {
			$orderby = 'date';
		}
		if ('id' === $orderby) {
			$orderby = 'ID';
		} elseif ('slug' === $orderby) {
			$orderby = 'name';
		}

		$args = array(
			'post_type' => $post_type,
			'post_status' => $statuses,
			'posts_per_page' => $per_page,
			'paged' => $page,
			'orderby' => $orderby,
			'order' => $order,
			'no_found_rows' => !$include_totals,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'suppress_filters' => false,
		);
		if ('' !== $search) {
			$args['s'] = $search;
		}
		if ($category_id > 0) {
			$args['cat'] = $category_id;
		}
		if ($author_id > 0) {
			$args['author'] = $author_id;
		}

		$result = mcp_wpml_with_language($target_lang, static function () use ($args, $include_totals, $page, $per_page): array {
			$query = new WP_Query($args);
			$posts = array();

			foreach ($query->posts as $post) {
				if (!$post instanceof WP_Post) {
					continue;
				}

				$details = mcp_wpml_lang_details((int) $post->ID, (string) $post->post_type);
				$posts[] = array(
					'id' => (int) $post->ID,
					'title' => (string) $post->post_title,
					'slug' => (string) $post->post_name,
					'status' => (string) $post->post_status,
					'post_type' => (string) $post->post_type,
					'date' => (string) $post->post_date,
					'modified' => (string) $post->post_modified,
					'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
					'link' => (string) get_permalink((int) $post->ID),
					'language_code' => $details ? (string) ($details->language_code ?? '') : '',
					'source_language_code' => $details ? (string) ($details->source_language_code ?? '') : '',
					'trid' => $details && !empty($details->trid) ? (int) $details->trid : 0,
				);
			}

			$returned = count($posts);
			$total = $include_totals ? (int) $query->found_posts : null;
			$total_pages = $include_totals ? (int) $query->max_num_pages : null;
			$has_more = $include_totals
				? $page < (int) $query->max_num_pages
				: $returned === $per_page;

			return array(
				'posts' => $posts,
				'returned' => $returned,
				'has_more' => $has_more,
				'total' => $total,
				'total_pages' => $total_pages,
			);
		});

		if (!is_array($result)) {
			return array('success' => false, 'message' => 'WPML post query failed.');
		}

		return array_merge(
			array(
				'success' => true,
				'target_lang' => $target_lang,
				'post_type' => $post_type,
				'category_id' => $category_id,
			),
			$result
		);
	};

	wp_register_ability(
		'wpml/list-posts',
		array(
			'label' => 'List WPML Posts',
			'description' => 'Lists posts, pages, or custom post types inside an explicit WPML language context with native WordPress query filters.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'required' => array('target_lang'),
				'properties' => array(
					'target_lang' => array('type' => 'string'),
					'post_type' => array('type' => 'string', 'default' => 'post'),
					'status' => array('type' => 'string', 'default' => 'publish'),
					'statuses' => array(
						'type' => 'array',
						'items' => array('type' => 'string'),
					),
					'per_page' => array('type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100),
					'page' => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
					'include_totals' => array('type' => 'boolean', 'default' => false),
					'orderby' => array('type' => 'string', 'default' => 'date'),
					'order' => array('type' => 'string', 'default' => 'DESC'),
					'search' => array('type' => 'string'),
					'category_id' => array('type' => 'integer'),
					'author_id' => array('type' => 'integer'),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'target_lang' => array('type' => 'string'),
					'post_type' => array('type' => 'string'),
					'category_id' => array('type' => 'integer'),
					'posts' => array('type' => 'array'),
					'returned' => array('type' => 'integer'),
					'has_more' => array('type' => 'boolean'),
					'total' => array('type' => array('integer', 'null')),
					'total_pages' => array('type' => array('integer', 'null')),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $list_posts,
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

	$find_translation_candidates = function ($input = array()): array {
		$input = is_array($input) ? $input : array();
		$source_id = isset($input['source_id']) ? (int) $input['source_id'] : 0;
		$target_lang = isset($input['target_lang']) ? sanitize_key((string) $input['target_lang']) : '';
		$include_unassigned = !array_key_exists('include_unassigned', $input) || (bool) $input['include_unassigned'];
		$max_results = isset($input['max_results']) ? max(1, min(50, (int) $input['max_results'])) : 20;
		$statuses = isset($input['statuses']) && is_array($input['statuses'])
			? array_values(array_filter(array_map('sanitize_key', $input['statuses'])))
			: array('publish', 'draft', 'pending', 'private', 'trash');

		if ($source_id <= 0 || '' === $target_lang) {
			return array('success' => false, 'message' => 'source_id and target_lang are required.');
		}

		$source = get_post($source_id);
		if (!$source) {
			return array('success' => false, 'message' => 'Source post not found.');
		}

		$post_type = isset($input['post_type']) && '' !== (string) $input['post_type']
			? sanitize_key((string) $input['post_type'])
			: (string) $source->post_type;
		$search = isset($input['search']) ? sanitize_text_field((string) $input['search']) : '';
		$source_title = (string) get_the_title($source_id);
		$source_slug = (string) $source->post_name;
		if ('' === $search) {
			$search = $source_title;
		}

		$source_details = mcp_wpml_lang_details($source_id, (string) $source->post_type);
		$source_trid = $source_details && !empty($source_details->trid) ? (int) $source_details->trid : 0;
		$existing_target_id = mcp_wpml_target_id_for_post_type($source_id, (string) $source->post_type, $target_lang);
		$candidate_ids = $existing_target_id > 0 ? array($existing_target_id) : array();
		$query_args = array(
			'post_type' => $post_type,
			'post_status' => $statuses,
			'posts_per_page' => 100,
			'orderby' => 'modified',
			'order' => 'DESC',
			'fields' => 'ids',
		);
		if ('' !== $search) {
			$query_args['s'] = $search;
		}

		$search_candidate_ids = mcp_wpml_with_language($target_lang, static function () use ($query_args): array {
			$ids = get_posts($query_args);
			return is_array($ids) ? $ids : array();
		});
		$candidate_ids = array_values(array_unique(array_merge($candidate_ids, array_map('intval', is_array($search_candidate_ids) ? $search_candidate_ids : array()))));

		if ('' !== $source_slug) {
			$slug_matches = mcp_wpml_with_language($target_lang, static function () use ($post_type, $statuses, $source_slug): array {
				$ids = get_posts(array(
					'post_type' => $post_type,
					'post_status' => $statuses,
					'posts_per_page' => 20,
					'post_name__in' => array($source_slug),
					'fields' => 'ids',
				));
				return is_array($ids) ? $ids : array();
			});
			$candidate_ids = array_values(array_unique(array_merge($candidate_ids, array_map('intval', is_array($slug_matches) ? $slug_matches : array()))));
		}

		$candidates = array();
		foreach ($candidate_ids as $candidate_id) {
			if ($candidate_id <= 0 || $candidate_id === $source_id) {
				continue;
			}

			$candidate = get_post($candidate_id);
			if (!$candidate) {
				continue;
			}

			$details = mcp_wpml_lang_details($candidate_id, (string) $candidate->post_type);
			$language_code = $details ? (string) ($details->language_code ?? '') : '';
			if ('' !== $language_code && $language_code !== $target_lang) {
				continue;
			}
			if ('' === $language_code && !$include_unassigned) {
				continue;
			}

			$candidate_title = (string) get_the_title($candidate_id);
			$candidate_slug = (string) $candidate->post_name;
			$score = 0;
			$reasons = array();
			if ($candidate_id === $existing_target_id) {
				$score += 100;
				$reasons[] = 'already_linked_target';
			}
			if ($source_trid > 0 && $details && (int) ($details->trid ?? 0) === $source_trid) {
				$score += 80;
				$reasons[] = 'same_trid';
			}
			if ('' !== $source_slug && $candidate_slug === $source_slug) {
				$score += 40;
				$reasons[] = 'exact_slug_match';
			}
			if ('' !== $source_title && 0 === strcasecmp($candidate_title, $source_title)) {
				$score += 30;
				$reasons[] = 'exact_title_match';
			} elseif ('' !== $search && false !== stripos($candidate_title, $search)) {
				$score += 15;
				$reasons[] = 'title_contains_search';
			}
			if ('trash' === (string) $candidate->post_status) {
				$score += 5;
				$reasons[] = 'trash_candidate';
			}
			if ('' === $language_code) {
				$reasons[] = 'missing_wpml_language_details';
			}

			$candidates[] = array(
				'id' => $candidate_id,
				'post_type' => (string) $candidate->post_type,
				'post_status' => (string) $candidate->post_status,
				'title' => $candidate_title,
				'slug' => $candidate_slug,
				'link' => 'trash' !== (string) $candidate->post_status ? (string) get_permalink($candidate_id) : '',
				'language_code' => $language_code,
				'source_language_code' => $details ? (string) ($details->source_language_code ?? '') : '',
				'trid' => $details && !empty($details->trid) ? (int) $details->trid : 0,
				'is_linked_to_source' => $candidate_id === $existing_target_id || ($source_trid > 0 && $details && (int) ($details->trid ?? 0) === $source_trid),
				'score' => $score,
				'reasons' => $reasons,
			);
		}

		usort($candidates, static function (array $a, array $b): int {
			return ((int) $b['score'] <=> (int) $a['score']) ?: ((int) $b['id'] <=> (int) $a['id']);
		});
		$candidates = array_slice($candidates, 0, $max_results);

		return array(
			'success' => true,
			'source_id' => $source_id,
			'target_lang' => $target_lang,
			'post_type' => $post_type,
			'search' => $search,
			'source_title' => $source_title,
			'source_slug' => $source_slug,
			'source_trid' => $source_trid,
			'existing_target_id' => $existing_target_id,
			'candidates' => $candidates,
			'total' => count($candidates),
		);
	};

	wp_register_ability(
		'wpml/find-translation-candidates',
		array(
			'label' => 'Find Translation Candidates',
			'description' => 'Finds existing target-language published, draft, private, pending, or trashed posts that may be reusable as translations before creating duplicates.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'required' => array('source_id', 'target_lang'),
				'properties' => array(
					'source_id' => array('type' => 'integer'),
					'target_lang' => array('type' => 'string'),
					'post_type' => array('type' => 'string'),
					'search' => array('type' => 'string'),
					'statuses' => array(
						'type' => 'array',
						'items' => array('type' => 'string'),
					),
					'include_unassigned' => array('type' => 'boolean', 'default' => true),
					'max_results' => array('type' => 'integer', 'default' => 20),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array('type' => 'boolean'),
					'source_id' => array('type' => 'integer'),
					'target_lang' => array('type' => 'string'),
					'post_type' => array('type' => 'string'),
					'search' => array('type' => 'string'),
					'source_title' => array('type' => 'string'),
					'source_slug' => array('type' => 'string'),
					'source_trid' => array('type' => 'integer'),
					'existing_target_id' => array('type' => 'integer'),
					'candidates' => array('type' => 'array'),
					'total' => array('type' => 'integer'),
					'message' => array('type' => 'string'),
				),
			),
			'execute_callback' => $find_translation_candidates,
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
}
