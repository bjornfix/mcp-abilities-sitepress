<?php
/**
 * WPML translation integrity, untranslated-content, and redirect cleanup abilities.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_gallery_widget_signature(array $gallery): string {
	$attachment_ids = isset($gallery['attachment_ids']) && is_array($gallery['attachment_ids']) ? $gallery['attachment_ids'] : array();
	$attachment_ids = array_values(array_unique(array_filter(array_map('intval', $attachment_ids))));
	sort($attachment_ids, SORT_NUMERIC);
	return implode(',', $attachment_ids);
}

function mcp_wpml_find_matching_gallery_widget(array $source_gallery, array $target_by_id, array $target_galleries, array &$matched_target_gallery_ids): ?array {
	$source_element_id = (string) ($source_gallery['element_id'] ?? '');
	if ('' !== $source_element_id && isset($target_by_id[$source_element_id])) {
		$matched_target_gallery_ids[$source_element_id] = true;
		return $target_by_id[$source_element_id];
	}

	$source_signature = mcp_wpml_gallery_widget_signature($source_gallery);
	if ('' === $source_signature) {
		return null;
	}

	foreach ($target_galleries as $target_gallery) {
		$target_element_id = (string) ($target_gallery['element_id'] ?? '');
		if ('' !== $target_element_id && !empty($matched_target_gallery_ids[$target_element_id])) {
			continue;
		}
		if ($source_signature !== mcp_wpml_gallery_widget_signature($target_gallery)) {
			continue;
		}
		if ('' !== $target_element_id) {
			$matched_target_gallery_ids[$target_element_id] = true;
		}
		return $target_gallery;
	}

	return null;
}

function mcp_wpml_register_translation_integrity_abilities(): void {
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
				$target_galleries = mcp_wpml_gallery_widgets_for_post((int) $target_id, (string) $target->post_type, !empty($source_galleries));
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
					$matched_target_gallery_ids = array();
					foreach ($source_by_id as $element_id => $source_gallery) {
						$target_gallery = mcp_wpml_find_matching_gallery_widget($source_gallery, $target_by_id, $target_galleries, $matched_target_gallery_ids);
						if (null === $target_gallery) {
							$issues[] = array(
								'reason' => 'gallery_missing_in_target',
								'element_id' => $element_id,
								'source_count' => (int) $source_gallery['count'],
							);
							continue;
						}
						if ((int) $source_gallery['count'] !== (int) $target_gallery['count']) {
							$issues[] = array(
								'reason' => 'gallery_item_count_mismatch',
								'element_id' => $element_id,
								'target_element_id' => (string) ($target_gallery['element_id'] ?? ''),
								'source_count' => (int) $source_gallery['count'],
								'target_count' => (int) $target_gallery['count'],
							);
						}
					}
					foreach ($target_by_id as $element_id => $target_gallery) {
						if (!empty($target_gallery['single_image_equivalent'])) {
							continue;
						}
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

}
