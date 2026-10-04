<?php
/** Native String Translation inventory and selected translation updates. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

function mcp_wpml_string_management_allowed(): bool {
	return current_user_can('wpml_manage_string_translation') || current_user_can('manage_translations');
}

/** Registered option strings remain in the secure native UI, outside this content Interface. */
function mcp_wpml_string_domain_allowed(string $domain): bool {
	return '' !== trim($domain) && strlen($domain) <= 160 && !str_starts_with($domain, 'admin_texts_');
}

/** Use the existing native read-only REST API in-process with the same current user. */
function mcp_wpml_list_strings($input = array()) {
	if (!mcp_wpml_string_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native String Translation management permission is required.'); }
	$domain = (string) ($input['domain'] ?? '');
	if (!mcp_wpml_string_domain_allowed($domain)) { return new WP_Error('mcp_wpml_string_scope_required', 'Select a content string domain. Registered admin-option strings require the native secure interface.'); }
	try {
		$request = new WP_REST_Request('GET', '/wpml/st/v1/strings');
		// WPML validates its native REST nonce as well as the same user's capabilities.
		$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
		$request->set_param('domain', $domain);
		$request->set_param('limit', max(1, min(100, (int) ($input['limit'] ?? 25))));
		$request->set_param('offset', max(0, (int) ($input['offset'] ?? 0)));
		foreach (array('search' => 'title', 'source_language' => 'sourceLanguageCode', 'target_language' => 'targetLanguageCode') as $name => $native) {
			if (isset($input[$name])) { $request->set_param($native, (string) $input[$name]); }
		}
		$response = rest_do_request($request);
		if (is_wp_error($response) || $response->get_status() >= 400) { return new WP_Error('mcp_wpml_string_inventory_unavailable', 'Native String Translation inventory is unavailable or denied. No alternative query or access is used.'); }
		$data = $response->get_data();
		if (!is_array($data) || ($data && array_keys($data) !== range(0, count($data) - 1))) { throw new RuntimeException('Unexpected native string inventory.'); }
		$strings = array();
		foreach ($data as $row) {
			if (!is_array($row) || ($row['domain'] ?? '') !== $domain) { throw new RuntimeException('Native domain mismatch.'); }
			$strings[] = array('id' => (int) $row['id'], 'domain' => $domain, 'context' => $row['context'] ?? null,
				'name' => (string) ($row['name'] ?? ''), 'source_language' => (string) ($row['language'] ?? ''),
				'source_value' => (string) ($row['title'] ?? ''), 'status' => (int) ($row['status'] ?? 0));
		}
		return array('success' => true, 'strings' => $strings, 'returned_count' => count($strings),
			'offset' => $request->get_param('offset'), 'limit' => $request->get_param('limit'),
			'total_count_known' => false, 'source' => 'native_wpml_string_api');
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_string_inventory_unavailable', 'Native string inventory could not be read safely.'); }
}

/** Selected getters only; raw storage rows and admin-option values are never returned. */
function mcp_wpml_string_state(int $id, string $domain): array {
	if (!mcp_wpml_string_domain_allowed($domain)) { throw new RuntimeException('Content domain required.'); }
	$factory = \WPML\Container\make('WPML_ST_String_Factory');
	$string = $factory->find_by_id($id);
	if (!$string->exists() || $domain !== $string->get_context()) { throw new RuntimeException('String identity mismatch.'); }
	$source = array('id' => $id, 'domain' => $domain, 'context' => $string->get_gettext_context(),
		'name' => (string) $string->get_name(), 'language' => (string) $string->get_language(), 'value' => (string) $string->get_value());
	$translations = array();
	foreach ($string->get_translations() as $translation) {
		$translation = (array) $translation;
		$translations[(string) $translation['language']] = array('value' => (string) ($translation['value'] ?? ''), 'status' => (int) ($translation['status'] ?? 0));
	}
	return array('source' => $source, 'source_hash' => hash('sha256', (string) wp_json_encode($source)), 'translations' => $translations);
}

function mcp_wpml_get_string($input = array()) {
	if (!mcp_wpml_string_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native String Translation management permission is required.'); }
	try { return array('success' => true) + mcp_wpml_string_state((int) ($input['id'] ?? 0), (string) ($input['domain'] ?? '')); }
	catch (Throwable $error) { return new WP_Error('mcp_wpml_string_unavailable', 'The selected content string could not be read. Check its ID and domain in the native inventory.'); }
}

function mcp_wpml_update_string_translation($input = array()) {
	if (!mcp_wpml_string_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native String Translation management permission is required.'); }
	try {
		$id = (int) ($input['id'] ?? 0);
		$before = mcp_wpml_string_state($id, (string) ($input['domain'] ?? ''));
		$language = (string) ($input['target_language'] ?? '');
		$languages = mcp_wpml_site_languages();
		if (!in_array($language, $languages['active_codes'], true) || $language === $before['source']['language']) { return new WP_Error('mcp_wpml_invalid_language', 'Select an active target language different from the source.'); }
		$target = $before['translations'][$language] ?? null;
		if ($target && in_array($target['status'], array(ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS), true)) { return new WP_Error('mcp_wpml_string_job_active', 'The native target is waiting or in progress/partial. Preserve it and use native String Translation or job review first.'); }
		$state = (string) ($input['state'] ?? 'draft');
		if (!in_array($state, array('draft', 'complete'), true) || !is_string($input['value'] ?? null) || strlen($input['value']) > 200000) { return new WP_Error('mcp_wpml_invalid_string_value', 'Provide bounded translation text and a supported state.'); }
		$target_hash = hash('sha256', (string) wp_json_encode($target));
		if (true !== ($input['confirm'] ?? false)) {
			return array('success' => true, 'applied' => false, 'before' => $before, 'target_hash' => $target_hash,
				'target_language' => $language, 'proposed_value' => $input['value'], 'proposed_state' => $state);
		}
		if (($input['expected_source_hash'] ?? '') !== $before['source_hash'] || ($input['expected_target_hash'] ?? '') !== $target_hash) { return new WP_Error('mcp_wpml_stale_string', 'The source or target string changed after review.'); }
		$status = 'complete' === $state ? ICL_TM_COMPLETE : ICL_TM_NOT_TRANSLATED;
		if ($target && $target['value'] === $input['value'] && $target['status'] === $status) { return array('success' => true, 'applied' => false, 'unchanged' => true); }
		if (!function_exists('icl_add_string_translation')) { return new WP_Error('mcp_wpml_string_writer_unavailable', 'Native String Translation writer is unavailable.'); }
		$translation_id = icl_add_string_translation($id, $language, $input['value'], $status);
		if (!$translation_id) { return new WP_Error('mcp_wpml_string_write_failed', 'Native String Translation did not confirm the update. Re-read the selected string.'); }
		$after = mcp_wpml_string_state($id, $before['source']['domain']);
		// Native WPML can normalize line breaks and links. Return actual selected text for review.
		$written = $after['translations'][$language] ?? null;
		$native_string = \WPML\Container\make('WPML_ST_String_Factory')->find_by_id($id);
		$value_verified = $written && $written['value'] === $native_string->normalize_line_breaks($input['value']);
		return array('success' => $before['source_hash'] === $after['source_hash'] && $written && $written['status'] === $status && $value_verified,
			'applied' => true, 'translation_id' => (int) $translation_id, 'before' => $before, 'after' => $after,
			'value_verified' => (bool) $value_verified, 'native_adjustments_require_review' => !$value_verified,
			'note' => 'WPML retains its native string lifecycle, link normalization and translation-file generation. Review the actual saved value.');
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_string_write_failed', 'A native string operation failed. Re-read the selected string to check partial changes.'); }
}

function mcp_wpml_register_string_administration_abilities(): void {
	$domain = array('type' => 'string', 'minLength' => 1, 'maxLength' => 160);
	$id = array('type' => 'integer', 'minimum' => 1);
	$lang = array('type' => 'string', 'minLength' => 1, 'maxLength' => 7);
	$hash = array('type' => 'string', 'pattern' => '^[a-f0-9]{64}$');
	$permission = static fn(): bool => current_user_can('manage_options') && mcp_wpml_string_management_allowed();
	mcp_wpml_register_native_admin_definitions(array(
		'list-strings' => array('List WPML Strings', 'Read a bounded native content-string domain using the existing WPML API and current permissions. Admin-option strings remain in the secure native UI.', 'mcp_wpml_list_strings', array('domain' => $domain, 'search' => array('type' => 'string', 'maxLength' => 200), 'source_language' => $lang, 'target_language' => $lang, 'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25), 'offset' => array('type' => 'integer', 'minimum' => 0, 'default' => 0)), array('domain'), true, false, true, $permission),
		'get-string' => array('Get WPML String', 'Read a selected content string and its native translations without raw storage or admin-option values.', 'mcp_wpml_get_string', array('id' => $id, 'domain' => $domain), array('id', 'domain'), true, false, true, $permission),
		'update-string-translation' => array('Update String Translation', 'Preview or save selected target text using native WPML. Require reviewed source/target hashes and preserve active job progress.', 'mcp_wpml_update_string_translation', array('id' => $id, 'domain' => $domain, 'target_language' => $lang, 'value' => array('type' => 'string', 'maxLength' => 200000), 'state' => array('type' => 'string', 'enum' => array('draft', 'complete'), 'default' => 'draft'), 'expected_source_hash' => $hash, 'expected_target_hash' => $hash, 'confirm' => array('type' => 'boolean', 'default' => false)), array('id', 'domain', 'target_language', 'value'), false, true, true, $permission),
	));
}
