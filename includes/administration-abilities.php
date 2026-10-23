<?php
/**
 * Native WPML job administration and manual translation controls.
 *
 * @package MCP_Abilities_SitePress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/** Return only translation controls, never registration or authentication values. */
function mcp_wpml_translation_controls(): array {
	global $sitepress;
	$tm = (array) $sitepress->get_setting('translation-management', array());
	$st = (array) $sitepress->get_setting('st', array());
	$st_interface = '\\WPML\\StringTranslation\\Application\\Setting\\Repository\\SettingsRepositoryInterface';
	$st_disabled = interface_exists($st_interface) ? constant($st_interface . '::AUTOREGISTER_STRINGS_TYPE_DISABLED') : null;
	return array(
		'translate_everything' => (bool) \WPML\Setup\Option::shouldTranslateEverything(),
		'translate_drafts' => (bool) \WPML\Setup\Option::getTranslateEverythingDrafts(),
		'review_mode' => \WPML\Setup\Option::getReviewMode(),
		'document_editor' => $tm['doc_translation_method'] ?? null,
		'native_editor_default' => (bool) ($tm[\WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_USE_NATIVE] ?? false),
		'native_editor_post_types' => (array) ($tm[\WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_USE_NATIVE] ?? array()),
		'string_autoregistration' => $st['autoregister_strings'] ?? $st_disabled,
		'string_autoregistration_disabled' => null === $st_disabled || $st_disabled === (int) ($st['autoregister_strings'] ?? $st_disabled),
		'backend_string_autoregistration' => (bool) ($st['autoregister_strings_should_register_backend_strings'] ?? false),
		'translation_pickup_method' => (int) $sitepress->get_setting('translation_pickup_method', ICL_PRO_TRANSLATION_PICKUP_POLLING),
		'ate_enabled' => \WPML_TM_ATE_Status::is_enabled(),
		'ate_account_active' => \WPML_TM_ATE_Status::is_active(),
		'existing_external_jobs_cancelled' => false,
	);
}

/** A settings read must not initialize or expose WPML account data. */
function mcp_wpml_get_translation_controls($input = array()): array {
	return array('success' => true, 'controls' => mcp_wpml_translation_controls());
}

