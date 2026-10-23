<?php
/** Exercise the unchanged installed WPML string model; only its framework boundaries are isolated. */
declare(strict_types=1);
namespace WPML\Container {
	function make($class) {
		if ('WPML_ST_String_Factory' !== $class) { throw new \RuntimeException('Unexpected native dependency.'); }
		return new class {
			public function find_by_id($id) { return new \WPML_ST_String($id, $GLOBALS['wpdb']); }
		};
	}
}
namespace WPML\ST\PackageTranslation {
	class StringRowsCache {
		public static function getRow($id) { return null; }
		public static function hasId($id) { return null; }
		public static function noteFieldUpdate($id, $values) { $GLOBALS['cache_updates'][] = array($id, $values); }
	}
}
namespace WPML\ST {
	class TranslationPauseScope { public static function translatable($codes) { return $codes; } }
}
namespace WPML\Core\SharedKernel\Component\Language\Domain {
	class LanguageCode { public static function isEnglish($code) { return 'en' === $code; } }
}
namespace {
	define('ABSPATH', __DIR__ . '/');
	define('ICL_TM_NOT_TRANSLATED', 0);
	define('ICL_TM_WAITING_FOR_TRANSLATOR', 1);
	define('ICL_TM_IN_PROGRESS', 2);
	define('ICL_TM_NEEDS_UPDATE', 3);
	define('ICL_TM_COMPLETE', 10);
	define('ICL_STRING_TRANSLATION_PARTIAL', 5);
	class WP_Error { public function __construct(public $code, public $message, public $data = null) {} }
	function is_wp_error($value) { return $value instanceof WP_Error; }
	function current_user_can($capability) { return $GLOBALS['caps'][$capability] ?? false; }
	function wp_json_encode($value) { return json_encode($value); }
	function wp_cache_delete($key, $group) { $GLOBALS['cache_deletes'][] = array($key, $group); }
	function do_action($name, ...$arguments) { $GLOBALS['native_actions'][] = $name; }
	function mcp_wpml_site_languages() { return array('active_codes' => array('fr', 'en')); }
	class wpdb {
		public $prefix = 'wp_';
		public $strings = array();
		public $translations = array();
		public $writes = array();
		public $fail_update = false;
		public function prepare($sql, ...$values) {
			$index = 0;
			return preg_replace_callback('/%[ds]/', static function ($match) use ($values, &$index) {
				$value = $values[$index++];
				return '%d' === $match[0] ? (string) (int) $value : "'" . addslashes((string) $value) . "'";
			}, $sql);
		}
		private function id($sql) { preg_match('/(?:string_id|id)\s*=\s*(\d+)/', $sql, $match); return (int) ($match[1] ?? 0); }
		public function get_var($sql) {
			preg_match('/SELECT\s+(\w+)/i', $sql, $match);
			return $this->strings[$this->id($sql)][$match[1]] ?? null;
		}
		public function get_row($sql) { $row = $this->strings[$this->id($sql)] ?? null; return $row ? (object) $row : null; }
		public function get_results($sql) {
			$rows = array_filter($this->translations, fn($row) => $row['string_id'] === $this->id($sql));
			return array_map(static fn($row) => (object) $row, array_values($rows));
		}
		public function update($table, $values, $where) {
			if ($this->fail_update) { return false; }
			if ('wp_icl_strings' !== $table || !isset($this->strings[$where['id']])) { throw new \RuntimeException('Unexpected native write target.'); }
			$this->writes[] = array('table' => $table, 'id' => $where['id'], 'values' => $values);
			$this->strings[$where['id']] = array_replace($this->strings[$where['id']], $values);
			return 1;
		}
	}
	$native_file = getenv('MCP_WPML_STRING_CLASS_FILE');
	if (!$native_file || !is_file($native_file)) { throw new \RuntimeException('Provide the unchanged installed WPML_ST_String source file.'); }
	require $native_file;
	require dirname(__DIR__) . '/includes/string-administration-abilities.php';
	$caps = array('manage_options' => true, 'wpml_manage_string_translation' => true);
	$wpdb = new wpdb();
	$sitepress = new class { public function get_active_languages() { return array('fr' => array(), 'en' => array()); } };
	$cache_updates = $cache_deletes = $native_actions = array();
	$wpdb->strings[21] = array('id' => 21, 'context' => 'admin_texts_fixture_locator', 'gettext_context' => '', 'name' => '[fixture_locator]phone_label', 'language' => 'en', 'value' => 'Téléphone', 'status' => ICL_TM_COMPLETE);
	$wpdb->translations[31] = array('id' => 31, 'string_id' => 21, 'language' => 'fr', 'value' => 'Téléphone', 'status' => ICL_TM_COMPLETE, 'translator_id' => 9, 'translation_service' => 'local', 'mo_string' => "compiled\xff\0text");
	$before_source = $wpdb->strings[21];
	$before_rows = $wpdb->translations;
	$request = array('id' => 21, 'domain' => 'admin_texts_fixture_locator', 'include_option_strings' => true, 'source_language' => 'fr');
	$checks = 0;
	function check($pass, $message) { ++$GLOBALS['checks']; if (!$pass) { throw new \RuntimeException($message); } echo 'PASS ' . $message . "\n"; }
	$preview = mcp_wpml_update_string_source_language($request);
	check(!is_wp_error($preview) && $preview['success'] && !$preview['applied'] && !$wpdb->writes, 'native preview makes no write');
	check($before_source === $wpdb->strings[21] && $before_rows === $wpdb->translations, 'preview preserves all source and translation fields');
	$save = $request + array('confirm' => true, 'expected_source_hash' => $preview['before']['source_hash'], 'expected_translations_hash' => $preview['translations_hash']);
	$wpdb->translations[31]['translator_id'] = 10;
	check(is_wp_error(mcp_wpml_update_string_source_language($save)) && !$wpdb->writes, 'full native translation metadata is included in the stale guard');
	$wpdb->translations = $before_rows;
	foreach (array(ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_STRING_TRANSLATION_PARTIAL) as $status) {
		$wpdb->translations[31]['status'] = $status;
		check(is_wp_error(mcp_wpml_update_string_source_language($request)) && !$wpdb->writes, 'active or partial translation work blocks source correction');
	}
	$wpdb->translations = $before_rows;
	$caps['manage_options'] = false;
	check(is_wp_error(mcp_wpml_update_string_source_language($save)) && !$wpdb->writes, 'native administrator permission remains required');
	$caps['manage_options'] = true; $caps['wpml_manage_string_translation'] = false;
	check(is_wp_error(mcp_wpml_update_string_source_language($save)) && !$wpdb->writes, 'native string-management permission remains required');
	$caps['wpml_manage_string_translation'] = true;
	$wrong = $request; $wrong['include_option_strings'] = false;
	check(is_wp_error(mcp_wpml_update_string_source_language($wrong)) && !$wpdb->writes, 'registered option scope must remain explicit');
	$wrong = $request; $wrong['domain'] = 'different-domain';
	check(is_wp_error(mcp_wpml_update_string_source_language($wrong)) && !$wpdb->writes, 'selected native string domain identity is enforced');
	$wrong = $request; $wrong['source_language'] = 'de';
	check(is_wp_error(mcp_wpml_update_string_source_language($wrong)) && !$wpdb->writes, 'only an active source language can be selected');
	$result = mcp_wpml_update_string_source_language($save);
	check(!is_wp_error($result) && $result['success'] && $result['applied'], 'unchanged installed native setter and fresh readback succeed');
	$expected = $before_source; $expected['language'] = 'fr'; $expected['status'] = ICL_TM_NOT_TRANSLATED;
	check($expected === $wpdb->strings[21], 'native source language and aggregate status change without altering identity or text');
	check($before_rows === $wpdb->translations, 'all native translation fields including binary compiled text are preserved');
	check(array('language', 'status') === array_map(static fn($write) => array_key_first($write['values']), $wpdb->writes), 'actual native storage writes only its language and aggregate status');
	check(array(md5('admin_texts_fixture_locator_[fixture_locator]phone_label'), 'wpml-string-translation') === $cache_deletes[0], 'native selected-string cache invalidation is retained');
	$preview = mcp_wpml_update_string_source_language($request);
	$again = $request + array('confirm' => true, 'expected_source_hash' => $preview['before']['source_hash'], 'expected_translations_hash' => $preview['translations_hash']);
	$count = count($wpdb->writes); $result = mcp_wpml_update_string_source_language($again);
	check($result['success'] && $result['unchanged'] && count($wpdb->writes) === $count, 'matching source language is a read-only terminal result');
	$wpdb->strings[21] = $before_source; $wpdb->fail_update = true;
	$result = mcp_wpml_update_string_source_language($save);
	check(!is_wp_error($result) && !$result['success'] && $result['applied'], 'native failed storage is detected by independent source readback');
	check($before_source === $wpdb->strings[21] && $before_rows === $wpdb->translations, 'failed native storage does not falsify preserved state');
	echo $checks . ' unchanged native source-language model contracts passed.' . "\n";
}
