<?php
/** Exercise bounded media repair through the registered native ability. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ICL_SITEPRESS_VERSION', 'contract-fixture');
$abilities = array();
$caps = array('upload_files' => true, 'edit_pages' => true, 'edit_post' => true);
$writes = 0;
$cleaned = array();
$race = false;
$root = sys_get_temp_dir() . '/mcp-srcset-' . bin2hex(random_bytes(6));
mkdir($root . '/2020/05', 0700, true);
mkdir($root . '/elementor/thumbs', 0700, true);
foreach (array('2020/05/original.jpg', '2020/05/original-300.jpg', 'elementor/thumbs/crop.jpg', '2020/05/valid.jpg') as $name) {
    file_put_contents($root . '/' . $name, 'unchanged fixture bytes: ' . $name);
}
$fixture = array('file' => '2020/05/original.jpg', 'width' => 700, 'height' => 700,
    'sizes' => array('medium' => array('file' => 'original-300.jpg', 'width' => 300, 'height' => 300),
        'elementor_custom_400x400' => array('file' => 'elementor/thumbs/crop.jpg', 'width' => 400, 'height' => 400, 'mime-type' => 'image/jpeg'),
        'elementor_custom_500x500' => array('file' => 'valid.jpg', 'width' => 500, 'height' => 500)),
    'image_meta' => array('credit' => 'Photographer "A"', 'caption' => 'A\\B\\C', 'custom' => array('opaque' => 'preserve')));
$rows = array($fixture);
function add_action(...$args) {}
function add_filter(...$args) {}
function wp_register_ability($name, $args) { $GLOBALS['abilities'][$name] = $args; }
function current_user_can($cap, ...$args) { return $GLOBALS['caps'][$cap] ?? false; }
function get_post($id) { return 123 === $id ? (object) array('post_type' => 'attachment') : null; }
function wp_attachment_is_image($id) { return 123 === $id; }
function get_post_meta(...$args) { return $GLOBALS['rows']; }
function wp_get_upload_dir() { return array('basedir' => $GLOBALS['root'], 'baseurl' => 'https://example.org/uploads', 'error' => false); }
function get_attached_file(...$args) { return $GLOBALS['root'] . '/2020/05/original.jpg'; }
function maybe_serialize($value) { return serialize($value); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function fixture_unslash($value) { return is_array($value) ? array_map('fixture_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function update_post_meta($id, $key, $value, $previous) {
    if ($GLOBALS['race']) { $GLOBALS['rows'][0]['image_meta']['credit'] = 'Concurrent human correction'; }
    if (array($previous) !== $GLOBALS['rows']) { return false; }
    ++$GLOBALS['writes']; $GLOBALS['rows'] = array(fixture_unslash($value)); return true;
}
function clean_post_cache($id) { $GLOBALS['cleaned'][] = $id; }
function wp_generate_attachment_metadata(...$args) { throw new RuntimeException('Regeneration is forbidden in bounded mode.'); }
function wp_get_attachment_metadata($id) { return $GLOBALS['rows'][0]; }
function trailingslashit($value) { return rtrim($value, '/') . '/'; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function get_the_title($id) { return 'Fixture image'; }
function wp_basename($value) { return basename($value); }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
require dirname(__DIR__) . '/mcp-abilities-sitepress.php';
mcp_wpml_register_abilities();
$ability = $abilities['wpml/repair-elementor-gallery-media'];
$call = $ability['execute_callback'];
$input = array('operation' => 'prune_invalid_srcset_sizes', 'attachment_ids' => array(123), 'size_keys' => array('elementor_custom_400x400'), 'dry_run' => true);
$files_before = array();
foreach (array('2020/05/original.jpg', '2020/05/original-300.jpg', 'elementor/thumbs/crop.jpg', '2020/05/valid.jpg') as $name) { $files_before[$name] = hash_file('sha256', $root . '/' . $name); }
try {
    $dry = $call($input);
    check($dry['success'] && array('elementor_custom_400x400') === $dry['proposed_size_keys'] && 0 === $writes && array($fixture) === $rows && array() === $cleaned, 'Dry run must propose exactly one entry without mutation.');
    $input['dry_run'] = false;
    check(!$call($input)['success'] && 0 === $writes, 'Missing reviewed hash must fail.');
    $input['expected_metadata_hash'] = str_repeat('a', 64);
    check(!$call($input)['success'] && 0 === $writes, 'Stale hash must fail.');
    $input['expected_metadata_hash'] = $dry['expected_metadata_hash'];
    foreach (array('upload_files', 'edit_pages', 'edit_post') as $cap) {
        $caps[$cap] = false;
        check(!$call($input)['success'] && 0 === $writes, 'Capability must be required: ' . $cap);
        $caps[$cap] = true;
    }
    foreach (array(array('attachment_ids' => array(123, 456)), array('attachment_ids' => array(456)), array('page_id' => 123), array('element_id' => 'widget'), array('force' => true), array('size_keys' => array('medium')), array('size_keys' => array('../file'))) as $override) {
        check(!$call(array_replace($input, $override))['success'] && 0 === $writes, 'Ambiguous or unsupported scope must fail.');
    }
    $rows[0]['sizes']['elementor_custom_400x400']['file'] = '../crop.jpg';
    check(!$call(array_replace($input, array('dry_run' => true)))['success'] && 0 === $writes, 'Traversal must fail.');
    $rows = array($fixture);
    rename($root . '/elementor/thumbs/crop.jpg', $root . '/elementor/thumbs/temporarily-missing.jpg');
    check(!$call(array_replace($input, array('dry_run' => true)))['success'] && 0 === $writes, 'Missing global crop must fail.');
    rename($root . '/elementor/thumbs/temporarily-missing.jpg', $root . '/elementor/thumbs/crop.jpg');
    $kept = $call(array_replace($input, array('dry_run' => true, 'size_keys' => array('elementor_custom_500x500'))));
    check($kept['success'] && array() === $kept['proposed_size_keys'] && array('elementor_custom_500x500') === $kept['kept_size_keys'], 'Existing local custom size must stay.');
    $race = true;
    check(!$call($input)['success'] && 0 === $writes && 'Concurrent human correction' === $rows[0]['image_meta']['credit'], 'Concurrent correction must be preserved.');
    $race = false; $rows = array($fixture);
    $done = $call($input);
    $expected = $fixture; unset($expected['sizes']['elementor_custom_400x400']);
    check($done['success'] && $done['metadata_changed'] && $done['verified'] && array($expected) === $rows && 1 === $writes && array(123) === $cleaned, 'Apply must preserve every other raw metadata value, including slashes.');
    check($call($input)['success'] && 1 === $writes, 'Retry must be idempotent.');
    $legacy = $call(array('attachment_ids' => array(123), 'size' => 'medium', 'dry_run' => true));
    check($legacy['success'] && 1 === $writes, 'Legacy default dry run must still work.');
    check(!$call(array('operation' => 'unsupported'))['success'], 'Unknown operation must fail.');
    foreach ($files_before as $name => $digest) { check($digest === hash_file('sha256', $root . '/' . $name), 'File bytes must stay unchanged.'); }
    echo json_encode(array('success' => true, 'registered_abilities' => count($abilities), 'bounded_write_count' => $writes, 'dry_run_permissions_stale_hash_scope_traversal_concurrency_idempotence_and_legacy_verified' => true, 'all_file_bytes_and_other_metadata_preserved' => true), JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    foreach (array_keys($files_before) as $name) { unlink($root . '/' . $name); }
    rmdir($root . '/2020/05'); rmdir($root . '/2020'); rmdir($root . '/elementor/thumbs'); rmdir($root . '/elementor'); rmdir($root);
}