/** Disable translation automation through the native WPML settings Interfaces. */
function mcp_wpml_enable_manual_translation($input = array()) {
	if (empty($input['confirm'])) {
		return new WP_Error('mcp_wpml_confirmation_required', 'Review the translation controls and explicitly confirm manual translation mode.');
	}
	global $sitepress;
	if (class_exists('WPML\\StringTranslation\\Infrastructure\\Setting\\Repository\\SettingsRepository')
		&& !current_user_can('wpml_manage_string_translation') && !current_user_can('manage_translations')) {
		return new WP_Error('mcp_wpml_permission_denied', 'Native String Translation management permission is required. No controls were changed.');
	}
	$before = mcp_wpml_translation_controls();
	$operation = 'translate_everything';
	try {
		// Do not dispatch wpml_set_translate_everything: its listener hides existing jobs.
		\WPML\Setup\Option::setTranslateEverything(false);
		\WPML\Setup\Option::setTranslateEverythingDrafts(0);
		\WPML\Setup\Option::setReviewMode(\WPML\Setup\Option::HOLD_FOR_REVIEW);
		\WPML\Setup\Option::setTranslationMode(array('myself'));
		// The classic editor is a supported native choice. The global native switch below
		// selects WordPress editing, while disabling ATE's receive and background hooks.
		$operation = 'document_editor';
		\WPML\API\Settings::assoc('translation-management', 'doc_translation_method', ICL_TM_TMETHOD_EDITOR);
		$operation = 'native_editor';
		$editor = new \WPML\TM\PostEditScreen\Endpoints\SetEditorMode($sitepress);
		$editor->run(wpml_collect(array('enabledEditor' => 'native', 'editorModeFor' => 'global')));
		$settings_class = '\\WPML\\StringTranslation\\Infrastructure\\Setting\\Repository\\SettingsRepository';
		if (class_exists($settings_class)) {
			$operation = 'string_collection';
			$urls = new \WPML\StringTranslation\Infrastructure\Setting\Repository\UrlRepository($sitepress);
			$strings = new $settings_class($sitepress, $urls);
			$interface = '\\WPML\\StringTranslation\\Application\\Setting\\Repository\\SettingsRepositoryInterface';
			$strings->setAutoregisterStringsTypeSetting(constant($interface . '::AUTOREGISTER_STRINGS_TYPE_DISABLED'));
			$strings->setShouldRegisterBackendStringsSetting(false);
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML String Translation cache invalidation.
			do_action('wpml_st_update_settings', 'deleteCache');
		}
		$operation = 'manual_delivery';
		$pickup = new \WPML_Update_PickUp_Method($sitepress);
		$project = class_exists('TranslationProxy') ? \TranslationProxy::get_current_project() : false;
		if ($project) {
			$pickup_result = $pickup->update_pickup_method(array('icl_translation_pickup_method' => ICL_PRO_TRANSLATION_PICKUP_POLLING), $project);
			if ('ok' !== $pickup_result) {
				return new WP_Error('mcp_wpml_pickup_failed', 'WPML could not confirm manual delivery. Re-read the controls; the other changes may have succeeded.');
			}
		} else {
			// No translation-service project exists: preserve other SitePress settings.
			$sitepress->set_setting('translation_pickup_method', ICL_PRO_TRANSLATION_PICKUP_POLLING, true);
		}
		$after = mcp_wpml_translation_controls();
		$verified = !$after['translate_everything'] && !$after['translate_drafts'] && !$after['ate_enabled']
			&& $after['native_editor_default'] && empty($after['native_editor_post_types'])
			&& $after['string_autoregistration_disabled'] && !$after['backend_string_autoregistration']
			&& ICL_PRO_TRANSLATION_PICKUP_POLLING === $after['translation_pickup_method'];
		return array('success' => $verified, 'before' => $before, 'after' => $after,
			'new_automatic_translation_disabled' => $verified,
			'existing_external_jobs_cancelled' => false,
			'note' => 'Existing external work is not cancelled or refunded. Re-enabling ATE can restore delivery eligibility.');
	} catch (Throwable $error) {
		return new WP_Error('mcp_wpml_manual_mode_failed', 'A native WPML operation failed: ' . $operation . '. Re-read the controls to check partial changes.');
	}
}

/** The latest native repository entries plus previous jobs from the same RID. */
function mcp_wpml_job_rows(array $input): array {
	if (!function_exists('wpml_tm_get_jobs_repository') || !class_exists('WPML_TM_Jobs_Search_Params')) {
		throw new RuntimeException('Native translation management is unavailable.');
	}
	$params = new \WPML_TM_Jobs_Search_Params();
	$params->set_limit(200);
	$params->set_exclude_hidden_jobs(false);
	$params->set_exclude_cancelled(false);
	if (!empty($input['source_ids'])) {
		$params->set_original_element_id(array_map('intval', $input['source_ids']));
	}
	$repository = wpml_tm_get_jobs_repository(true, false, true);
	$total = $repository->get_count($params);
	if ($total > 5000) {
		throw new RuntimeException('Narrow the source scope before administration.');
	}
	$rows = array();
	$pending_statuses = array(ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_TM_TRANSLATION_READY_TO_DOWNLOAD, ICL_TM_ATE_NEEDS_RETRY);
	for ($offset = 0; $offset < $total; $offset += 200) {
		$params->set_offset($offset);
		foreach ($repository->get($params) as $job) {
			$is_post = $job instanceof \WPML_TM_Post_Job_Entity;
			$row = array(
				'rid' => (int) $job->get_rid(), 'type' => $job->get_type(),
				'source_id' => (int) $job->get_original_element_id(), 'title' => (string) $job->get_title(),
				'source_language' => $job->get_source_language(), 'target_language' => $job->get_target_language(),
				'local_status' => (int) $job->get_status(), 'needs_update' => (bool) $job->does_need_update(),
				'service' => (string) $job->get_translation_service(), 'current' => true,
				'job_id' => $is_post ? (int) $job->get_translate_job_id() : null,
				'ate_id' => $is_post && $job->is_ate_editor() ? (int) $job->get_editor_job_id() : 0,
				'automatic' => $is_post && $job->is_automatic(),
				'local_pending' => in_array((int) $job->get_status(), $pending_statuses, true),
				'local_completed' => ICL_TM_COMPLETE === (int) $job->get_status(),
			);
			$rows[] = $row;
			if ($is_post && !empty($input['include_history'])) {
				$seen = array($row['job_id'] => true);
				$previous_id = $row['job_id'];
				while ($previous = \WPML\TM\API\Jobs::getPreviousJob($previous_id)) {
					$previous_id = (int) $previous->job_id;
					if (isset($seen[$previous_id]) || (int) $previous->rid !== $row['rid'] || count($rows) >= 10000) {
						throw new RuntimeException('Translation history could not be read completely.');
					}
					$seen[$previous_id] = true;
					$historical = $row;
					$historical['current'] = false;
					$historical['job_id'] = $previous_id;
					$historical['ate_id'] = \WPML_TM_Editors::ATE === ($previous->editor ?? '') ? (int) ($previous->editor_job_id ?? 0) : 0;
					$historical['automatic'] = !empty($previous->automatic);
					$historical['local_pending'] = false;
					$historical['local_completed'] = !empty($previous->translated);
					$rows[] = $historical;
				}
			}
		}
	}
	usort($rows, static function (array $a, array $b): int {
		return array($a['type'], $a['rid'], $a['job_id']) <=> array($b['type'], $b['rid'], $b['job_id']);
	});
	return $rows;
}

