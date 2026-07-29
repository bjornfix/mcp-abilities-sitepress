<?php
/**
 * Elementor gallery media audit and repair abilities.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function mcp_wpml_register_elementor_media_abilities(): void {
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
		$caption_language = isset($input['caption_language']) ? sanitize_key((string) $input['caption_language']) : 'en';
		$source_language = isset($input['source_language']) ? sanitize_key((string) $input['source_language']) : mcp_wpml_default_lang();
		$source_language_markers = isset($input['source_language_markers']) && is_array($input['source_language_markers']) ? $input['source_language_markers'] : array();

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
			if ($caption_language === $lang && '' !== $source_language) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML media translation lookup.
				$translated = apply_filters('wpml_object_id', $attachment_id, 'attachment', false, $source_language);
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

			if ($caption_audit && $caption_language === $lang && !empty($source_language_markers)) {
				$combined = $title . "\n" . $caption . "\n" . $description;
				if (mcp_wpml_text_has_source_language_markers($combined, $source_language_markers)) {
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
			'description' => 'Find published Elementor galleries, optionally regenerate gallery thumbnails, and report captions containing caller-supplied source-language markers.',
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
					'caption_language' => array('type' => 'string', 'default' => 'en'),
					'source_language' => array('type' => 'string', 'description' => 'WPML source language code used to locate matching media.'),
					'source_language_markers' => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Exact source-language words or phrases to flag in target captions.'),
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
