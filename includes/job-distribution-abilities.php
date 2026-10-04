<?php
/** Native local job distribution without new roles, external services or job deletion. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

function mcp_wpml_job_management_allowed(): bool {
	return current_user_can('administrator') || current_user_can('manage_translations') || current_user_can('translate');
}

/** Use native translator records and exact language pairs. Omit emails and user metadata. */
function mcp_wpml_local_translators(string $source, string $target): array {
	$native = wpml_tm_load_blog_translators();
	$out = array();
	foreach ($native->get_blog_translators(array('from' => $source, 'to' => $target)) as $user) {
		$id = (int) $user->ID;
		$pairs = $native->get_language_pairs($id);
		if (user_can($id, 'translate') && !empty($pairs[$source][$target])) {
			$out[] = array('id' => $id, 'display_name' => (string) $user->display_name,
				'source_language' => $source, 'target_language' => $target);
		}
	}
	return $out;
}

function mcp_wpml_list_local_translators($input = array()) {
	try {
		$source = (string) ($input['source_language'] ?? '');
		$target = (string) ($input['target_language'] ?? '');
		$languages = mcp_wpml_site_languages();
		if ($source === $target || !in_array($source, $languages['active_codes'], true) || !in_array($target, $languages['active_codes'], true)) { return new WP_Error('mcp_wpml_invalid_language', 'Select two different active native languages.'); }
		$translators = mcp_wpml_local_translators($source, $target);
		return array('success' => true, 'total' => count($translators), 'translators' => array_slice($translators, max(0, (int) ($input['offset'] ?? 0)), max(1, min(100, (int) ($input['limit'] ?? 25)))), 'new_roles_granted' => false);
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_translators_unavailable', 'Native translator inventory could not be read.'); }
}

/** Find the exact current native post job; never read/return its package or URLs. */
function mcp_wpml_distribution_job_state(int $source_id, int $job_id): array {
	$rows = mcp_wpml_job_rows(array('source_ids' => array($source_id), 'include_history' => false));
	foreach ($rows as $row) {
		if ('post' === $row['type'] && $job_id === $row['job_id']) {
			$entity = wpml_tm_get_jobs_repository(true, false, true)->get_job($row['rid'], 'post');
			if (!$entity || (int) $entity->get_translate_job_id() !== $job_id) { throw new RuntimeException('Native job changed.'); }
			$row['translator_id'] = (int) $entity->get_translator_id();
			$row['editor'] = (string) $entity->get_editor();
			return $row;
		}
	}
	throw new RuntimeException('Current native job not found.');
}

function mcp_wpml_assign_local_translation_job($input = array()) {
	if (!mcp_wpml_job_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native translation-management permission is required.'); }
	try {
		$source_id = (int) ($input['source_id'] ?? 0);
		$job_id = (int) ($input['job_id'] ?? 0);
		$translator_id = (int) ($input['translator_id'] ?? 0);
		$job = mcp_wpml_distribution_job_state($source_id, $job_id);
		if (!current_user_can('edit_post', $source_id)) { return new WP_Error('mcp_wpml_permission_denied', 'You must be able to edit the job source.'); }
		if ('local' !== $job['service'] || $job['automatic'] || $job['ate_id'] || 'ate' === $job['editor']
			|| ICL_TM_WAITING_FOR_TRANSLATOR !== $job['local_status']) { return new WP_Error('mcp_wpml_job_not_assignable', 'Only current waiting local manual jobs can be assigned. Started, completed and external jobs are preserved.'); }
		$translators = mcp_wpml_local_translators($job['source_language'], $job['target_language']);
		if (!in_array($translator_id, array_column($translators, 'id'), true)) { return new WP_Error('mcp_wpml_invalid_translator', 'Select an existing native translator with the exact language pair. No role or language pair is added.'); }
		$hash = hash('sha256', (string) wp_json_encode($job));
		if (true !== ($input['confirm'] ?? false)) {
			return array('success' => true, 'applied' => false, 'job' => $job, 'job_hash' => $hash,
				'proposed_translator_id' => $translator_id, 'may_send_native_notifications' => true);
		}
		if (($input['expected_job_hash'] ?? '') !== $hash) { return new WP_Error('mcp_wpml_stale_job', 'The job or assignment changed after review.'); }
		if ($translator_id === $job['translator_id']) { return array('success' => true, 'applied' => false, 'unchanged' => true, 'job' => $job); }
		$result = wpml_tm_assign_translation_job($job_id, $translator_id, 'local', 'post');
		$after = mcp_wpml_distribution_job_state($source_id, $job_id);
		return array('success' => true === $result && $translator_id === $after['translator_id'], 'applied' => true,
			'before' => $job, 'after' => $after, 'may_send_native_notifications' => true, 'new_roles_granted' => false);
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_assignment_failed', 'Native job assignment failed. Re-read the selected job before retrying.'); }
}

/** First local job only. WPML resend cancels previous jobs, so that workflow is excluded. */
function mcp_wpml_create_local_translation_job($input = array()) {
	if (!mcp_wpml_job_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native translation-management permission is required.'); }
	try {
		$source_id = (int) ($input['source_id'] ?? 0);
		$target = (string) ($input['target_language'] ?? '');
		$translator_id = (int) ($input['translator_id'] ?? 0);
		$post = get_post($source_id);
		if (!$post || in_array($post->post_type, array('revision', 'nav_menu_item', 'attachment'), true)
			|| 'trash' === $post->post_status) { return new WP_Error('mcp_wpml_invalid_job_source', 'Select a supported source post that is not in trash.'); }
		if (!current_user_can('edit_post', $source_id)) { return new WP_Error('mcp_wpml_permission_denied', 'You must be able to edit the source post.'); }
		global $sitepress;
		if (!$sitepress->is_translated_post_type($post->post_type)) { return new WP_Error('mcp_wpml_post_type_not_translated', 'Enable translation for this post type in native WPML settings first.'); }
		$details = mcp_wpml_lang_details($source_id, (string) $post->post_type);
		$languages = mcp_wpml_site_languages();
		if (!$details || empty($details->trid) || !empty($details->source_language_code)
			|| !in_array($target, $languages['active_codes'], true) || $target === $details->language_code) { return new WP_Error('mcp_wpml_invalid_job_source', 'Select an original source and a different active target language.'); }
		if (!in_array($translator_id, array_column(mcp_wpml_local_translators((string) $details->language_code, $target), 'id'), true)) { return new WP_Error('mcp_wpml_invalid_translator', 'An existing native translator with this exact language pair is required.'); }
		if (\WPML_TM_ATE_Status::is_enabled() || \WPML\Setup\Option::shouldTranslateEverything()) { return new WP_Error('mcp_wpml_manual_mode_required', 'Native manual mode with ATE disabled is required. No mode or account is changed automatically.'); }
		$rows = mcp_wpml_job_rows(array('source_ids' => array($source_id), 'include_history' => true));
		foreach ($rows as $row) {
			if ('post' === $row['type'] && $target === $row['target_language']) { return new WP_Error('mcp_wpml_existing_job', 'A current or historical job already exists for this target. Native resend can cancel previous work and is outside this first-job operation.'); }
		}
		$type = mcp_wpml_element_type_for_post_type((string) $post->post_type);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public native WPML group lookup.
		$group = apply_filters('wpml_get_element_translations', null, (int) $details->trid, $type);
		if (!is_array($group)) { throw new RuntimeException('Group unavailable.'); }
		foreach ($group as $member) {
			if ($target === $member->language_code) { return new WP_Error('mcp_wpml_existing_translation', 'This target already has a native translation relationship or content. Use native translation review rather than replacing it through first-job dispatch.'); }
		}
		global $iclTranslationManagement;
		$native_digest = $iclTranslationManagement->post_md5($post);
		if (!is_string($native_digest) || '' === $native_digest) { throw new RuntimeException('Native source fingerprint unavailable.'); }
		$hash = hash('sha256', $native_digest);
		if (true !== ($input['confirm'] ?? false)) {
			return array('success' => true, 'created' => false, 'source_id' => $source_id, 'target_language' => $target,
				'translator_id' => $translator_id, 'source_hash' => $hash, 'may_send_native_notifications' => true,
				'note' => 'First local manual job only. Existing content, current jobs and job history block dispatch.');
		}
		if (($input['expected_source_hash'] ?? '') !== $hash) { return new WP_Error('mcp_wpml_stale_job_source', 'The source changed after review.'); }
		$job_id = wpml_tm_load_job_factory()->create_local_post_job($source_id, $target, $translator_id);
		if (!$job_id) { return new WP_Error('mcp_wpml_dispatch_failed', 'Native WPML did not confirm a new local job. Re-read the selected queue.'); }
		$after = mcp_wpml_distribution_job_state($source_id, (int) $job_id);
		return array('success' => 'local' === $after['service'] && !$after['automatic'] && !$after['ate_id'] && 'ate' !== $after['editor']
			&& $target === $after['target_language'] && $translator_id === $after['translator_id'],
			'created' => true, 'job' => $after, 'may_send_native_notifications' => true, 'new_roles_granted' => false);
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_dispatch_failed', 'A native local-job operation failed. Re-read the selected queue to check partial changes.'); }
}

function mcp_wpml_register_job_distribution_abilities(): void {
	$id = array('type' => 'integer', 'minimum' => 1);
	$lang = array('type' => 'string', 'minLength' => 1, 'maxLength' => 7);
	$hash = array('type' => 'string', 'pattern' => '^[a-f0-9]{64}$');
	$confirm = array('type' => 'boolean', 'default' => false);
	$permission = static fn(): bool => current_user_can('manage_options') && mcp_wpml_job_management_allowed();
	mcp_wpml_register_native_admin_definitions(array(
		'list-local-translators' => array('List Local Translators', 'Read existing native translators for an exact language pair. Do not grant roles or return emails/user metadata.', 'mcp_wpml_list_local_translators', array('source_language' => $lang, 'target_language' => $lang, 'offset' => array('type' => 'integer', 'minimum' => 0, 'default' => 0), 'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25)), array('source_language', 'target_language'), true, false, true, $permission),
		'assign-local-translation-job' => array('Assign Local Translation Job', 'Preview or assign a current waiting local manual post job through WPML. Verify the reviewed job hash and exact native language pair. Native notifications may be sent.', 'mcp_wpml_assign_local_translation_job', array('source_id' => $id, 'job_id' => $id, 'translator_id' => $id, 'expected_job_hash' => $hash, 'confirm' => $confirm), array('source_id', 'job_id', 'translator_id'), false, false, true, $permission),
		'create-local-translation-job' => array('Create Local Translation Job', 'Preview or dispatch the first local manual job for a source/target through WPML. Require manual mode, an existing translator and reviewed source hash. Existing content/job history blocks resend. Native notifications may be sent.', 'mcp_wpml_create_local_translation_job', array('source_id' => $id, 'target_language' => $lang, 'translator_id' => $id, 'expected_source_hash' => $hash, 'confirm' => $confirm), array('source_id', 'target_language', 'translator_id'), false, false, true, $permission),
	));
}