/** Read status through WPML's authenticated client; discard URLs and response text. */
function mcp_wpml_ate_statuses(array $ids): array {
	$statuses = array();
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
	if (!$ids) {
		return $statuses;
	}
	$api = \WPML\Container\make(\WPML_TM_ATE_API::class);
	foreach (array_chunk($ids, 50) as $chunk) {
		$response = $api->get_jobs($chunk);
		if (is_wp_error($response)) {
			continue;
		}
		$data = json_decode((string) wp_json_encode($response), true);
		$items = isset($data['jobs']) && is_array($data['jobs']) ? $data['jobs'] : $data;
		foreach ((array) $items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$id = (int) ($item['ate_job_id'] ?? $item['ate_id'] ?? $item['id'] ?? 0);
			$status = $item['status_id'] ?? $item['status'] ?? null;
			if (in_array($id, $chunk, true) && is_numeric($status)) {
				$statuses[$id] = (int) $status;
			}
		}
	}
	return $statuses;
}

/** Annotate current and historical jobs without equating an unreadable ATE state to empty. */
function mcp_wpml_job_snapshot(array $input): array {
	$rows = mcp_wpml_job_rows($input);
	$statuses = !empty($input['check_ate']) ? mcp_wpml_ate_statuses(array_column($rows, 'ate_id')) : array();
	$pending = array();
	$unknown = array();
	foreach ($rows as &$row) {
		$row['ate_status'] = $statuses[$row['ate_id']] ?? null;
		$row['ate_verified'] = 0 === $row['ate_id'] || null !== $row['ate_status'];
		$row['external_pending'] = $row['ate_id'] > 0 && null !== $row['ate_status']
			&& !in_array($row['ate_status'], array(\WPML_TM_ATE_API::CANCELLED_STATUS, \WPML_TM_ATE_API::SHOULD_HIDE_STATUS, \WPML_TM_ATE_Job::ATE_JOB_DELIVERED), true);
		// Finished translations are never removed by queue cancellation.
		$row['pending'] = !$row['local_completed'] && ($row['local_pending'] || $row['external_pending']);
		$row['cancellable'] = $row['pending'] && 'local' === $row['service'] && $row['ate_verified']
			&& \WPML_TM_ATE_Job::ATE_JOB_DELIVERED !== $row['ate_status'];
		if ($row['pending']) {
			$pending[] = $row;
		}
		if ($row['ate_id'] > 0 && !$row['ate_verified'] && !$row['local_completed']) {
			$unknown[] = array('job_id' => $row['job_id'], 'ate_id' => $row['ate_id'], 'current' => $row['current']);
		}
	}
	unset($row);
	return array('rows' => $rows, 'pending' => $pending, 'unverified_ate_jobs' => $unknown,
		'external_verification_complete' => empty($unknown), 'verification_scope' => 'unfinished_jobs', 'scope' => $input);
}

