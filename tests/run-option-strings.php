<?php
/** Registered option-string workflows use the same selected native APIs as content strings. */
namespace WPML\Container {
 function make($class) { return new \OptionStringFactory(); }
}
namespace {
 define('ABSPATH', __DIR__ . '/');
 define('ICL_TM_WAITING_FOR_TRANSLATOR', 1);
 define('ICL_TM_IN_PROGRESS', 2);
 define('ICL_TM_COMPLETE', 10);
 define('ICL_TM_NOT_TRANSLATED', 0);
 class WP_Error { public function __construct(public $code, public $message) {} }
 function is_wp_error($v) { return $v instanceof WP_Error; }
 function current_user_can($cap) { return $GLOBALS['caps'][$cap] ?? false; }
 function wp_json_encode($v) { return json_encode($v); }
 function wp_create_nonce($action) { return 'native-test-nonce'; }
 function mcp_wpml_site_languages() { return array('active_codes' => array('nb', 'en')); }
 function mcp_wpml_register_native_admin_definitions($definitions) { $GLOBALS['definitions'] = $definitions; }
 class WP_REST_Request {
  private $params = array();
  public function __construct($method, $route) { if ($method !== 'GET' || $route !== '/wpml/st/v1/strings') { throw new \RuntimeException('Unexpected native inventory route.'); } }
  public function set_header($name, $value) { if ($name !== 'X-WP-Nonce' || $value !== 'native-test-nonce') { throw new \RuntimeException('Native nonce missing.'); } }
  public function set_param($key, $value) { $this->params[$key] = $value; }
  public function get_param($key) { return $this->params[$key] ?? null; }
 }
 function rest_do_request($request) {
  ++$GLOBALS['rest_calls'];
  return new class($request) {
   public function __construct(private $request) {}
   public function get_status() { return $GLOBALS['rest_denied'] ? 403 : 200; }
   public function get_data() { return array(array('id' => 21, 'domain' => $this->request->get_param('domain'), 'name' => '[plugin_locator][phone_label]', 'language' => 'nb', 'title' => 'Telefon')); }
  };
 }
 class OptionStringFactory { public function find_by_id($id) { return new OptionString($id); } }
 class OptionString {
  public function __construct(private $id) {}
  public function exists() { return $this->id === 21; }
  public function get_context() { return 'admin_texts_plugin_locator'; }
  public function get_gettext_context() { return ''; }
  public function get_name() { return '[plugin_locator][phone_label]'; }
  public function get_language() { return $GLOBALS['source_language'] ?? 'nb'; }
  public function set_language($language) { ++$GLOBALS['language_writes']; $GLOBALS['source_language'] = $language; }
  public function get_value() { return 'Telefon'; }
  public function get_translations() { return $GLOBALS['translations']; }
  public function normalize_line_breaks($text) { return $text; }
 }
 function icl_add_string_translation($id, $language, $value, $status) {
  ++$GLOBALS['writes']; $GLOBALS['translations'][$language] = compact('language', 'value', 'status'); return 31;
 }
 require dirname(__DIR__) . '/includes/string-administration-abilities.php';
 mcp_wpml_register_string_administration_abilities();
 $caps = array('manage_options' => true, 'wpml_manage_string_translation' => true);
 $rest_calls = $writes = 0; $translations = array(); $rest_denied = false;
 $count = 0;
 function check($ok, $label) { ++$GLOBALS['count']; if (!$ok) { throw new \RuntimeException($label); } echo 'PASS ' . $label . "\n"; }
 $input = array('domain' => 'admin_texts_plugin_locator', 'include_option_strings' => true);
 check(is_wp_error(mcp_wpml_list_strings(array('domain' => $input['domain']))) && 0 === $rest_calls, 'registered option domain still requires explicit selection');
 $caps['manage_options'] = false;
 check(is_wp_error(mcp_wpml_list_strings($input)) && 0 === $rest_calls, 'option strings require native administrator permission');
 $caps['manage_options'] = true;
 $caps['wpml_manage_string_translation'] = false;
 check(is_wp_error(mcp_wpml_list_strings($input)) && 0 === $rest_calls, 'native string-management permission remains required');
 $caps['wpml_manage_string_translation'] = true;
 $result = mcp_wpml_list_strings($input);
 check($result['success'] && $result['returned_count'] === 1 && $result['strings'][0]['source_value'] === 'Telefon', 'explicit selected domain is read through native inventory');
 $rest_denied = true;
 check(is_wp_error(mcp_wpml_list_strings($input)), 'native inventory denial is preserved without an alternative query');
 $rest_denied = false;
 check(is_wp_error(mcp_wpml_get_string($input + array('id' => 22))), 'exact native string identity is required');
 $request = $input + array('id' => 21, 'target_language' => 'en', 'value' => 'Phone', 'state' => 'complete');
 $preview = mcp_wpml_update_string_translation($request);
 check($preview['success'] && !$preview['applied'] && $writes === 0, 'preview reads selected registered source without changing it');
 $save = $request + array('confirm' => true, 'expected_source_hash' => $preview['before']['source_hash'], 'expected_target_hash' => $preview['target_hash']);
 $stale = $save; $stale['expected_target_hash'] = str_repeat('0', 64);
 check(is_wp_error(mcp_wpml_update_string_translation($stale)) && $writes === 0, 'reviewed target hash is enforced for registered option strings');
 $saved = mcp_wpml_update_string_translation($save);
 check($saved['success'] && $writes === 1 && $saved['after']['translations']['en']['value'] === 'Phone' && $saved['after']['source']['value'] === 'Telefon', 'native target writer preserves original string and verifies saved translation');
 $translations['en']['status'] = ICL_TM_IN_PROGRESS;
 check(is_wp_error(mcp_wpml_update_string_translation($request)) && $writes === 1, 'active option-string translation job is preserved');
 check(count($definitions) === 4 && isset($definitions['get-string'][3]['include_option_strings']), 'native string abilities expose explicit option-string selection and source-language correction');
 $translations['en']['status'] = ICL_TM_COMPLETE;
 $source_language = 'en'; $language_writes = 0;
 $language_request = $input + array('id' => 21, 'source_language' => 'nb');
 $language_preview = mcp_wpml_update_string_source_language($language_request);
 check($language_preview['success'] && !$language_preview['applied'] && $language_writes === 0, 'source-language preview preserves value and all translations');
 $language_save = $language_request + array('confirm' => true, 'expected_source_hash' => $language_preview['before']['source_hash'], 'expected_translations_hash' => $language_preview['translations_hash']);
 $stale = $language_save; $stale['expected_translations_hash'] = str_repeat('0',64);
 check(is_wp_error(mcp_wpml_update_string_source_language($stale)) && $language_writes === 0, 'source-language correction rejects a stale translation set');
 $translations['en']['status'] = ICL_TM_IN_PROGRESS;
 check(is_wp_error(mcp_wpml_update_string_source_language($language_request)) && $language_writes === 0, 'source-language correction preserves active translation work');
 $translations['en']['status'] = ICL_TM_COMPLETE;
 $language_result = mcp_wpml_update_string_source_language($language_save);
 check($language_result['success'] && $language_writes === 1 && $language_result['after']['source']['language'] === 'nb' && $language_result['after']['source']['value'] === 'Telefon' && $language_result['after']['translations'] === $language_preview['before']['translations'], 'native language setter changes only source language metadata');
 echo $count . " option-string contracts passed.\n";
}
