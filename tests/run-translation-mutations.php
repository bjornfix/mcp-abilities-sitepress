<?php
/** Regression checks through registered mutation callbacks. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$abilities = array();
$posts = array(1 => (object) array('post_type' => 'page'), 2 => (object) array('post_type' => 'page'), 3 => (object) array('post_type' => 'wpcf7_contact_form'));
$details = array(1 => (object) array('trid' => 10, 'language_code' => 'en'));
$editable = array();
$writes = 0;
function wp_register_ability($name, $args) { $GLOBALS['abilities'][$name] = $args; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function current_user_can($cap, ...$args) { return in_array($cap, array('edit_post', 'read_post'), true) ? in_array($args[0], $GLOBALS['editable'], true) : true; }
function sanitize_key($value) { return strtolower($value); }
function sanitize_text_field($value) { return $value; }
function mcp_wpml_element_type_for_post_type($type) { return 'post_' . $type; }
function mcp_wpml_lang_details($id, $type = '') { return $GLOBALS['details'][$id] ?? null; }
function mcp_wpml_get_active_languages($skip = false) { return array('en' => array(), 'fr' => array()); }
function mcp_wpml_configured_languages() { return array('en' => array(), 'fr' => array()); }
function apply_filters($hook, $value, ...$args) {
 if ('wpml_post_language_details' === $hook) { return array('locale' => 'en_GB'); }
 return 'wpml_get_element_translations' === $hook ? ($GLOBALS['translations'] ?? array()) : $value;
}
function mcp_wpml_target_id_for_post_type($id, $type, $lang) { return 0; }
function mcp_wpml_normalize_scalar($value) { return (array) $value; }
function clean_post_cache($id) {}
function do_action(...$args) { ++$GLOBALS['writes']; $GLOBALS['last_action'] = $args; }
function update_post_meta(...$args) { ++$GLOBALS['writes']; }
function wp_kses_post($value) { return $value; }
function sanitize_title($value) { return $value; }
function post_type_exists($type) { return true; }
function wp_update_post(...$args) { ++$GLOBALS['writes']; return 4; }
function wp_insert_post(...$args) { ++$GLOBALS['writes']; $GLOBALS['inserted'] = $args[0]; return 4; }
function is_wp_error($value) { return false; }
function wp_slash($value) { return $value; }
function clean_attachment_cache($id) {}
function get_post_type_object($type) { return (object) array('hierarchical' => false, 'cap' => (object) array('create_posts' => 'edit_pages', 'publish_posts' => 'publish_pages')); }
function get_post_status($id) { return 'draft'; }
function get_permalink($id) { return 'https://example.com/fixture/'; }
function mcp_wpml_target_id($id, $lang) { return 0; }
function get_the_title($id) { return 'Fixture'; }
function get_post_meta($id, $key, $single = true) { return '_elementor_data' === $key ? '[{"id":"widget","settings":{"html":"<div class=\"trustpilot-widget\" data-locale=\"en-GB\"></div>"}}]' : ''; }
function wp_json_encode($value) { return json_encode($value); }
function delete_post_meta(...$args) {}
class WPCF7_ContactForm {
 public static $saved = 0;
 public static function get_instance($id) { return new self(); }
 public function set_properties($properties) {}
 public function save() { ++self::$saved; return 0; }
}
require dirname(__DIR__) . '/includes/translation-mutation-abilities.php';
mcp_wpml_register_translation_mutation_abilities();
require dirname(__DIR__) . '/includes/translation-query-abilities.php';
mcp_wpml_register_translation_query_abilities();
$cases = array(
 'wpml/set-post-language-details' => array('id' => 2, 'language_code' => 'fr'),
 'wpml/link-post-translation' => array('source_id' => 1, 'target_id' => 2, 'target_lang' => 'fr'),
 'wpml/update-contact-form-7-translation-form' => array('target_id' => 3, 'form' => '[text name]'),
);
$failures = array();
foreach (array('wpml/get-element-language-details', 'wpml/get-post-translations') as $name) {
 $posts[1]->post_status = 'draft';
 $result = $abilities[$name]['execute_callback'](array('id' => 1));
 if (!empty($result['success'])) { $failures[] = $name . ' disclosed an inaccessible post'; }
}
foreach ($cases as $name => $input) {
 $writes = 0;
 $result = $abilities[$name]['execute_callback']($input);
 if ($writes || !empty($result['success'])) { $failures[] = $name . ' allowed an inaccessible post'; }
}
require dirname(__DIR__) . '/includes/elementor-media-abilities.php';
mcp_wpml_register_elementor_media_abilities();
$posts[4] = (object) array('post_type' => 'attachment');
$writes = 0;
$result = $abilities['wpml/update-media-captions-batch']['execute_callback'](array('updates' => array(array('id' => 4, 'caption' => 'Changed'))));
if ($writes || !empty($result['success'])) { $failures[] = 'caption batch allowed an inaccessible attachment'; }
require dirname(__DIR__) . '/includes/translation-link-audit-abilities.php';
mcp_wpml_register_translation_link_audit_abilities();
$audit_input = array('id' => 1, 'target_lang' => 'fr', 'include_content' => false, 'include_elementor' => false);
$result = $abilities['wpml/audit-translated-links']['execute_callback']($audit_input);
if (!empty($result['success'])) { $failures[] = 'link audit allowed an inaccessible post'; }
$result = $abilities['wpml/audit-translated-links-batch']['execute_callback'](array_merge($audit_input, array('ids' => array(1))));
if (!empty($result['success'])) { $failures[] = 'batch audit reported success despite a failed item'; }
require dirname(__DIR__) . '/includes/translation-shell-abilities.php';
mcp_wpml_register_translation_shell_abilities();
foreach (array('post_title' => 'Fixture', 'post_content' => '', 'post_excerpt' => '', 'post_parent' => 0, 'menu_order' => 0) as $key => $value) { $posts[1]->$key = $value; }
foreach (array('wpml/ensure-post-translation', 'wpml/ensure-page-translation') as $name) {
 $writes = 0;
 $result = $abilities[$name]['execute_callback'](array('source_id' => 1, 'target_lang' => 'fr', 'copy_content' => false, 'copy_excerpt' => false, 'copy_elementor' => false, 'copy_featured_image' => false, 'copy_taxonomies' => false, 'copy_selected_meta' => false));
 if ($writes || !empty($result['success'])) { $failures[] = $name . ' copied an inaccessible source'; }
}
$editable = array(1, 2);
$posts[1]->ID = 1;
$result = $abilities['wpml/audit-elementor-language-assets']['execute_callback'](array('ids' => array(1), 'target_lang' => 'en'));
if (!empty($result['issue_count'])) { $failures[] = 'language audit treated British English as US English'; }
foreach (array('en', 'invalid') as $language) {
 $writes = 0;
 $input = $cases['wpml/link-post-translation'];
 $input['target_lang'] = $language;
 $abilities['wpml/link-post-translation']['execute_callback']($input);
 if ($writes) { $failures[] = 'link wrote an invalid or source language'; }
}
$translations = array('fr' => (object) array('element_id' => 99, 'language_code' => 'fr'));
$writes = 0;
$abilities['wpml/link-post-translation']['execute_callback']($cases['wpml/link-post-translation']);
if ($writes) { $failures[] = 'link overwrote an occupied translation language'; }
$translations = array();
$abilities['wpml/set-post-language-details']['execute_callback']($cases['wpml/set-post-language-details']);
if (!array_key_exists('trid', $last_action[1]) || false !== $last_action[1]['trid'] || null !== $last_action[1]['source_language_code']) { $failures[] = 'new original omitted the native new-group and original-language values'; }
$shell_input = array('source_id' => 1, 'target_lang' => 'fr', 'target_status' => 'publish', 'copy_content' => false, 'copy_excerpt' => false, 'copy_elementor' => false, 'copy_featured_image' => false, 'copy_taxonomies' => false, 'copy_selected_meta' => false);
$result = $abilities['wpml/ensure-post-translation']['execute_callback']($shell_input);
if (!empty($result['success']) || 'draft' !== ($inserted['post_status'] ?? '')) { $failures[] = 'shell published or claimed a translation without WPML readback'; }
$writes = 0;
$shell_input['target_lang'] = 'en';
$result = $abilities['wpml/ensure-post-translation']['execute_callback']($shell_input);
if ($writes || !empty($result['created'])) { $failures[] = 'shell duplicated its source language'; }
$result = $abilities['wpml/link-post-translation']['execute_callback']($cases['wpml/link-post-translation']);
if (!empty($result['success'])) { $failures[] = 'link reported success when WPML did not persist the link'; }
$editable[] = 3;
$result = $abilities['wpml/update-contact-form-7-translation-form']['execute_callback']($cases['wpml/update-contact-form-7-translation-form']);
if (!empty($result['success']) || 1 !== WPCF7_ContactForm::$saved) { $failures[] = 'form bypassed native save or ignored a save failure'; }
if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo "Mutation permission and readback checks passed\n";