/** Read native translation management and optionally verify external ATE status. */
function mcp_wpml_list_translation_jobs($input = array()) {
	try {
		$input = array_merge(array('source_ids' => array(), 'include_history' => true, 'check_ate' => false, 'offset' => 0, 'limit' => 100), (array) $input);
		$snapshot = mcp_wpml_job_snapshot($input);
		return array('success' => true, 'total' => count($snapshot['rows']), 'pending_count' => count($snapshot['pending']),
			'jobs' => array_slice($snapshot['rows'], (int) $input['offset'], (int) $input['limit']),
			'external_verification_complete' => $snapshot['external_verification_complete'],
			'verification_scope' => $snapshot['verification_scope'],
			'unverified_ate_count' => count($snapshot['unverified_ate_jobs']));
	} catch (Throwable $error) {
		return new WP_Error('mcp_wpml_job_read_failed', 'The native WPML job inventory could not be read completely. No jobs were changed.');
	}
}

/** Bind cancellation to an exact, short-lived preview and the authenticated user. */
function mcp_wpml_preview_translation_job_cancellation($input = array()) {
	try {
		$scope = array('source_ids' => (array) ($input['source_ids'] ?? array()), 'include_history' => true, 'check_ate' => true);
		$snapshot = mcp_wpml_job_snapshot($scope);
		$id = wp_generate_uuid4();
		$snapshot['user_id'] = get_current_user_id();
		$snapshot['completed'] = array();
		set_transient('mcp_wpml_cancel_' . $id, $snapshot, 15 * MINUTE_IN_SECONDS);
		$delivery_disabled = !\WPML_TM_ATE_Status::is_enabled();
		return array('success' => true, 'preview_id' => $id, 'expires_in_seconds' => 15 * MINUTE_IN_SECONDS,
			'pending_count' => count($snapshot['pending']), 'jobs' => $snapshot['pending'],
			'cancellable_count' => count(array_filter($snapshot['pending'], static fn(array $row): bool => $row['cancellable'])),
			'external_verification_complete' => $snapshot['external_verification_complete'],
			'unverified_ate_jobs' => $snapshot['unverified_ate_jobs'],
			'ate_delivery_disabled' => $delivery_disabled,
			'permanent_consequence' => 'Cancelling unfinished work can discard translation progress. Previously consumed credits are not refunded. Finished translations are excluded.',
			'requires_loss_acknowledgement' => true,
			'can_cancel' => $delivery_disabled && !$snapshot['unverified_ate_jobs'] && !array_filter($snapshot['pending'], static fn(array $row): bool => !$row['cancellable']));
	} catch (Throwable $error) {
		return new WP_Error('mcp_wpml_preview_failed', 'WPML could not provide a complete cancellation preview. No jobs were changed.');
	}
}

/** Exact local state, excluding remote progress which can legitimately advance. */
function mcp_wpml_job_state_hash(array $rows): string {
	foreach ($rows as &$row) {
		foreach (array('ate_status', 'ate_verified', 'external_pending', 'pending', 'cancellable') as $key) {
			unset($row[$key]);
		}
	}
	unset($row);
	return hash('sha256', (string) wp_json_encode($rows));
}

