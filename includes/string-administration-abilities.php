<?php
/** Native String Translation inventory and selected translation updates. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

function mcp_wpml_string_management_allowed(): bool {
	return current_user_can('wpml_manage_string_translation') || current_user_can('manage_translations');
}

/** Registered option strings require explicit selection and the native administrator capability. */
function mcp_wpml_string_domain_allowed(string $domain, bool $include_option_strings = false): bool {
	return '' !== trim($domain) && strlen($domain) <= 160
		&& (!str_starts_with($domain, 'admin_texts_') || ($include_option_strings && current_user_can('manage_options')));
}

/** Use the existing native read-only REST API in-process with the same current user. */
function mcp_wpml_list_strings($input = array()) {
	if (!mcp_wpml_string_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native String Translation management permission is required.'); }
	$domain = (string) ($input['domain'] ?? '');
	if (!mcp_wpml_string_domain_allowed($domain, true === ($input['include_option_strings'] ?? false))) { return new WP_Error('mcp_wpml_string_scope_required', 'Select a string domain. Registered option strings require include_option_strings and the native administrator capability.'); }
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

/** Selected native string getters only; never read arbitrary WordPress options or raw storage. */
function mcp_wpml_string_state(int $id, string $domain, bool $include_option_strings = false): array {
	if (!mcp_wpml_string_domain_allowed($domain, $include_option_strings)) { throw new RuntimeException('Explicit permitted string domain required.'); }
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
	try { return array('success' => true) + mcp_wpml_string_state((int) ($input['id'] ?? 0), (string) ($input['domain'] ?? ''), true === ($input['include_option_strings'] ?? false)); }
	catch (Throwable $error) { return new WP_Error('mcp_wpml_string_unavailable', 'The selected string could not be read. Check its ID, domain and option-string selection in the native inventory.'); }
}

function mcp_wpml_update_string_translation($input = array()) {
	if (!mcp_wpml_string_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native String Translation management permission is required.'); }
	try {
		$id = (int) ($input['id'] ?? 0);
		$include_option_strings = true === ($input['include_option_strings'] ?? false);
		$before = mcp_wpml_string_state($id, (string) ($input['domain'] ?? ''), $include_option_strings);
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
		$after = mcp_wpml_string_state($id, $before['source']['domain'], $include_option_strings);
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

/** Fingerprint every native translation field without returning private row metadata. */
function mcp_wpml_string_translation_rows_hash(iterable $translations): string {
	$rows = array();
	foreach ($translations as $translation) {
		$row = (array) $translation;
		foreach ($row as $key => $value) {
			if (null !== $value && !is_scalar($value)) { throw new RuntimeException('Unexpected native translation field.'); }
			$row[$key] = array('type' => gettype($value), 'value' => is_string($value) ? base64_encode($value) : $value);
		}
		ksort($row);
		$encoded = wp_json_encode($row);
		if (false === $encoded) { throw new RuntimeException('Native translation fields could not be fingerprinted.'); }
		$rows[] = $encoded;
	}
	sort($rows, SORT_STRING);
	return hash('sha256', (string) wp_json_encode($rows));
}

/** Correct source language through WPML; preserve text/translation rows and native status calculation. */
function mcp_wpml_update_string_source_language($input = array()) {
	if (!current_user_can('manage_options') || !mcp_wpml_string_management_allowed()) { return new WP_Error('mcp_wpml_permission_denied', 'Native administrator and String Translation permissions are required.'); }
	$write_attempted = false;
	try {
		$id = (int) ($input['id'] ?? 0);
		$options = true === ($input['include_option_strings'] ?? false);
		$before = mcp_wpml_string_state($id, (string) ($input['domain'] ?? ''), $options);
		$language = (string) ($input['source_language'] ?? '');
		$languages = mcp_wpml_site_languages();
		if (!in_array($language, $languages['active_codes'], true)) { return new WP_Error('mcp_wpml_invalid_language', 'Select an active native source language.'); }
		$active_statuses = array(ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS);
		if (defined('ICL_STRING_TRANSLATION_PARTIAL')) { $active_statuses[] = constant('ICL_STRING_TRANSLATION_PARTIAL'); }
		$string = \WPML\Container\make('WPML_ST_String_Factory')->find_by_id($id);
		if (!is_callable(array($string, 'set_language'))) { return new WP_Error('mcp_wpml_string_writer_unavailable', 'Native source-language writer is unavailable.'); }
		$translation_rows = $string->get_translations();
		foreach ($translation_rows as $translation) {
			$translation = (array) $translation;
			if (in_array((int) ($translation['status'] ?? 0), $active_statuses, true)) { return new WP_Error('mcp_wpml_string_job_active', 'Preserve active or partial native string translation work before changing its source language.'); }
		}
		$translations_hash = mcp_wpml_string_translation_rows_hash($translation_rows);
		if (true !== ($input['confirm'] ?? false)) {
			return array('success' => true, 'applied' => false, 'before' => $before, 'translations_hash' => $translations_hash, 'proposed_source_language' => $language, 'native_status_recalculation' => true);
		}
		if (($input['expected_source_hash'] ?? '') !== $before['source_hash'] || ($input['expected_translations_hash'] ?? '') !== $translations_hash) { return new WP_Error('mcp_wpml_stale_string', 'The source or its translations changed after review.'); }
		if ($language === $before['source']['language']) { return array('success' => true, 'applied' => false, 'unchanged' => true); }
		$write_attempted = true;
		$string->set_language($language);
		$after = mcp_wpml_string_state($id, $before['source']['domain'], $options);
		$expected = $before['source']; $expected['language'] = $language;
		$after_hash = mcp_wpml_string_translation_rows_hash($string->get_translations());
		$verified = $after['source'] === $expected && hash_equals($translations_hash, $after_hash);
		return array('success' => $verified, 'applied' => true, 'before' => $before, 'after' => $after, 'translations_hash' => $after_hash,
			'native_status_recalculation' => true, 'message' => $verified ? '' : 'Native readback differs. Inspect the selected string before another write.');
	} catch (Throwable $error) {
		return new WP_Error('mcp_wpml_string_language_write_failed', 'Native source-language update failed. Re-read the selected string before another write.', array('outcome' => $write_attempted ? 'uncertain' : 'not_started'));
	}
}

function mcp_wpml_register_string_administration_abilities(): void {
	$domain = array('type' => 'string', 'minLength' => 1, 'maxLength' => 160);
	$id = array('type' => 'integer', 'minimum' => 1);
	$lang = array('type' => 'string', 'minLength' => 1, 'maxLength' => 7);
	$hash = array('type' => 'string', 'pattern' => '^[a-f0-9]{64}$');
	$options = array('type' => 'boolean', 'default' => false, 'description' => 'Explicitly include the selected registered admin_texts_* domain. Requires native administrator and string-management capabilities. Never reads arbitrary option values.');
	$permission = static fn(): bool => current_user_can('manage_options') && mcp_wpml_string_management_allowed();
	mcp_wpml_register_native_admin_definitions(array(
		'list-strings' => array('List WPML Strings', 'Read a bounded selected native string domain using the existing WPML API and current permissions. Registered option domains require explicit selection.', 'mcp_wpml_list_strings', array('domain' => $domain, 'include_option_strings' => $options, 'search' => array('type' => 'string', 'maxLength' => 200), 'source_language' => $lang, 'target_language' => $lang, 'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25), 'offset' => array('type' => 'integer', 'minimum' => 0, 'default' => 0)), array('domain'), true, false, true, $permission),
		'get-string' => array('Get WPML String', 'Read one selected registered string and its native translations. Registered option domains require explicit selection; raw options are never read.', 'mcp_wpml_get_string', array('id' => $id, 'domain' => $domain, 'include_option_strings' => $options), array('id', 'domain'), true, false, true, $permission),
		'update-string-source-language' => array('Update String Source Language', 'Preview or correct the language of one selected registered source string using native WPML. Preserve its value, translations and active work; require reviewed source and translation hashes.', 'mcp_wpml_update_string_source_language', array('id' => $id, 'domain' => $domain, 'include_option_strings' => $options, 'source_language' => $lang, 'expected_source_hash' => $hash, 'expected_translations_hash' => $hash, 'confirm' => array('type' => 'boolean', 'default' => false)), array('id', 'domain', 'source_language'), false, true, true, $permission),
		'update-string-translation' => array('Update String Translation', 'Preview or save selected content or explicitly selected registered option translation text using native WPML. Require reviewed hashes and preserve active job progress.', 'mcp_wpml_update_string_translation', array('id' => $id, 'domain' => $domain, 'include_option_strings' => $options, 'target_language' => $lang, 'value' => array('type' => 'string', 'maxLength' => 200000), 'state' => array('type' => 'string', 'enum' => array('draft', 'complete'), 'default' => 'draft'), 'expected_source_hash' => $hash, 'expected_target_hash' => $hash, 'confirm' => array('type' => 'boolean', 'default' => false)), array('id', 'domain', 'target_language', 'value'), false, true, true, $permission),
	));
}
