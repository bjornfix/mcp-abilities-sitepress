<?php
/**
 * Bounded repair of misplaced Elementor thumbnail references.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/** Remove reviewed invalid size entries without regenerating or deleting files. */
function mcp_wpml_prune_invalid_elementor_srcset_sizes(array $input): array {
	$fail = static function (string $message): array {
		return array('success' => false, 'metadata_changed' => false, 'message' => $message);
	};
	if (!current_user_can('upload_files') || !current_user_can('edit_pages')) {
		return $fail('You cannot repair attachment metadata.');
	}
	$ids = $input['attachment_ids'] ?? array();
	$keys = $input['size_keys'] ?? array();
	if (!is_array($ids) || 1 !== count($ids) || !is_int($ids[0]) || $ids[0] < 1
		|| !empty($input['page_id']) || !empty($input['element_id']) || !empty($input['force'])) {
		return $fail('Provide exactly one attachment ID without a page, element or force scope.');
	}
	if (!is_array($keys) || count($keys) < 1 || count($keys) > 10) {
		return $fail('Provide between one and ten explicit Elementor custom size keys.');
	}
	foreach ($keys as $key) {
		if (!is_string($key) || !preg_match('/^elementor_custom_[1-9][0-9]*x[1-9][0-9]*$/D', $key)) {
			return $fail('Only explicit Elementor custom size keys can be repaired.');
		}
	}
	$keys = array_values(array_unique($keys));
	$id = $ids[0];
	$post = get_post($id);
	if (!$post || 'attachment' !== $post->post_type || !current_user_can('edit_post', $id) || !wp_attachment_is_image($id)) {
		return $fail('The image attachment does not exist or is not editable.');
	}
	$rows = get_post_meta($id, '_wp_attachment_metadata', false);
	if (!is_array($rows) || 1 !== count($rows) || !is_array($rows[0]) || !isset($rows[0]['sizes']) || !is_array($rows[0]['sizes'])) {
		return $fail('Expected one image metadata row containing a sizes array.');
	}
	$before = $rows[0];
	$uploads = wp_get_upload_dir();
	$relative = $before['file'] ?? '';
	$file = get_attached_file($id, true);
	if (!empty($uploads['error']) || empty($uploads['basedir']) || !is_string($relative)
		|| !preg_match('#^(?:[A-Za-z0-9_-]+/)*[^/\\\\\x00]+$#D', $relative)
		|| in_array('..', explode('/', $relative), true) || in_array('.', explode('/', $relative), true)
		|| !is_string($file) || !is_file($file)) {
		return $fail('The original image and its upload location could not be verified.');
	}
	$root = realpath($uploads['basedir']);
	$original = realpath($file);
	$expected_original = realpath($uploads['basedir'] . '/' . $relative);
	if (false === $root || false === $original || $original !== $expected_original || 0 !== strpos($original, $root . DIRECTORY_SEPARATOR)) {
		return $fail('The original image is outside its expected upload location.');
	}
	$after = $before;
	$removed = array();
	$kept = array();
	foreach ($keys as $key) {
		if (!array_key_exists($key, $before['sizes'])) {
			continue;
		}
		$entry = $before['sizes'][$key];
		preg_match('/^elementor_custom_([1-9][0-9]*)x([1-9][0-9]*)$/D', $key, $dimensions);
		if (!is_array($entry) || !isset($entry['file'], $entry['width'], $entry['height']) || !is_string($entry['file'])
			|| (int) $entry['width'] !== (int) $dimensions[1] || (int) $entry['height'] !== (int) $dimensions[2]
			|| !preg_match('#^(?:elementor/thumbs/)?[^/\\\\\x00]+$#D', $entry['file'])
			|| in_array(basename($entry['file']), array('.', '..'), true)) {
			return $fail('A selected size has unsupported metadata; nothing was changed.');
		}
		$relative_candidate = dirname($file) . '/' . $entry['file'];
		if (is_file($relative_candidate)) {
			$kept[] = $key;
			continue;
		}
		$global_candidate = $uploads['basedir'] . '/' . $entry['file'];
		$global_real = realpath($global_candidate);
		if (0 !== strpos($entry['file'], 'elementor/thumbs/') || !is_file($global_candidate)
			|| false === $global_real || 0 !== strpos($global_real, $root . DIRECTORY_SEPARATOR . 'elementor' . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR)) {
			return $fail('A selected size is not a verified misplaced Elementor thumbnail; nothing was changed.');
		}
		unset($after['sizes'][$key]);
		$removed[] = $key;
	}
	$hash = hash('sha256', maybe_serialize($before));
	$result = array(
		'success' => true,
		'operation' => 'prune_invalid_srcset_sizes',
		'attachment_id' => $id,
		'dry_run' => !empty($input['dry_run']),
		'proposed_size_keys' => $removed,
		'kept_size_keys' => $kept,
		'removed_size_keys' => array(),
		'expected_metadata_hash' => $hash,
		'metadata_hash_after' => $hash,
		'metadata_changed' => false,
		'verified' => empty($removed),
		'message' => empty($removed) ? 'No invalid selected size entries remain.' : 'Review the proposed size keys and metadata hash before applying.',
	);
	if ($result['dry_run'] || empty($removed)) {
		return $result;
	}
	$expected_hash = $input['expected_metadata_hash'] ?? '';
	if (!is_string($expected_hash) || !preg_match('/^[a-f0-9]{64}$/D', $expected_hash) || !hash_equals($hash, $expected_hash)) {
		return $fail('The reviewed metadata hash is missing or stale; inspect a new dry run.');
	}
	$updated = update_post_meta($id, '_wp_attachment_metadata', wp_slash($after), $before);
	$stored = get_post_meta($id, '_wp_attachment_metadata', false);
	if (!$updated || array($after) !== $stored) {
		return array(
			'success' => false,
			'metadata_changed' => (bool) $updated,
			'verified' => false,
			'message' => 'The conditional metadata update could not be verified. Inspect current metadata before retrying.',
		);
	}
	clean_post_cache($id);
	$result['removed_size_keys'] = $removed;
	$result['metadata_hash_after'] = hash('sha256', maybe_serialize($after));
	$result['metadata_changed'] = true;
	$result['verified'] = true;
	$result['message'] = 'Removed only the reviewed invalid size entries. Image files and other metadata were preserved.';
	return $result;
}