/** Cancel the previewed native queue, with remote confirmation before local mutation. */
function mcp_wpml_cancel_translation_jobs($input = array()) {
	if (empty($input['confirm']) || empty($input['acknowledge_loss'])) {
		return new WP_Error('mcp_wpml_confirmation_required', 'An exact preview and explicit acknowledgement of lost job progress are required.');
	}
	$id = (string) ($input['preview_id'] ?? '');
	if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) {
		return new WP_Error('mcp_wpml_preview_invalid', 'Use a current cancellation preview.');
	}
	$key = 'mcp_wpml_cancel_' . $id;
	$preview = get_transient($key);
	if (!is_array($preview) || (int) $preview['user_id'] !== get_current_user_id()) {
		return new WP_Error('mcp_wpml_preview_expired', 'The cancellation preview expired or belongs to another user.');
	}
	if (!empty($preview['receipt'])) {
		$result = $preview['receipt'];
		$result['idempotent_replay'] = true;
		try {
			$now = mcp_wpml_job_snapshot($preview['scope']);
			$result['current_pending_count'] = count($now['pending']);
			$result['queue_currently_empty'] = !$now['pending'] && !$now['unverified_ate_jobs'];
		} catch (Throwable $error) {
			$result['queue_currently_empty'] = false;
			$result['readback_failed'] = true;
		}
		return $result;
	}
	if ($preview['unverified_ate_jobs'] || array_filter($preview['pending'], static fn(array $row): bool => !$row['cancellable'])) {
		return new WP_Error('mcp_wpml_queue_unverified', 'Some native or external jobs cannot be verified or cancelled. No local jobs were changed.');
	}
	if (\WPML_TM_ATE_Status::is_enabled()) {
		return new WP_Error('mcp_wpml_manual_mode_required', 'Disable ATE delivery through native manual translation controls before cancellation. No jobs were changed.');
	}
	$lock = \WPML\Container\make(\WPML\Utilities\KeyedLock::class, array(':name' => 'mcp_wpml_cancellation'));
	$lock_token = $lock->create(wp_generate_uuid4(), HOUR_IN_SECONDS);
	if (!$lock_token) {
		return new WP_Error('mcp_wpml_queue_busy', 'Another cancellation is active. Re-read the queue before retrying.');
	}
	try {
		$current = mcp_wpml_job_snapshot($preview['scope']);
		if (mcp_wpml_job_state_hash($preview['rows']) !== mcp_wpml_job_state_hash($current['rows'])) {
			return new WP_Error('mcp_wpml_preview_stale', 'WPML jobs changed after the preview. Obtain a fresh preview; no jobs were cancelled.');
		}
		if ($current['unverified_ate_jobs'] || array_filter($current['pending'], static fn(array $row): bool => !$row['cancellable'])) {
			return new WP_Error('mcp_wpml_queue_unverified', 'ATE status changed or could not be verified. Obtain a fresh preview; no jobs were cancelled.');
		}
		$api = \WPML\Container\make(\WPML_TM_ATE_API::class);
		$remote_ids = array_values(array_unique(array_filter(array_column($preview['pending'], 'ate_id'))));
		if ($remote_ids) {
			foreach (array_chunk($remote_ids, 50) as $chunk) {
				$response = $api->cancelJobs($chunk);
				if (is_wp_error($response)) {
					return new WP_Error('mcp_wpml_ate_cancel_failed', 'ATE did not confirm cancellation. Local jobs were preserved; external cancellation may be partial. Re-read the queue.');
				}
			}
			$remote_after = mcp_wpml_ate_statuses($remote_ids);
			foreach ($remote_ids as $remote_id) {
				if (\WPML_TM_ATE_API::CANCELLED_STATUS !== ($remote_after[$remote_id] ?? null)) {
					return new WP_Error('mcp_wpml_ate_cancel_unverified', 'ATE cancellation is not verified for every selected job. Local jobs were preserved. Re-read the queue.');
				}
				// PublicReceive reads this native priority response before applying XLIFF.
				$response = $api->get_job_status_with_priority($remote_id);
				$verified = false;
				if (!is_wp_error($response)) {
					foreach ((array) json_decode((string) wp_json_encode($response), true) as $item) {
						if (is_array($item) && $remote_id === (int) ($item['ate_job_id'] ?? 0)
							&& \WPML_TM_ATE_API::CANCELLED_STATUS === (int) ($item['status_id'] ?? -1)
							&& array_key_exists('translated_xliff', $item) && empty($item['translated_xliff'])) {
							$verified = true;
						}
					}
				}
				if (!$verified) {
					return new WP_Error('mcp_wpml_ate_delivery_unverified', 'ATE delivery eligibility could not be excluded for every cancelled job. Local jobs were preserved. Re-read the queue.');
				}
			}
		}
		$native_lock = \WPML\Container\make(\WPML\TM\ATE\SyncLock::class);
		$sync_key = $native_lock->create();
		if (!$sync_key) {
			return new WP_Error('mcp_wpml_ate_sync_busy', 'WPML is receiving translations. External cancellation may have succeeded; local jobs were preserved. Re-read the queue.');
		}
		try {
			$fresh = mcp_wpml_job_rows($preview['scope']);
			if (mcp_wpml_job_state_hash($preview['rows']) !== mcp_wpml_job_state_hash($fresh)) {
				return new WP_Error('mcp_wpml_preview_stale', 'Native jobs changed while ATE cancellation was running. Local jobs were preserved. Re-read the queue.');
			}
			global $wpdb, $sitepress;
			$repository = wpml_tm_get_jobs_repository(true, false, true);
			$native = new \WPML_TP_Sync_Update_Job($wpdb, $sitepress);
			$post_jobs = array();
			foreach ($preview['pending'] as $row) {
				if (!$native_lock->create($sync_key) || \WPML_TM_ATE_Status::is_enabled()) {
					return new WP_Error('mcp_wpml_ate_sync_changed', 'Native delivery controls changed during cancellation. Re-read the queue to check partial results.');
				}
				if ($row['current'] && $row['local_pending']) {
					$job = $repository->get_job($row['rid'], $row['type']);
					if (!$job) {
						return new WP_Error('mcp_wpml_local_cancel_failed', 'A native job disappeared during cancellation. Re-read the queue to check partial results.');
					}
					// These are the native Jobs REST controller's state-transition calls.
					$job->set_status(ICL_TM_NOT_TRANSLATED);
					$native->update_state($job);
					if ($job instanceof \WPML_TM_Post_Job_Entity) {
						$post_jobs[] = $job;
					}
				}
			}
			if ($post_jobs) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML lifecycle hook.
				do_action('wpml_tm_jobs_cancelled', $post_jobs);
			}
		} finally {
			$native_lock->release();
		}
		$after = mcp_wpml_job_snapshot($preview['scope']);
		$result = array('success' => !$after['pending'] && !$after['unverified_ate_jobs'],
			'preview_id' => $id, 'selected_count' => count($preview['pending']),
			'selected_jobs' => array_map(static fn(array $row): array => array('rid' => $row['rid'], 'type' => $row['type'], 'job_id' => $row['job_id'], 'ate_id' => $row['ate_id']), $preview['pending']),
			'remaining_pending_count' => count($after['pending']), 'remaining_jobs' => $after['pending'],
			'external_verification_complete' => $after['external_verification_complete'],
			'unverified_ate_jobs' => $after['unverified_ate_jobs'], 'idempotent_replay' => false);
		$preview['receipt'] = $result;
		set_transient($key, $preview, DAY_IN_SECONDS);
		return $result;
	} catch (Throwable $error) {
		return new WP_Error('mcp_wpml_cancellation_failed', 'A native cancellation operation failed. Cancellation may be partial. Re-read the local and external queue before retrying.');
	} finally {
		$lock->release();
	}
}

