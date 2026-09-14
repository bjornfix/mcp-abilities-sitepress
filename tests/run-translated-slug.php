<?php
/** Verify that the URL ability preserves native slug resolution and history. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$abilities = array();
$post = (object) array('ID' => 1, 'post_type' => 'page', 'post_name' => 'original');
$deleted = array();
$flushes = 0;
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
function wp_update_post($updates, $error) { $GLOBALS['post']->post_name = $updates['post_name'] . '-2'; return 1; }
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
echo "Native translated slug contract passed\n";
