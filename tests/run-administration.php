<?php
/** Exercise registered administration callbacks and native failure handling. */
declare(strict_types=1);
namespace WPML\Container {
	function make($class) {
		if (!empty($GLOBALS['fail_container'])) { throw new \RuntimeException('SECRET'); }
		return str_ends_with($class, 'SyncLock') || str_ends_with($class, 'KeyedLock') ? new \FixtureLock() : $GLOBALS['ate_api'];
	}
}
namespace WPML\TM\API {
	class Jobs {
		public static function getPreviousJob($id) { return $GLOBALS['history'][$id] ?? null; }
	}
}
namespace {
	define('ABSPATH', __DIR__ . '/');
	define('MINUTE_IN_SECONDS', 60);
	define('DAY_IN_SECONDS', 86400);
	define('HOUR_IN_SECONDS', 3600);
	define('ICL_TM_NOT_TRANSLATED', 0);
	define('ICL_TM_WAITING_FOR_TRANSLATOR', 1);
	define('ICL_TM_IN_PROGRESS', 2);
	define('ICL_TM_TRANSLATION_READY_TO_DOWNLOAD', 3);
	define('ICL_TM_ATE_NEEDS_RETRY', 40);
	define('ICL_TM_COMPLETE', 10);
	$abilities = $transients = $options = $history = array();
	$admin = true;
	$writes = 0;
	function wp_register_ability($name, $args) { $GLOBALS['abilities'][$name] = $args; }
	function current_user_can($cap) { return $GLOBALS['admin']; }
	function wp_json_encode($data) { return json_encode($data); }
	function get_current_user_id() { return 5; }
	function wp_generate_uuid4() { return sprintf('aaaaaaaa-bbbb-cccc-dddd-%012d', ++$GLOBALS['sequence']); }
	function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; return true; }
	function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
	function add_option($key, $value, ...$args) { if (isset($GLOBALS['options'][$key])) { return false; } $GLOBALS['options'][$key] = $value; return true; }
	function get_option($key) { return $GLOBALS['options'][$key] ?? false; }
	function delete_option($key) { unset($GLOBALS['options'][$key]); }
	function do_action(...$args) {}
	function is_wp_error($value) { return $value instanceof WP_Error; }
	class WP_Error { public function __construct(public $code, public $message) {} }
	class WPML_TM_Editors { const ATE = 'ate'; }
	class WPML_TM_ATE_Job { const ATE_JOB_DELIVERED = 8; }
	class WPML_TM_ATE_Status { public static $enabled = false; public static function is_enabled() { return self::$enabled; } }
	class WPML_TM_ATE_API { const CANCELLED_STATUS = 20; const SHOULD_HIDE_STATUS = 42; }
	class WPML_TM_Jobs_Search_Params {
		public $limit = 200, $offset = 0, $sources = array();
		public function set_limit($v) { $this->limit = $v; }
		public function set_offset($v) { $this->offset = $v; }
		public function set_exclude_hidden_jobs($v) {}
		public function set_exclude_cancelled($v) {}
		public function set_original_element_id($v) { $this->sources = $v; }
	}
	class WPML_TM_Post_Job_Entity {
		public function __construct(public $rid, public $source, public $job, public $status, public $ate = 0) {}
		public function get_rid() { return $this->rid; }
		public function get_type() { return 'post'; }
		public function get_original_element_id() { return $this->source; }
		public function get_title() { return 'Fixture'; }
		public function get_source_language() { return 'en'; }
		public function get_target_language() { return 'fr'; }
		public function get_status() { return $this->status; }
		public function set_status($v) { $this->status = $v; }
		public function does_need_update() { return false; }
		public function get_translation_service() { return 'local'; }
		public function get_translate_job_id() { return $this->job; }
		public function is_ate_editor() { return (bool) $this->ate; }
		public function get_editor_job_id() { return $this->ate; }
		public function is_automatic() { return false; }
	}
	class FixtureRepository {
		public function get_count($p) { if (!empty($GLOBALS['fail_local'])) { throw new RuntimeException('SECRET'); } return count($this->get_all($p)); }
		public function get_all($p) { return array_values(array_filter($GLOBALS['jobs'], fn($j) => !$p->sources || in_array($j->source, $p->sources, true))); }
		public function get($p) { return array_slice($this->get_all($p), $p->offset, $p->limit); }
		public function get_job($rid, $type) { foreach ($GLOBALS['jobs'] as $j) { if ($rid === $j->rid) { return $j; } } return null; }
	}
	function wpml_tm_get_jobs_repository(...$args) { return new FixtureRepository(); }
	class FixtureAPI {
		public $statuses = array(), $queries = array(), $throw = false, $fail = false, $cancel_failure = false, $delivery_available = false, $cancel_calls = 0;
		public function get_jobs($ids) { $this->queries[] = $ids; if ($this->throw) { throw new RuntimeException('SECRET'); } if ($this->fail) { return new WP_Error('remote', 'SECRET should never be exposed'); } return array_map(fn($id) => array('ate_job_id' => $id, 'status_id' => $this->statuses[$id] ?? 1, 'editor_url' => 'SECRET'), $ids); }
		public function cancelJobs($ids) { ++$this->cancel_calls; if ($this->cancel_failure) { return new WP_Error('remote', 'SECRET'); } foreach ($ids as $id) { $this->statuses[$id] = 20; } return array('success' => true); }
		public function get_job_status_with_priority($id) { return array(array('ate_job_id' => $id, 'status_id' => $this->statuses[$id] ?? 1, 'translated_xliff' => $this->delivery_available ? 'SECRET' : null)); }
	}
	class FixtureLock { public function create() { return 'fixture'; } public function release() {} }
	class WPML_TP_Sync_Update_Job { public function __construct(...$args) {} public function update_state($j) { ++$GLOBALS['writes']; } }
	$ate_api = new FixtureAPI();
	$sequence = 0;
	$jobs = array(new WPML_TM_Post_Job_Entity(1, 101, 201, 1), new WPML_TM_Post_Job_Entity(2, 102, 202, 10), new WPML_TM_Post_Job_Entity(3, 103, 203, 2, 303));
	require (getenv('MCP_WPML_SOURCE_DIR') ?: dirname(__DIR__)) . '/includes/administration-abilities.php';
	mcp_wpml_register_administration_abilities();
	$run = function ($slug, $input = array()) use (&$abilities) { return ($abilities['wpml/' . $slug]['execute_callback'])($input); };
	$check = function ($ok, $message) { if (!$ok) { throw new RuntimeException($message); } };
	$check(6 === count($abilities), 'Administration abilities missing.');
	$admin = false;
	foreach ($abilities as $ability) { $check(!$ability['permission_callback'](), 'Non-administrator permitted.'); }
	$admin = true;
	$r = $run('cancel-translation-jobs', array('confirm' => true));
	$check(is_wp_error($r) && !$writes, 'Missing loss acknowledgement mutated jobs.');
	$r = $run('preview-translation-job-cancellation');
	$check(2 === $r['pending_count'] && 2 === $r['cancellable_count'], 'Finished job included in cancellation.');
	$check(!str_contains(json_encode($r), 'SECRET'), 'Remote data or editor URL disclosed.');
	$ate_api->cancel_failure = true;
	$r = $run('cancel-translation-jobs', array('preview_id' => $r['preview_id'], 'confirm' => true, 'acknowledge_loss' => true));
	$check(is_wp_error($r) && !$writes, 'Remote failure still changed local jobs.');
	$ate_api->cancel_failure = false;
	$p = $run('preview-translation-job-cancellation');
	$jobs[0]->job = 999;
	$r = $run('cancel-translation-jobs', array('preview_id' => $p['preview_id'], 'confirm' => true, 'acknowledge_loss' => true));
	$check(is_wp_error($r) && !$writes && 1 === $ate_api->cancel_calls, 'Stale preview cancelled a changed job.');
	$jobs[0]->job = 201;
	$ate_api->fail = true;
	$p = $run('preview-translation-job-cancellation');
	$check(!$p['can_cancel'] && !$p['external_verification_complete'], 'Unknown remote job treated as empty.');
	$r = $run('cancel-translation-jobs', array('preview_id' => $p['preview_id'], 'confirm' => true, 'acknowledge_loss' => true));
	$check(is_wp_error($r) && !$writes, 'Unverified preview changed local jobs.');
	$ate_api->fail = false;
	WPML_TM_ATE_Status::$enabled = true;
	$p = $run('preview-translation-job-cancellation');
	$check(!$p['can_cancel'], 'Active ATE delivery permits cancellation.');
	$r = $run('cancel-translation-jobs', array('preview_id' => $p['preview_id'], 'confirm' => true, 'acknowledge_loss' => true));
	$check(is_wp_error($r) && !$writes, 'Active ATE delivery still changed local jobs.');
	WPML_TM_ATE_Status::$enabled = false;
	$ate_api->delivery_available = true;
	$p = $run('preview-translation-job-cancellation');
	$r = $run('cancel-translation-jobs', array('preview_id' => $p['preview_id'], 'confirm' => true, 'acknowledge_loss' => true));
	$check(is_wp_error($r) && !$writes && !str_contains(json_encode($r), 'SECRET'), 'Deliverable XLIFF cleared local jobs or disclosed a URL.');
	$ate_api->delivery_available = false;
	$p = $run('preview-translation-job-cancellation');
	$r = $run('cancel-translation-jobs', array('preview_id' => $p['preview_id'], 'confirm' => true, 'acknowledge_loss' => true));
	$check($r['success'] && 2 === $writes && 10 === $jobs[1]->status && 0 === $r['remaining_pending_count'], 'Native cancellation or finished-job protection failed.');
	$jobs[] = new WPML_TM_Post_Job_Entity(4, 104, 204, 1);
	$again = $run('cancel-translation-jobs', array('preview_id' => $p['preview_id'], 'confirm' => true, 'acknowledge_loss' => true));
	$check($again['idempotent_replay'] && 2 === $writes && !$again['queue_currently_empty'] && 1 === $again['current_pending_count'], 'Repeat cancelled an unseen new job or returned stale queue state.');
	// Local inventory and external verification have separate failure semantics.
	$jobs = array(new WPML_TM_Post_Job_Entity(11, 111, 211, 10, 311), new WPML_TM_Post_Job_Entity(12, 112, 212, 2, 312));
	$ate_api->queries = array(); $ate_api->throw = true;
	$r = $run('list-translation-jobs', array('check_ate' => true));
	$check(!is_wp_error($r) && $r['local_inventory_complete'] && 2 === $r['total'] && 1 === $r['pending_count'] && 1 === $r['unverified_ate_count'], 'External exception destroyed local inventory.');
	$check(!$r['external_verification_complete'] && 'unavailable_or_incomplete' === $r['external_verification_status'], 'Unknown ATE state reported complete.');
	$check(array(array(312)) === $ate_api->queries && !str_contains(json_encode($r), 'SECRET'), 'Completed jobs queried externally or exception leaked.');
	$fail_container = true;
	$r = $run('list-translation-jobs', array('check_ate' => true));
	$check(!is_wp_error($r) && 1 === $r['unverified_ate_count'], 'ATE dependency construction failure destroyed local inventory.');
	$fail_container = false; $ate_api->throw = false;
	$r = $run('list-translation-jobs', array('check_ate' => false));
	$check('not_requested' === $r['external_verification_status'] && !$r['external_verification_requested'], 'Skipped external read reported as verified.');
	$jobs = array($jobs[0]); $ate_api->queries = array();
	$r = $run('list-translation-jobs', array('check_ate' => true));
	$check($r['external_verification_complete'] && !$ate_api->queries, 'Finished-only queue required ATE connectivity.');
	$fail_local = true;
	$r = $run('list-translation-jobs');
	$check(is_wp_error($r) && 'mcp_wpml_job_read_failed' === $r->code && !str_contains(json_encode($r), 'SECRET'), 'Local inventory failure returned an empty queue or leaked an exception.');
	$fail_local = false;
	echo "PASS: registered callbacks, permissions, completion exclusion, redaction, loss confirmation, stale preview, remote failure, native transition, readback and repeat safety\n";
}