/** Explicit coverage, including functions requiring a secure human workflow. */
function mcp_wpml_administration_coverage($input = array()): array {
	$coverage = array(
		'languages_and_relationships' => array('supported' => array('wpml/list-active-languages', 'wpml/get-site-languages', 'wpml/add-site-language', 'wpml/set-active-site-languages', 'wpml/set-default-site-language', 'wpml/get-post-translations', 'wpml/get-term-translations', 'wpml/link-post-translation', 'wpml/set-post-language-details', 'wpml/set-term-language-details', 'wpml/link-term-translation'), 'missing' => array('Delete language content', 'Edit existing language names, flags and display translations', 'Language-pack downloads and native language setup notices')),
		'translations_and_strings' => array('supported' => array('wpml/ensure-post-translation', 'wpml/update-translated-post-url', 'wpml/update-contact-form-7-translation-form', 'wpml/list-strings', 'wpml/get-string', 'wpml/update-string-translation'), 'registered_option_strings' => 'Select an exact registered admin_texts_* domain with include_option_strings; native administrator and String Translation permissions are required.', 'missing' => array('Unregistered option values', 'String registration, deletion and catalog import/export', 'Updates to waiting or in-progress/partial string targets')),
		'jobs_and_ate' => array('supported' => array('wpml/list-translation-jobs', 'wpml/preview-translation-job-cancellation', 'wpml/cancel-translation-jobs', 'wpml/list-local-translators', 'wpml/assign-local-translation-job', 'wpml/create-local-translation-job'), 'missing' => array('Grant translator roles or language pairs', 'External/automatic/ATE dispatch and assignment', 'Resend jobs for an existing target relationship or job history', 'String/package job dispatch', 'Atomic coordination with concurrent native UI edits')),
		'settings' => array('supported' => array('wpml/get-translation-controls', 'wpml/enable-manual-translation', 'wpml/get-language-switcher-settings', 'wpml/get-general-settings', 'wpml/update-general-settings'), 'missing' => array('Settings outside the synchronization and translated-slug allowlist', 'Raw options, URL domains, access roles and account settings')),
		'diagnostics' => array('supported' => array('wpml/audit-translation-integrity', 'wpml/audit-translated-links', 'wpml/audit-translation-coverage', 'wpml/get-diagnostics', 'wpml/clear-translation-cache'), 'missing' => array('Full debug dumps and external connectivity probes', 'Database schema repair, destructive reset and orphan-job repair')),
		'registration' => array('supported' => array('wpml/get-registration-status', 'wpml/get-registration-workflow'), 'requires_secure_user_step' => array('WPML registration and site-key replacement in the native secure form', 'New access or account connections'), 'missing' => array('License validity verification', 'Registration-key input/output, payments and subscriptions')),
	);
	return array('success' => true, 'wpml_version' => ICL_SITEPRESS_VERSION,
		'coverage' => $coverage, 'full_administration_supported' => false,
		'note' => 'A callable operation is not authorization to change settings, registration, billing, or delete work. Registration keys are never returned.');
}

