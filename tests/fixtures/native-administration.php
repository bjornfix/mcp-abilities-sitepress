<?php
/** Isolated WordPress hooks and unchanged WPML APIs with in-memory storage. */
declare(strict_types=1);
namespace WPML\Container {
	function make($class, $args = array()) {
		if ('WPML_ST_String_Factory' === $class) { return new \WPML_ST_String_Factory($GLOBALS['wpdb']); }
		throw new \RuntimeException('Unexpected dependency in isolated administration test.');
	}
}
namespace WPML\TM\API {
	// Repository collaborator only; the factory and assignment methods below are native.
	class Jobs {
		const SENT_MANUALLY = 1;
		public static function getPreviousJob($id) { return $GLOBALS['fixture_history'][$id] ?? null; }
	}
}
namespace WPML\Setup {
	class Option {
		public static $automatic = false;
		public static function shouldTranslateEverything() { return self::$automatic; }
		public static function getTranslateEverythingDrafts() { return 0; }
		public static function getReviewMode() { return 'review-before-publish'; }
	}
}
namespace {
	$native = getenv('MCP_WPML_NATIVE_DIR');
	$extra = getenv('MCP_WPML_EXTRA_DIR');
	$libraries = getenv('MCP_WPML_LIBRARY_DIR');
	$hooks = getenv('MCP_WPML_HOOKS_DIR');
	$application = getenv('MCP_WPML_STRING_API_DIR');
	$jobs_api = getenv('MCP_WPML_JOBS_API_FILE');
	if (!$native || !$extra || !$libraries || !$hooks || !$application || !$jobs_api) { throw new RuntimeException('Provide native source fixture directories and Jobs API file. No site is used.'); }
	define('ABSPATH', __DIR__ . '/');
	define('ARRAY_A', 'ARRAY_A');
	define('ICL_SITEPRESS_VERSION', '4.7.3');
	// Read scalar constants from unchanged native source, rather than guessing enum values.
	preg_match_all("/define\\(\\s*'(ICL_TM_[A-Z_]+)'\\s*,\\s*(-?\\d+)\\s*\\)/", file_get_contents($native . '/inc/constants.php'), $native_constants, PREG_SET_ORDER);
	foreach ($native_constants as $definition) { define($definition[1], (int) $definition[2]); }
	preg_match("/define\\(\\s*'ICL_STRING_TRANSLATION_PARTIAL'\\s*,\\s*(\\d+)\\s*\\)/", file_get_contents($extra . '/wpml-string-translation/inc/constants.php'), $partial_constant);
	define('ICL_STRING_TRANSLATION_PARTIAL', (int) $partial_constant[1]);
	define('ICL_PRO_TRANSLATION_PICKUP_POLLING', 1);
	require $hooks . '/plugin.php';
	spl_autoload_register(static function ($class) use ($libraries, $application) {
		$maps = array('WPML\\FP\\' => $libraries . '/fp/core/', 'WPML\\Collect\\' => $libraries . '/collect/src/Illuminate/', 'WPML\\LIB\\WP\\' => $libraries . '/wp/classes/', 'WPML\\StringTranslation\\' => $application . '/');
		foreach ($maps as $prefix => $base) {
			if (str_starts_with($class, $prefix)) {
				$part = substr($class, strlen($prefix));
				$file = $base . str_replace('\\', '/', $part) . '.php';
				if ('WPML\\FP\\Str' === $class) { $file = $libraries . '/fp/core/strings_functions.php'; }
				if (is_file($file)) { require_once $file; }
			}
		}
	});
	require $libraries . '/fp/core/functions.php';
	require $libraries . '/core-api/core/Languages.php';
	require $libraries . '/core-api/core/Settings.php';
	preg_match('/const SENT_MANUALLY\s*=\s*(\d+)/', file_get_contents($jobs_api), $manual_origin);
	if ((int) $manual_origin[1] !== \WPML\TM\API\Jobs::SENT_MANUALLY) { throw new RuntimeException('Native manual dispatch constant changed.'); }
	$abilities = $fixture_history = $fixture_jobs = $fixture_posts = $fixture_terms = $fixture_term_languages = $fixture_post_languages = array();
	$fixture_caps = array('manage_options' => true, 'administrator' => true, 'manage_translations' => true, 'wpml_manage_string_translation' => true, 'edit_terms' => true, 'edit_term' => true, 'edit_post' => true);
	$fixture_settings = array('default_language' => 'en', 'hidden_languages' => array('de'), 'languages_order' => array('en', 'fr', 'de'), 'setup_complete' => 1, 'private_registration_value' => 'FIXTURE_SECRET');
	$fixture_langs = array();
	foreach (array('en' => 'English', 'fr' => 'French', 'de' => 'German') as $code => $name) {
		$fixture_langs[$code] = array('code' => $code, 'id' => count($fixture_langs) + 1, 'english_name' => $name, 'default_locale' => $code . '_' . strtoupper($code), 'tag' => $code, 'active' => '1');
	}
	$fixture_writes = $fixture_cache_calls = $fixture_dispatches = $fixture_assignments = $fixture_rest_calls = 0;
	$fixture_fail_setting = $fixture_fail_assignment = $fixture_rest_failure = false;
	$fixture_nonce_denied = false;
	function wp_register_ability($name, $args) { $GLOBALS['abilities'][$name] = $args; }
	function current_user_can($cap, ...$args) { return $GLOBALS['fixture_caps'][$cap] ?? false; }
	function user_can($id, $cap) { return 'translate' === $cap && in_array($id, array(7, 8), true); }
	function wp_json_encode($data) { return json_encode($data); }
	function sanitize_text_field($value) { return trim(strip_tags($value)); }
	function wp_create_nonce($action) { return 'wp_rest' === $action ? 'fixture-current-user-nonce' : ''; }
	function wp_verify_nonce($nonce, $action) { return !$GLOBALS['fixture_nonce_denied'] && 'wp_rest' === $action && 'fixture-current-user-nonce' === $nonce; }
	function is_wp_error($v) { return $v instanceof WP_Error; }
	function __($value, ...$args) { return $value; }
	function register_rest_route(...$args) { throw new RuntimeException('No REST exposure is allowed in the isolated fixture.'); }
	function wp_remote_request(...$args) { throw new RuntimeException('No external network is allowed in the isolated fixture.'); }
	function wp_mail(...$args) { throw new RuntimeException('No external notification is allowed in the isolated fixture.'); }
	function get_term($id, $tax) { $t = $GLOBALS['fixture_terms'][$id] ?? null; return $t && $t->taxonomy === $tax ? $t : null; }
	function get_taxonomy($tax) { return 'category' === $tax ? (object) array('cap' => (object) array('edit_terms' => 'edit_terms')) : null; }
	function get_post($id) { return $GLOBALS['fixture_posts'][$id] ?? null; }
	function clean_term_cache(...$args) {}
	function icl_get_languages_codes() { return array('en', 'fr', 'de'); }
	function esc_sql($value) { return $value; }
	function wp_cache_init() {}
	function icl_cache_clear(...$args) { ++$GLOBALS['fixture_cache_calls']; }
	function wpml_reload_active_languages_setting(...$args) {}
	function wpml_get_setting_filter($default, $name) { return $GLOBALS['sitepress']->get_setting($name, $default); }
	function icl_get_setting($name, $default = false) { return $GLOBALS['sitepress']->get_setting($name, $default); }
	function icl_set_setting($name, $value, ...$args) { return $GLOBALS['sitepress']->set_setting($name, $value, true); }
	function wpml_prepare_in($values, $format = '%s') { return implode(',', array_map(static fn($v) => '%d' === $format ? (string) (int) $v : "'" . addslashes((string) $v) . "'", $values)); }
	function wpml_get_setup_instance() { return $GLOBALS['fixture_setup']; }
	function mcp_wpml_term_lang_details($id, $tax) { $t = get_term($id, $tax); return $t ? $GLOBALS['fixture_term_languages'][$t->term_taxonomy_id] ?? null : null; }
	function mcp_wpml_lang_details($id, $type = '') { return $GLOBALS['fixture_post_languages'][$id] ?? null; }
	function mcp_wpml_element_type_for_taxonomy($tax) { return 'tax_' . $tax; }
	function mcp_wpml_element_type_for_post_type($type) { return 'post_' . $type; }
	class WP_Error { public function __construct(public $code, public $message) {} }
	class WPML_TM_Editors { const ATE = 'ate'; }
	class WPML_TM_ATE_Status { public static $enabled = false; public static function is_enabled() { return self::$enabled; } public static function is_active() { return false; } }
	class WPML_TM_Post_Edit_TM_Editor_Mode { const TM_KEY_GLOBAL_USE_NATIVE = 'use_native'; const TM_KEY_FOR_POST_TYPE_USE_NATIVE = 'use_native_types'; }
	class SitePress {
		public function get_default_language() { return $GLOBALS['fixture_settings']['default_language']; }
		public function get_languages($lang = false, $active = false, ...$args) { return array_filter($GLOBALS['fixture_langs'], static fn($l) => !$active || '1' === $l['active']); }
		public function get_active_languages(...$args) { return array_diff_key($this->get_languages(false, true), array_fill_keys($GLOBALS['fixture_settings']['hidden_languages'], true)); }
		public function get_setting($name, $default = false) { return $GLOBALS['fixture_settings'][$name] ?? $default; }
		public function set_setting($name, $value, ...$args) { if ($GLOBALS['fixture_fail_setting']) { return false; } ++$GLOBALS['fixture_writes']; $GLOBALS['fixture_settings'][$name] = $value; return true; }
		public function set_default_language($code) { return $this->set_setting('default_language', $code, true); }
		public function is_setup_complete() { return true; }
		public function is_translated_taxonomy($tax) { return 'category' === $tax; }
		public function is_translated_post_type($type) { return in_array($type, array('page', 'post'), true); }
	}
	class SitePress_Setup { public static function insert_default_category($code) {} }
	class wpdb {
		public $prefix = 'wp_', $insert_id = 0;
		public $strings = array(11 => array('id' => 11, 'context' => 'fixture-content', 'gettext_context' => '', 'name' => 'welcome', 'language' => 'en', 'value' => "Source\r\nSecond", 'status' => 0), 12 => array('id' => 12, 'context' => 'admin_texts_private', 'gettext_context' => '', 'name' => 'private', 'language' => 'en', 'value' => 'FIXTURE_SECRET', 'status' => 0));
		public $translations = array(), $fail_update = false;
		public function prepare($sql, ...$args) { if (1 === count($args) && is_array($args[0])) { $args = $args[0]; } $index = 0; return preg_replace_callback('/%[sd]/', static function ($m) use ($args, &$index) { $v = $args[$index++]; return '%d' === $m[0] ? (string) (int) $v : "'" . addslashes((string) $v) . "'"; }, $sql); }
		private function id($sql) { preg_match('/(?:string_id|id)\s*=\s*(\d+)/', $sql, $m); return (int) ($m[1] ?? 0); }
		public function get_var($sql) {
			if (str_contains($sql, 'icl_locale_map')) { return false; }
			if (str_contains($sql, 'icl_languages')) { return 'fixture_LOCALE'; }
			$id = $this->id($sql);
			preg_match('/SELECT\s+(\w+)/i', $sql, $m); $field = $m[1] ?? 'id';
			return $this->strings[$id][$field] ?? null;
		}
		public function get_row($sql) {
			if (str_contains($sql, 'icl_translate_job job')) { preg_match('/job\.job_id < (\d+)/', $sql, $match); return $GLOBALS['fixture_history'][(int) $match[1]] ?? null; }
			$id = $this->id($sql);
			if (str_contains($sql, 'icl_string_translations')) {
				preg_match('/language\s*=\s*\x27([^\x27]+)\x27/', $sql, $m);
				foreach ($this->translations as $r) { if ($r['string_id'] === $id && $r['language'] === ($m[1] ?? '')) { return (object) $r; } }
				return null;
			}
			return isset($this->strings[$id]) ? (object) $this->strings[$id] : null;
		}
		public function get_results($sql, $format = null) {
			$id = $this->id($sql);
			$rows = array_values(array_filter($this->translations, static fn($r) => $r['string_id'] === $id));
			return ARRAY_A === $format ? $rows : array_map(static fn($r) => (object) ($r + array('mo_string' => null)), $rows);
		}
		public function insert($table, $data) {
			++$GLOBALS['fixture_writes']; ++$this->insert_id;
			if ('wp_icl_languages' === $table) { $GLOBALS['fixture_langs'][$data['code']] = $data + array('id' => $this->insert_id); $GLOBALS['fixture_langs'][$data['code']]['active'] = (string) $data['active']; }
			if ('wp_icl_string_translations' === $table) { $this->translations[$this->insert_id] = $data + array('id' => $this->insert_id); }
			return true;
		}
		public function update($table, $data, $where) {
			if ($this->fail_update) { return false; }
			++$GLOBALS['fixture_writes'];
			if ('wp_icl_string_translations' === $table) { $this->translations[$where['id']] = array_merge($this->translations[$where['id']], $data); }
			if ('wp_icl_strings' === $table) { $this->strings[$where['id']] = array_merge($this->strings[$where['id']], $data); }
			return true;
		}
		public function query($sql) {
			if (preg_match('/UPDATE wp_icl_languages SET active = ([01]) WHERE code (NOT )?IN \((.+)\)/', $sql, $m)) {
				preg_match_all('/\x27([^\x27]+)\x27/', $m[3], $codes);
				foreach ($GLOBALS['fixture_langs'] as $code => &$language) { if (in_array($code, $codes[1], true) !== !empty($m[2])) { $language['active'] = $m[1]; } }
			}
			++$GLOBALS['fixture_writes']; return true;
		}
	}
	$wpdb = new wpdb(); $sitepress = new SitePress();
	require $native . '/classes/core-abstract-classes/class-wpml-wpdb-user.php';
	require $native . '/classes/core-abstract-classes/class-wpml-wpdb-and-sp-user.php';
	require $native . '/inc/setup/wpml-installation.class.php';
	class FixtureInstallation extends WPML_Installation { public function refresh_active_lang_cache($display_language, $active_only = false, $major_first = false, $order_by = 'english_name') { return $this->sitepress->get_languages($display_language, $active_only); } }
	$fixture_setup = new FixtureInstallation($wpdb, $sitepress);
	require $extra . '/wpml-string-translation/classes/class-wpml-st-string.php';
	require $extra . '/wpml-string-translation/classes/class-wpml-st-string-factory.php';
	/** Load an exact native function without executing unrelated bootstrap hooks. */
	function fixture_native_function($path, $name) {
		$tokens = token_get_all(file_get_contents($path)); $capture = false; $depth = 0; $entered = false; $code = '';
		foreach ($tokens as $i => $token) {
			if (!$capture && is_array($token) && T_FUNCTION === $token[0]) {
				for ($j = $i + 1; $j < count($tokens); ++$j) { if (is_array($tokens[$j]) && T_STRING === $tokens[$j][0]) { $capture = $name === $tokens[$j][1]; break; } if ('(' === $tokens[$j]) { break; } }
			}
			if ($capture) { $text = is_array($token) ? $token[1] : $token; $code .= $text; if ('{' === $token) { ++$depth; $entered = true; } if ('}' === $token && 0 === --$depth && $entered) { eval($code); return; } }
		}
		throw new RuntimeException('Native function unavailable: ' . $name);
	}
	fixture_native_function($extra . '/wpml-string-translation/inc/functions.php', 'icl_add_string_translation');
	fixture_native_function($extra . '/wpml-string-translation/inc/functions.php', 'icl_update_string_status');
	require $extra . '/sitepress-multilingual-cms/vendor/otgs/installer/includes/class-wp-installer-api.php';
	class FixtureInstaller { public function get_site_key($repo) { return 'FIXTURE_SECRET'; } public function menu_url() { return 'https://example.com/wp-admin/plugin-install.php?tab=commercial'; } }
	function WP_Installer() { return new FixtureInstaller(); }
	class WP_REST_Request { public $params = array(), $headers = array(); public function __construct(public $method, public $route) {} public function set_param($k, $v) { $this->params[$k] = $v; } public function get_param($k) { return $this->params[$k] ?? null; } public function set_header($k, $v) { $this->headers[strtolower(str_replace('-', '_', $k))] = $v; } public function get_header($k) { return $this->headers[strtolower(str_replace('-', '_', $k))] ?? null; } }
	class WP_REST_Response { public function __construct(private $data, private $status = 200) {} public function get_data() { return $this->data; } public function get_status() { return $this->status; } }
	interface IWPML_Action { public function add_hooks(); }
	require $native . '/classes/API/REST/class-wpml-rest-base.php';
	require $native . '/classes/API/REST/Target.php';
	require $native . '/classes/API/REST/Adaptor.php';
	require $native . '/classes/API/REST/Base.php';
	require $extra . '/wpml-string-translation/classes/API/rest/Base.php';
	class FixtureStringSettings extends \WPML\StringTranslation\Infrastructure\Setting\Repository\SettingsRepository {
		public function __construct() {}
		public function getLanguageDetails(string $code): array { return array('languageFullName' => 'Fixture', 'languageFlagUrl' => 'https://example.com/flag.png'); }
	}
	class FixtureStringQuery implements \WPML\StringTranslation\Application\StringCore\Query\FindBySearchCriteriaQueryInterface {
		public function execute(\WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchCriteria $criteria) {
			$GLOBALS['fixture_string_criteria'] = $criteria;
			$out = array();
			foreach ($GLOBALS['wpdb']->strings as $id => $row) {
				if ($criteria->getDomain() !== $row['context']) { continue; }
				$out[] = new \WPML\StringTranslation\Application\StringCore\Query\Dto\StringWithTranslationStatusDto($id, $row['language'], $row['context'], $row['gettext_context'], $row['name'], $row['value'], $row['status'], 'optional', 2, 0, 1);
			}
			return array_slice($out, $criteria->getOffset(), $criteria->getLimit());
		}
	}
	$fixture_adaptor = new \WPML\Rest\Adaptor();
	$fixture_string_controller = new \WPML\StringTranslation\UserInterface\RestApi\StringListApiController($fixture_adaptor, new FixtureStringQuery(), new FixtureStringSettings());
	function rest_do_request($request) {
		++$GLOBALS['fixture_rest_calls'];
		if ($GLOBALS['fixture_rest_failure']) { return new WP_REST_Response(array('message' => 'FIXTURE_SECRET'), 403); }
		if ('GET' !== $request->method || '/wpml/st/v1/strings' !== $request->route) { throw new RuntimeException('Unexpected native API request.'); }
		if (!$GLOBALS['fixture_adaptor']->validate_permission($request)) { return new WP_REST_Response(array('message' => 'FIXTURE_SECRET'), 403); }
		$native_rows = $GLOBALS['fixture_string_controller']->get($request);
		foreach ($native_rows as &$row) { $row['private_key'] = 'FIXTURE_SECRET'; }
		return new WP_REST_Response($native_rows);
	}
	$fixture_translators = array((object) array('ID' => 7, 'display_name' => 'Translator One', 'user_email' => 'FIXTURE_SECRET'), (object) array('ID' => 8, 'display_name' => 'Translator Two', 'user_email' => 'FIXTURE_SECRET'));
	$fixture_pairs = array(7 => array('en' => array('fr' => true)), 8 => array('en' => array('fr' => true)));
	class FixtureTranslators { public function get_blog_translators($args) { return $GLOBALS['fixture_translators']; } public function get_language_pairs($id) { return $GLOBALS['fixture_pairs'][$id] ?? array(); } }
	function wpml_tm_load_blog_translators() { return new FixtureTranslators(); }
	require $native . '/classes/jobs/class-wpml-tm-jobs-batch.php';
	require $native . '/classes/jobs/class-wpml-tm-job-entity.php';
	require $native . '/classes/jobs/class-wpml-tm-post-job-entity.php';
	require $native . '/classes/jobs/class-wpml-tm-jobs-search-params.php';
	function fixture_job($source, $id, $status, $owner = 7) {
		$job = new WPML_TM_Post_Job_Entity($id + 1000, 'post', 0, new WPML_TM_Jobs_Batch(1, 'Fixture'), $status, array());
		$job->set_original_element_id($source); $job->set_source_language('en'); $job->set_target_language('fr'); $job->set_translate_job_id($id); $job->set_translation_service('local'); $job->set_editor('none'); $job->set_editor_job_id(0); $job->set_automatic(false); $job->set_needs_update(false); $job->set_translator_id($owner); $job->set_title('Fixture');
		return $job;
	}
	class FixtureJobRepository {
		private function rows($p) { $ids = (array) $p->get_original_element_id(); return array_values(array_filter($GLOBALS['fixture_jobs'], static fn($j) => !$ids || in_array($j->get_original_element_id(), $ids, true))); }
		public function get_count($p) { return count($this->rows($p)); }
		public function get($p) { return array_slice($this->rows($p), (int) $p->get_offset(), $p->get_limit()); }
		public function get_job($rid, $type) { foreach ($GLOBALS['fixture_jobs'] as $job) { if ($rid === $job->get_rid()) { return $job; } } return null; }
	}
	function wpml_tm_get_jobs_repository(...$args) { return new FixtureJobRepository(); }
	class WPML_Translation_Job_Helper { protected function get_tm_setting($path) { return 0; } }
	require $native . '/inc/translation-jobs/jobs/wpml-translation-job.class.php';
	class FixtureTranslationJob extends WPML_Translation_Job {
		public function cancel() { throw new RuntimeException('No queue deletion is allowed.'); }
		public function get_original_element_id() { return $GLOBALS['fixture_jobs'][$this->job_id]->get_original_element_id(); }
		public function to_array() { return array(); }
		public function get_title() { return 'Fixture'; }
		public function get_type() { return 'post'; }
		protected function load_batch_id() { $this->batch_id = 1; }
		protected function load_resultant_element_id() { return 0; }
		protected function load_status() { return $GLOBALS['fixture_jobs'][$this->job_id]->get_status(); }
		protected function load_job_data($id) { $job = $GLOBALS['fixture_jobs'][$id]; return (object) array('translator_id' => $job->get_translator_id(), 'translation_service' => $job->get_translation_service()); }
		protected function save_updated_assignment() { if ($GLOBALS['fixture_fail_assignment']) { return false; } ++$GLOBALS['fixture_assignments']; $GLOBALS['fixture_jobs'][$this->job_id]->set_translator_id((int) $this->basic_data->translator_id); return true; }
	}
	class FixtureRecord { public function __construct(private $id) {} public function trid() { return $GLOBALS['fixture_post_languages'][$this->id]->trid; } public function language_code() { return 'en'; } }
	class FixtureTMRecords { public function wpdb() { return $GLOBALS['wpdb']; } public function icl_translations_by_element_id_and_type_prefix($id, $type) { return new FixtureRecord($id); } }
	class TranslationProxy_Batch { public static function get_generic_batch_name() { return 'Fixture'; } }
	class TranslationManagement {
		const TRANSLATE_ELEMENT_ACTION = 1; const DUPLICATE_ELEMENT_ACTION = 2;
		public function post_md5($post) { return md5(json_encode($post)); }
		public function send_jobs($batch, $type, $from) { if (\WPML\TM\API\Jobs::SENT_MANUALLY !== $from) { throw new RuntimeException('Native dispatch must retain manual origin.'); } ++$GLOBALS['fixture_dispatches']; foreach ($batch->get_elements() as $element) { foreach ($element->get_target_langs() as $target => $action) { $id = 500 + count($GLOBALS['fixture_jobs']); $GLOBALS['fixture_jobs'][$id] = fixture_job($element->get_element_id(), $id, 1, $batch->get_translator($target)); } } }
	}
	$iclTranslationManagement = new TranslationManagement();
	require $native . '/classes/translation-batch/class-wpml-tm-translation-batch-element.php';
	require $native . '/classes/translation-batch/class-wpml-tm-translation-batch.php';
	require $native . '/inc/translation-jobs/collections/class-wpml-abstract-job-collection.php';
	require $native . '/classes/class-wpml-translation-job-factory.php';
	class FixtureNativeJobFactory extends WPML_Translation_Job_Factory {
		public function get_translation_job($id, $all = false, $revisions = 0, $instance = false) { return new FixtureTranslationJob($id, 1); }
		public function job_id_by_trid_and_lang($trid, $lang) { foreach ($GLOBALS['fixture_jobs'] as $job) { if ($GLOBALS['fixture_post_languages'][$job->get_original_element_id()]->trid === $trid && $job->get_target_language() === $lang) { return $job->get_translate_job_id(); } } return null; }
	}
	$fixture_records = new FixtureTMRecords(); $fixture_factory = new FixtureNativeJobFactory($fixture_records);
	function wpml_tm_load_job_factory() { return $GLOBALS['fixture_factory']; }
	fixture_native_function($native . '/inc/wpml-private-actions-tm.php', 'wpml_tm_assign_translation_job');
	add_filter('wpml_get_element_translations', static function ($unused, $trid, $type) {
		$terms = str_starts_with($type, 'tax_'); $map = $terms ? $GLOBALS['fixture_term_languages'] : $GLOBALS['fixture_post_languages']; $out = array();
		foreach ($map as $id => $details) { if ((int) $details->trid === $trid) { $out[$details->language_code] = (object) array('element_id' => $id, 'language_code' => $details->language_code, 'original' => empty($details->source_language_code)); } }
		return $out;
	}, 10, 3);
	add_action('wpml_set_element_language_details', static function ($data) {
		++$GLOBALS['fixture_writes']; $GLOBALS['fixture_term_languages'][$data['element_id']] = (object) array('trid' => $data['trid'] ?: 7000, 'language_code' => $data['language_code'], 'source_language_code' => $data['source_language_code']);
	});
}
