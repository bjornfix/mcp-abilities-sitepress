<?php
/** Regression contracts through the registered WPML administration Interface. */
declare(strict_types=1);
namespace WPML\Setup {
	class Option {
		const HOLD_FOR_REVIEW = 'before-publish';
		public static function shouldTranslateEverything() { return $GLOBALS['state']['automatic']; }
		public static function getTranslateEverythingDrafts() { return $GLOBALS['state']['drafts']; }
		public static function getReviewMode() { return $GLOBALS['state']['review']; }
		public static function setTranslateEverything($value) { $GLOBALS['state']['automatic'] = $value; ++$GLOBALS['writes']; }
		public static function setTranslateEverythingDrafts($value) { if ($GLOBALS['fail_drafts']) { throw new \RuntimeException('SECRET'); } $GLOBALS['state']['drafts'] = (bool) $value; ++$GLOBALS['writes']; }
		public static function setReviewMode($value) { $GLOBALS['state']['review'] = $value; ++$GLOBALS['writes']; }
		public static function setTranslationMode($value) { $GLOBALS['state']['translation_mode'] = $value; ++$GLOBALS['writes']; }
	}
}
namespace WPML\TM\API {
	class Jobs { public static function getPreviousJob($id) { return null; } }
}
namespace WPML\StringTranslation\Application\Setting\Repository {
	interface SettingsRepositoryInterface { const AUTOREGISTER_STRINGS_TYPE_DISABLED = 2; }
}
namespace WPML\StringTranslation\Infrastructure\Setting\Repository {
	class UrlRepository { public function __construct($sitepress) {} }
	class SettingsRepository {
		public function __construct(...$args) {}
		public function setAutoregisterStringsTypeSetting($value) { $GLOBALS['state']['st']['autoregister_strings'] = $value; ++$GLOBALS['writes']; }
		public function setShouldRegisterBackendStringsSetting($value) { $GLOBALS['state']['st']['autoregister_strings_should_register_backend_strings'] = $value; ++$GLOBALS['writes']; }
	}
}
namespace WPML\API {
	class Settings {
		public static function assoc($name, $key, $value) { $GLOBALS['state'][$name][$key] = $value; ++$GLOBALS['writes']; if ($GLOBALS['fail_editor']) { throw new \RuntimeException('SECRET'); } }
		public static function setAndSave($name, $value) { $GLOBALS['state'][$name] = $value; ++$GLOBALS['writes']; }
	}
}
namespace {
	define('ABSPATH', __DIR__ . '/');
	define('ICL_PRO_TRANSLATION_PICKUP_POLLING', 1);
	define('ICL_TM_TMETHOD_EDITOR', 1);
	define('ICL_TM_WAITING_FOR_TRANSLATOR', 1);
	define('ICL_TM_IN_PROGRESS', 2);
	define('ICL_TM_TRANSLATION_READY_TO_DOWNLOAD', 3);
	define('ICL_TM_ATE_NEEDS_RETRY', 40);
	define('ICL_TM_COMPLETE', 10);
	$abilities = array(); $admin = true; $writes = 0; $fail_drafts = $fail_editor = false;
	$state = array('automatic' => true, 'drafts' => true, 'review' => 'no-review', 'translation_pickup_method' => 0,
		'translation-management' => array('doc_translation_method' => 'ATE', 'native' => false, 'types' => array(), 'unknown' => array('keep' => 'yes')),
		'st' => array('autoregister_strings' => 2), 'unknown_top_level' => array('private' => 'SECRET'));
	class WP_Error { public function __construct(public $code, public $message, public $data = null) {} }
	function is_wp_error($r) { return $r instanceof WP_Error; }
	function wp_json_encode($v) { return json_encode($v); }
	function current_user_can($cap) { return $GLOBALS['admin']; }
	function wp_register_ability($name, $args) { $GLOBALS['abilities'][$name] = $args; }
	function icl_cache_clear() {}
	function do_action(...$args) { if (array('wpml_st_update_settings', 'deleteCache') !== $args) { throw new RuntimeException('Unexpected action'); } }
	class WPML_TM_Editors { const ATE = 'ATE'; }
	class WPML_TM_Post_Edit_TM_Editor_Mode { const TM_KEY_GLOBAL_USE_NATIVE = 'native'; const TM_KEY_FOR_POST_TYPE_USE_NATIVE = 'types'; }
	class WPML_TM_ATE_Status { public static function is_enabled() { return WPML_TM_Editors::ATE === $GLOBALS['state']['translation-management']['doc_translation_method']; } public static function is_active() { return true; } }
	class WPML_TM_Jobs_Search_Params { public function __call($name, $args) {} }
	class FixtureEmptyRepository {
		public function get_count($p) { return !empty($GLOBALS['unfinished_ate']) ? 1 : 0; }
		public function get($p) { return array(new WPML_TM_Post_Job_Entity()); }
	}
	class WPML_TM_Post_Job_Entity {
		public function __call($name, $args) { return array('get_rid' => 81, 'get_type' => 'post', 'get_original_element_id' => 82, 'get_title' => 'Fixture', 'get_source_language' => 'en', 'get_target_language' => 'fr', 'get_status' => 2, 'does_need_update' => false, 'get_translation_service' => 'local', 'get_translate_job_id' => 83, 'is_ate_editor' => true, 'get_editor_job_id' => 84, 'is_automatic' => true)[$name]; }
	}
	function wpml_tm_get_jobs_repository(...$args) { if (!empty($GLOBALS['fail_inventory'])) { throw new RuntimeException('SECRET'); } return new FixtureEmptyRepository(); }
	$sitepress = new class { public function get_setting($name, $default = false) { return $GLOBALS['state'][$name] ?? $default; } };
	$root = getenv('MCP_WPML_SOURCE_DIR') ?: dirname(__DIR__);
	require $root . '/includes/administration-abilities.php';
	require $root . '/includes/site-administration-abilities.php';
	mcp_wpml_register_administration_abilities();
	mcp_wpml_register_site_administration_abilities();
	$checks = 0;
	function check($v, $message) { ++$GLOBALS['checks']; if (!$v) { throw new RuntimeException($message); } }
	function run($slug, $input = array()) { $a = $GLOBALS['abilities']['wpml/' . $slug]; if (!$a['permission_callback']()) { return new WP_Error('forbidden', 'Denied'); } $r = ($a['execute_callback'])($input); check(!str_contains(json_encode($r), 'SECRET'), 'Secret returned'); return $r; }
	if ('--automation-preservation-only' === ($argv[1] ?? '')) {
		run('enable-manual-translation', array('confirm' => true));
		check('ATE' === $state['translation-management']['doc_translation_method'] && WPML_TM_ATE_Status::is_enabled(), 'Automatic shutdown changed manual ATE');
		echo "PASS: automation shutdown preserves manual ATE\n";
		exit;
	}
	$initial = $state;
	check(is_wp_error(run('enable-manual-translation', array('confirm' => 1))) && !$writes, 'Strict confirmation required');
	$preview = run('get-translation-controls');
	check(is_wp_error(run('enable-manual-translation', array('confirm' => true, 'expected_controls_hash' => str_repeat('0', 64)))) && !$writes, 'Stale controls mutated');
	$r = run('enable-manual-translation', array('confirm' => true, 'expected_controls_hash' => $preview['controls_hash']));
	check($r['success'] && $r['after']['ate_enabled'] && 'ATE' === $r['after']['document_editor'], 'Automatic shutdown changed manual ATE');
	check(array('translate_everything', 'translate_drafts') === array_keys($r['changes']), 'Shutdown changed unrelated controls');
	$expected = $initial; $expected['automatic'] = false; $expected['drafts'] = false;
	check($state === $expected, 'Unknown fields or pickup/review/mode changed');
	$before_writes = $writes;
	check(run('enable-manual-translation', array('confirm' => true))['success'] && $before_writes === $writes, 'Repeat wrote settings');
	$state['automatic'] = $state['drafts'] = true; $fail_drafts = true;
	$r = run('enable-manual-translation', array('confirm' => true));
	check(!$r['success'] && $r['partial'] && 'translate_drafts' === $r['failed_operation'] && 'read_back' === $r['write_outcome'], 'Partial failure lost saved changes');
	$fail_drafts = false; $state = $expected;
	$state['st'] = array('autoregister_strings' => 1, 'autoregister_strings_should_register_backend_strings' => true, 'unknown' => 'keep');
	$r = run('enable-manual-translation', array('confirm' => true));
	check($r['success'] && $r['after']['ate_enabled'] && array('autoregister_strings' => 2, 'autoregister_strings_should_register_backend_strings' => false, 'unknown' => 'keep') === $state['st'], 'String collection update lost unknown fields or ATE');
	$state = $expected;
	$state['translation-management']['doc_translation_method'] = 1;
	$before_writes = $writes;
	$r = run('update-general-settings', array('changes' => array('translation_editor' => 'advanced')));
	check(!$r['applied'] && $before_writes === $writes, 'Editor preview wrote settings');
	$hash = $r['before']['settings_hash'];
	check(is_wp_error(run('update-general-settings', array('changes' => array('translation_editor' => 'advanced', 'sync_page_ordering' => true), 'confirm' => true, 'expected_settings_hash' => $hash))), 'Mixed editor write accepted');
	$fail_inventory = true;
	check(is_wp_error(run('update-general-settings', array('changes' => array('translation_editor' => 'advanced'), 'confirm' => true, 'expected_settings_hash' => $hash))) && $before_writes === $writes, 'Unknown inventory enabled delivery');
	$fail_inventory = false; $unfinished_ate = true;
	$r = run('update-general-settings', array('changes' => array('translation_editor' => 'advanced'), 'confirm' => true, 'expected_settings_hash' => $hash));
	check(is_wp_error($r) && 'mcp_wpml_editor_delivery_risk' === $r->code && $before_writes === $writes, 'Unfinished ATE work enabled delivery');
	$unfinished_ate = false;
	$r = run('update-general-settings', array('changes' => array('translation_editor' => 'advanced'), 'confirm' => true, 'expected_settings_hash' => $hash));
	check($r['success'] && $r['applied'] && $expected === $state, 'Narrow editor restoration changed unrelated fields');
	$before_writes = $writes; $hash = $r['after']['settings_hash'];
	$r = run('update-general-settings', array('changes' => array('translation_editor' => 'advanced'), 'confirm' => true, 'expected_settings_hash' => $hash));
	check($r['success'] && !$r['applied'] && $writes === $before_writes, 'Editor replay was not idempotent');
	$fail_editor = true;
	$r = run('update-general-settings', array('changes' => array('translation_editor' => 'classic'), 'confirm' => true, 'expected_settings_hash' => $hash));
	check(!$r['success'] && $r['partial'] && 'classic' === $r['after']['settings']['translation_editor'], 'Saved editor failure falsely reported no change');
	$admin = false;
	check(is_wp_error(run('enable-manual-translation', array('confirm' => true))), 'Administrator boundary removed');
	check(is_wp_error(run('update-general-settings', array('changes' => array('translation_editor' => 'advanced'), 'confirm' => true))), 'Editor administrator boundary removed');
	echo "PASS: {$checks} controls/editor assertions; no job dispatch, delivery, external calls or raw option replacement\n";
}
