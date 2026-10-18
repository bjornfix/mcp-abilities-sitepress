<?php
/** Verify that the URL ability preserves native slug resolution and history. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$abilities = array();
$post = (object) array('ID' => 1, 'post_type' => 'page', 'post_name' => 'original');
$deleted = array();
$flushes = 0;
$sibling = array('post_content' => 'Reviewed English body', '_elementor_data' => 'English native gallery');
$guard_ids = array(2);
$guard_calls = array();
$fail_update = false;
function mcp_wpml_elementor_translation_sibling_post_ids(...$args) { return array(2); }
function mcp_expose_capture_translation_sibling_state($id) {
 return array('sibling_ids' => $GLOBALS['guard_ids'], 'fixture' => $GLOBALS['sibling']);
}
function mcp_expose_restore_translation_sibling_state($snapshot) {
 $GLOBALS['sibling'] = $snapshot['fixture'];
 $GLOBALS['guard_calls'][] = 'restore';
 return array('protected_post_ids' => $snapshot['sibling_ids']);
}
function mcp_expose_schedule_translation_sibling_state_restore($snapshot, $id, $fields) {
 $GLOBALS['guard_calls'][] = 'schedule';
 return true;
}
$wpdb = new class {
 public $posts = 'posts';
 public $writes = 0;
 public function update($table, $data, ...$args) { ++$this->writes; $GLOBALS['post']->post_name = $data['post_name']; }
};
function wp_register_ability($name, $args) { $GLOBALS['abilities'][$name] = $args; }
function sanitize_key($value) { return $value; }
function sanitize_title($value) { return $value; }
function get_post($id) { return clone $GLOBALS['post']; }
function current_user_can(...$args) { return true; }
function mcp_wpml_lang_details(...$args) { return (object) array('language_code' => 'fr'); }
function mcp_wpml_with_language($lang, $callback) { return $callback(); }
function get_permalink($id) { return 'https://example.com/' . $GLOBALS['post']->post_name . '/'; }
function wp_slash($value) { return $value; }
function wp_update_post($updates, $error) {
 if (!in_array('schedule', $GLOBALS['guard_calls'], true)) { throw new RuntimeException('Guard was not scheduled before native save.'); }
 $GLOBALS['sibling'] = array('post_content' => 'Unexpected source copy', '_elementor_data' => 'Wrong language gallery');
 if ($GLOBALS['fail_update']) { throw new RuntimeException('Native save failed after sync'); }
 $GLOBALS['post']->post_name = $updates['post_name'] . '-2'; return 1;
}
function is_wp_error($value) { return false; }
function delete_post_meta($id, $key) { $GLOBALS['deleted'][] = $key; }
function clean_post_cache($id) {}
function wp_cache_flush() { ++$GLOBALS['flushes']; }
function flush_rewrite_rules($hard) { ++$GLOBALS['flushes']; }
require dirname(__DIR__) . '/includes/translation-shell-abilities.php';
mcp_wpml_register_translation_shell_abilities();
$result = $abilities['wpml/update-translated-post-url']['execute_callback'](array('id' => 1, 'slug' => 'occupied', 'target_lang' => 'fr'));
if (empty($result['success']) || 'occupied-2' !== $result['after_slug'] || $wpdb->writes || in_array('_wp_old_slug', $deleted, true) || $flushes) {
 fwrite(STDERR, "URL update bypassed native slug resolution, removed history, or flushed unrelated caches\n");
 exit(1);
}
if ('Reviewed English body' !== $sibling['post_content'] || 'English native gallery' !== $sibling['_elementor_data'] || empty($result['translation_guard']['shutdown_restore_scheduled'])) {
 throw new RuntimeException('URL save did not preserve translated content.');
}
$fail_update = true;
try {
 $abilities['wpml/update-translated-post-url']['execute_callback'](array('id' => 1, 'slug' => 'fails', 'target_lang' => 'fr'));
 throw new LogicException('Expected native failure.');
} catch (RuntimeException $error) {
 if ('Native save failed after sync' !== $error->getMessage()) { throw $error; }
}
if ('Reviewed English body' !== $sibling['post_content']) { throw new RuntimeException('Exception escaped sibling restore.'); }
$guard_ids = array();
$guard_calls = array();
$result = $abilities['wpml/update-translated-post-url']['execute_callback'](array('id' => 1, 'slug' => 'unprotected', 'target_lang' => 'fr'));
if ($result['success'] || $guard_calls || 'occupied-2' !== $post->post_name) { throw new RuntimeException('Missing sibling was not refused before native mutation.'); }
echo "Native slug history, uniqueness, sibling language preservation, failure cleanup and missing-guard refusal passed\n";
