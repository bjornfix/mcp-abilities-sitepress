<?php
/**
 * WPML translated-link and translation coverage audit abilities.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_register_translation_link_audit_abilities(): void {
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

}