/** Register administration operations on the established authenticated ability Interface. */
function mcp_wpml_register_administration_abilities(): void {
	$scope_schema = array('type' => 'array', 'items' => array('type' => 'integer', 'minimum' => 1), 'maxItems' => 200, 'uniqueItems' => true, 'default' => array());
	$definitions = array(
		'get-translation-controls' => array('Get Translation Controls', 'Read redacted native WPML automation, editor, delivery and account-status controls.', 'mcp_wpml_get_translation_controls', array(), array(), true, false, true),
		'enable-manual-translation' => array('Enable Manual Translation', 'Disable new automatic translation, ATE delivery, automatic string collection and automatic service pickup using native WPML settings. Does not cancel external jobs.', 'mcp_wpml_enable_manual_translation', array('confirm' => array('type' => 'boolean', 'default' => false)), array('confirm'), false, true, true),
		'list-translation-jobs' => array('List Translation Jobs', 'Read current and historical native WPML jobs. Optional ATE status verification never treats unknown jobs as an empty queue.', 'mcp_wpml_list_translation_jobs', array('source_ids' => $scope_schema, 'include_history' => array('type' => 'boolean', 'default' => true), 'check_ate' => array('type' => 'boolean', 'default' => false), 'offset' => array('type' => 'integer', 'minimum' => 0, 'default' => 0), 'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100)), array(), true, false, true),
		'preview-translation-job-cancellation' => array('Preview Translation Job Cancellation', 'Read the whole pending queue or selected source groups, verify ATE status, and bind exact affected jobs to a short-lived user preview. Finished translations are excluded.', 'mcp_wpml_preview_translation_job_cancellation', array('source_ids' => $scope_schema), array(), false, false, false),
		'cancel-translation-jobs' => array('Cancel Translation Jobs', 'Cancel only an exact verified preview through native WPML and ATE mechanisms. Requires acknowledgement of lost progress, rejects stale local state and verifies remaining jobs.', 'mcp_wpml_cancel_translation_jobs', array('preview_id' => array('type' => 'string', 'pattern' => '^[a-f0-9-]{36}$'), 'confirm' => array('type' => 'boolean', 'default' => false), 'acknowledge_loss' => array('type' => 'boolean', 'default' => false)), array('preview_id', 'confirm', 'acknowledge_loss'), false, true, true),
		'get-administration-coverage' => array('Get WPML Administration Coverage', 'List supported, missing and secure-user-step WPML administration functions without returning credentials.', 'mcp_wpml_administration_coverage', array(), array(), true, false, true),
	);
	mcp_wpml_register_native_admin_definitions($definitions);
}

/** Keep schemas and native capability callbacks at the existing ability registration seam. */
function mcp_wpml_register_native_admin_definitions(array $definitions): void {
	foreach ($definitions as $slug => $definition) {
		wp_register_ability('wpml/' . $slug, array(
			'label' => $definition[0], 'description' => $definition[1],
			'category' => 'site',
			'input_schema' => array('type' => 'object', 'properties' => $definition[3], 'required' => $definition[4], 'additionalProperties' => false),
			'output_schema' => array('type' => 'object'), 'execute_callback' => $definition[2],
			'permission_callback' => $definition[8] ?? static fn(): bool => current_user_can('manage_options'),
			'meta' => array('annotations' => array('readonly' => $definition[5], 'destructive' => $definition[6], 'idempotent' => $definition[7])),
		));
	}
}
