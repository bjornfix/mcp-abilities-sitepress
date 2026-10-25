<?php
/** Selected native WPML site controls. No registration secrets or generic option access. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/** Administrative language state includes active languages hidden from the frontend. */
function mcp_wpml_site_languages(): array {
	global $sitepress;
	$default = (string) $sitepress->get_default_language();
	$all = $sitepress->get_languages($default, false, true);
	$active = $sitepress->get_languages($default, true, true);
	if (!is_array($all) || !is_array($active)) {
		throw new RuntimeException('Native language inventory unavailable.');
	}
	$rows = array();
	foreach ($all as $code => $language) {
		$language = (array) $language;
		$rows[] = array('code' => (string) $code, 'english_name' => (string) ($language['english_name'] ?? ''),
			'locale' => (string) ($language['default_locale'] ?? ''), 'hreflang' => (string) ($language['tag'] ?? ''),
			'active' => isset($active[$code]), 'default' => $code === $default);
	}
	return array('default_language' => $default, 'active_codes' => array_keys($active), 'languages' => $rows);
}

function mcp_wpml_get_site_languages($input = array()) {
	try { return array('success' => true) + mcp_wpml_site_languages(); }
	catch (Throwable $error) { return new WP_Error('mcp_wpml_languages_unavailable', 'Native language inventory could not be read.'); }
}

/** Add a custom language through WPML; leave activation to the separate native control. */
function mcp_wpml_add_site_language($input = array()) {
	if (true !== ($input['confirm'] ?? false)) { return new WP_Error('mcp_wpml_confirmation_required', 'Confirm creation of the reviewed custom language.'); }
	try {
		$code = (string) ($input['code'] ?? '');
		$name = sanitize_text_field(trim((string) ($input['english_name'] ?? '')));
		$locale = (string) ($input['locale'] ?? '');
		$tag = (string) ($input['hreflang'] ?? '');
		if (!preg_match('/^[a-z][a-z0-9-]{0,6}$/D', $code) || '' === $name || strlen($name) > 128
			|| !preg_match('/^[A-Za-z][A-Za-z0-9_@-]{1,34}$/D', $locale)
			|| !preg_match('/^[A-Za-z][A-Za-z0-9-]{0,19}$/D', $tag)) {
			return new WP_Error('mcp_wpml_invalid_language', 'Provide a valid native language code, name, locale and hreflang.');
		}
		$before = mcp_wpml_site_languages();
		foreach ($before['languages'] as $language) {
			if ($code === $language['code']) {
				if ($name !== $language['english_name'] || $locale !== $language['locale'] || $tag !== $language['hreflang']) {
					return new WP_Error('mcp_wpml_language_conflict', 'This code already identifies a different native language.');
				}
				return array('success' => true, 'created' => false, 'language' => $language);
			}
		}
		$id = \WPML\Element\API\Languages::add($code, $name, $locale, 0, 0, 0, $tag);
		if (!$id) { return new WP_Error('mcp_wpml_language_creation_failed', 'Native WPML language creation failed.'); }
		icl_cache_clear();
		$after = mcp_wpml_site_languages();
		foreach ($after['languages'] as $language) {
			if ($code === $language['code']) {
				return array('success' => $name === $language['english_name'] && $locale === $language['locale']
					&& $tag === $language['hreflang'] && !$language['active'], 'created' => true, 'language' => $language);
			}
		}
		return new WP_Error('mcp_wpml_language_readback_failed', 'The new language could not be verified. Re-read native language state before retrying.');
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_language_creation_failed', 'A native language operation failed. Re-read state to check partial changes.'); }
}

/** Native deactivation preserves translations; no language/content deletion is offered. */
function mcp_wpml_set_active_site_languages($input = array()) {
	try {
		$before = mcp_wpml_site_languages();
		$codes = array_values((array) ($input['codes'] ?? array()));
		$known = array_column($before['languages'], 'code');
		if (!$codes || count($codes) !== count(array_unique($codes)) || array_diff($codes, $known)
			|| !in_array($before['default_language'], $codes, true)) {
			return new WP_Error('mcp_wpml_invalid_language_selection', 'Select known languages and retain the current default language.');
		}
		$removed = array_values(array_diff($before['active_codes'], $codes));
		$preview = array('success' => true, 'before' => $before['active_codes'], 'after' => $codes,
			'deactivated_codes' => $removed, 'content_deleted' => false,
			'note' => 'Deactivated languages disappear from public language navigation. Their translations remain stored. Native language hooks may trigger configured integrations.');
		if (true !== ($input['confirm'] ?? false)) { return $preview + array('applied' => false); }
		if (!mcp_wpml_same_code_set((array) ($input['expected_active_codes'] ?? array()), $before['active_codes'])) {
			return new WP_Error('mcp_wpml_stale_languages', 'Active language selection changed after review.');
		}
		if (mcp_wpml_same_code_set($codes, $before['active_codes'])) { return $preview + array('applied' => false, 'unchanged' => true); }
		global $sitepress;
		$old_native_languages = $sitepress->get_languages($before['default_language'], true, true);
		$setup = wpml_get_setup_instance();
		if (!$setup->set_active_languages($codes)) { return new WP_Error('mcp_wpml_language_update_failed', 'Native language selection could not be saved.'); }
		icl_cache_clear();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native compatibility hook still used by String Translation.
		do_action('icl_update_active_languages');
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML lifecycle hook.
		do_action('wpml_update_active_languages', $old_native_languages);
		$after = mcp_wpml_site_languages();
		return array('success' => mcp_wpml_same_code_set($codes, $after['active_codes']), 'applied' => true,
			'before' => $before['active_codes'], 'after' => $after['active_codes'], 'content_deleted' => false);
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_language_update_failed', 'A native language update failed. Re-read state to check partial changes.'); }
}

function mcp_wpml_same_code_set(array $a, array $b): bool { sort($a); sort($b); return $a === $b; }

function mcp_wpml_set_default_site_language($input = array()) {
	try {
		$before = mcp_wpml_site_languages();
		$code = (string) ($input['code'] ?? '');
		if (!in_array($code, $before['active_codes'], true)) { return new WP_Error('mcp_wpml_invalid_language', 'The default must be an active native language.'); }
		if (true !== ($input['confirm'] ?? false)) {
			return array('success' => true, 'applied' => false, 'before' => $before['default_language'], 'after' => $code,
				'note' => 'Native default-language changes also update WordPress locale and native user-language preferences.');
		}
		if ($before['default_language'] !== ($input['expected_default'] ?? '')) { return new WP_Error('mcp_wpml_stale_languages', 'Default language changed after review.'); }
		if ($before['default_language'] === $code) { return array('success' => true, 'applied' => false, 'unchanged' => true); }
		global $sitepress;
		$result = $sitepress->set_default_language($code);
		$after = mcp_wpml_site_languages();
		return array('success' => $code === $after['default_language'], 'applied' => true, 'before' => $before['default_language'],
			'after' => $after['default_language'], 'locale_pack_missing' => 1 === $result);
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_language_update_failed', 'Native default-language change failed. Re-read state to check partial changes.'); }
}

/** Register missing term language metadata or safely connect an existing term. */
function mcp_wpml_set_term_language_details($input = array()) {
	try {
		$id = (int) ($input['term_id'] ?? 0);
		$taxonomy = (string) ($input['taxonomy'] ?? '');
		$language = (string) ($input['language_code'] ?? '');
		$term = get_term($id, $taxonomy);
		$tax = get_taxonomy($taxonomy);
		if (!$tax || 'nav_menu' === $taxonomy || !$term || is_wp_error($term)) { return new WP_Error('mcp_wpml_invalid_term', 'A supported term and taxonomy are required.'); }
		if (!current_user_can('edit_term', $id) || !current_user_can($tax->cap->edit_terms)) { return new WP_Error('mcp_wpml_permission_denied', 'Native term editing permission is required.'); }
		global $sitepress;
		if (!$sitepress->is_translated_taxonomy($taxonomy)) { return new WP_Error('mcp_wpml_taxonomy_not_translated', 'Enable translation for this taxonomy in native WPML settings first.'); }
		$state = mcp_wpml_site_languages();
		if (!in_array($language, $state['active_codes'], true)) { return new WP_Error('mcp_wpml_invalid_language', 'Term language must be active.'); }
		$existing = mcp_wpml_term_lang_details($id, $taxonomy);
		$source_id = (int) ($input['source_term_id'] ?? 0);
		$trid = false;
		$source_language = null;
		$type = mcp_wpml_element_type_for_taxonomy($taxonomy);
		if ($source_id) {
			$source = get_term($source_id, $taxonomy);
			$details = mcp_wpml_term_lang_details($source_id, $taxonomy);
			if (!$source || is_wp_error($source) || $source_id === $id || !current_user_can('edit_term', $source_id)
				|| !$details || empty($details->trid) || !empty($details->source_language_code)
				|| $language === $details->language_code) { return new WP_Error('mcp_wpml_invalid_term_source', 'Select an editable original term in the same taxonomy and a different language.'); }
			$trid = (int) $details->trid;
			$source_language = (string) $details->language_code;
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML group lookup.
			$group = apply_filters('wpml_get_element_translations', null, $trid, $type);
			if (!is_array($group)) { throw new RuntimeException('Group unavailable.'); }
			foreach ($group as $member) {
				if ($language === $member->language_code && (int) $member->element_id !== (int) $term->term_taxonomy_id) { return new WP_Error('mcp_wpml_term_conflict', 'The source group already has a different term in this language.'); }
			}
		}
		if ($existing && !empty($existing->trid)) {
			if ($language === $existing->language_code && (!$source_id || $trid === (int) $existing->trid)) {
				return array('success' => true, 'applied' => false, 'unchanged' => true, 'term_id' => $id, 'trid' => (int) $existing->trid);
			}
			if (!$source_id || $language !== $existing->language_code) { return new WP_Error('mcp_wpml_term_conflict', 'Existing term language is different. No relationship was changed.'); }
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Native WPML group lookup.
			$old_group = apply_filters('wpml_get_element_translations', null, (int) $existing->trid, $type);
			if (!is_array($old_group) || count($old_group) > 1) { return new WP_Error('mcp_wpml_term_conflict', 'The term already belongs to another linked group.'); }
		}
		$preview = array('success' => true, 'applied' => false, 'term_id' => $id, 'term_taxonomy_id' => (int) $term->term_taxonomy_id,
			'taxonomy' => $taxonomy, 'language_code' => $language, 'source_language_code' => $source_language, 'trid' => $trid);
		if (true !== ($input['confirm'] ?? false)) { return $preview; }
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public WPML language mutation hook.
		do_action('wpml_set_element_language_details', array('element_id' => (int) $term->term_taxonomy_id, 'element_type' => $type,
			'trid' => $trid, 'language_code' => $language, 'source_language_code' => $source_language, 'check_duplicates' => true));
		clean_term_cache($id, $taxonomy);
		$after = mcp_wpml_term_lang_details($id, $taxonomy);
		$preview['applied'] = true;
		$preview['success'] = $after && $after->language_code === $language && (!$trid || (int) $after->trid === $trid);
		$preview['trid'] = $after ? (int) $after->trid : null;
		return $preview;
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_term_update_failed', 'Native term relationship update failed. Re-read the group to check partial changes.'); }
}

function mcp_wpml_link_term_translation($input = array()) {
	return mcp_wpml_set_term_language_details(array('term_id' => $input['target_term_id'] ?? 0, 'source_term_id' => $input['source_term_id'] ?? 0,
		'taxonomy' => $input['taxonomy'] ?? '', 'language_code' => $input['target_language'] ?? '', 'confirm' => $input['confirm'] ?? false));
}

/** Selected scalar settings only; registration, deletion, domains and raw options are excluded. */
function mcp_wpml_general_setting_schema(): array {
	$schema = array();
	foreach (array('sync_page_ordering', 'sync_page_parent', 'sync_page_template', 'sync_ping_status', 'sync_comment_status',
		'sync_sticky_flag', 'sync_post_format', 'sync_post_date', 'tm_block_retranslating_terms') as $name) { $schema[$name] = array('type' => 'boolean'); }
	$schema['translated_document_page_url'] = array('type' => 'string', 'enum' => array('auto-generate', 'copy-encoded', 'translate'));
	$schema['translation_editor'] = array('type' => 'string', 'enum' => array('advanced', 'classic'));
	return $schema;
}

function mcp_wpml_general_settings(): array {
	global $sitepress;
	$out = array();
	foreach (mcp_wpml_general_setting_schema() as $name => $schema) {
		if ('translation_editor' === $name) {
			$tm = (array) $sitepress->get_setting('translation-management', array());
			$method = $tm['doc_translation_method'] ?? null;
			$out[$name] = \WPML_TM_Editors::ATE === $method ? 'advanced' : (ICL_TM_TMETHOD_EDITOR === $method ? 'classic' : null);
			continue;
		}
		$value = $sitepress->get_setting($name, null);
		$out[$name] = null === $value ? null : ('boolean' === $schema['type'] ? (bool) $value : (string) $value);
	}
	return $out;
}

function mcp_wpml_get_general_settings($input = array()) {
	try {
		$settings = mcp_wpml_general_settings();
		return array('success' => true, 'settings' => $settings, 'settings_hash' => hash('sha256', (string) wp_json_encode($settings)), 'allowed_settings' => array_keys(mcp_wpml_general_setting_schema()));
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_settings_unavailable', 'Selected native settings could not be read. No raw options or account data are returned.'); }
}

function mcp_wpml_update_general_settings($input = array()) {
	$before = null;
	$operation = 'preflight';
	$write_attempted = false;
	try {
		$changes = (array) ($input['changes'] ?? array());
		$schema = mcp_wpml_general_setting_schema();
		if (!$changes || array_diff_key($changes, $schema)) { return new WP_Error('mcp_wpml_setting_not_allowed', 'Only the listed native settings may be changed.'); }
		foreach ($changes as $name => $value) {
			if (('boolean' === $schema[$name]['type'] && !is_bool($value))
				|| ('string' === $schema[$name]['type'] && !in_array($value, $schema[$name]['enum'], true))) { return new WP_Error('mcp_wpml_invalid_setting', 'A selected setting has an invalid value.'); }
		}
		$editor = isset($changes['translation_editor']);
		if ($editor && 1 !== count($changes)) { return new WP_Error('mcp_wpml_editor_scope', 'Change the translation editor on its own. No controls were changed.'); }
		$before = mcp_wpml_get_general_settings();
		if (is_wp_error($before)) { return $before; }
		$controls_before = $editor ? mcp_wpml_translation_controls() : null;
		if (true !== ($input['confirm'] ?? false)) { return array('success' => true, 'applied' => false, 'before' => $before, 'changes' => $changes, 'controls_before' => $controls_before); }
		if (($input['expected_settings_hash'] ?? '') !== $before['settings_hash']) { return new WP_Error('mcp_wpml_stale_settings', 'Settings changed after review.'); }
		if ($editor && 'advanced' === $changes['translation_editor'] && 'advanced' !== $before['settings']['translation_editor']) {
			if ($controls_before['translate_everything'] || $controls_before['translate_drafts']) {
				return new WP_Error('mcp_wpml_automation_active', 'Disable automatic pickup and drafts before changing to the Advanced Translation Editor. No controls were changed.');
			}
			// Enabling ATE can restore delivery eligibility for existing work.
			$snapshot = mcp_wpml_job_snapshot(array('include_history' => true, 'check_ate' => false));
			foreach ($snapshot['rows'] as $row) {
				if (!$row['local_completed'] && $row['ate_id'] > 0) {
					return new WP_Error('mcp_wpml_editor_delivery_risk', 'Unfinished ATE work exists. Review its native delivery state before enabling ATE. No controls were changed.');
				}
			}
		}
		foreach ($changes as $name => $value) {
			if ($before['settings'][$name] === $value) { continue; }
			$operation = $name;
			$write_attempted = true;
			if ('translation_editor' === $name) {
				\WPML\API\Settings::assoc('translation-management', 'doc_translation_method', 'advanced' === $value ? \WPML_TM_Editors::ATE : ICL_TM_TMETHOD_EDITOR);
			} else {
				\WPML\API\Settings::setAndSave($name, $value);
			}
		}
		$operation = 'readback';
		if ($write_attempted && !$editor) { icl_cache_clear(); }
		$after = mcp_wpml_get_general_settings();
		if (is_wp_error($after)) { throw new RuntimeException('Readback unavailable.'); }
		$verified = true;
		foreach ($changes as $name => $value) { $verified = $verified && $after['settings'][$name] === $value; }
		$controls_after = $editor ? mcp_wpml_translation_controls() : null;
		$control_diff = $editor ? mcp_wpml_control_changes($controls_before, $controls_after) : array();
		if ($editor) { $verified = $verified && !array_diff(array_keys($control_diff), array('document_editor', 'ate_enabled')); }
		return array('success' => $verified, 'applied' => $before['settings'] !== $after['settings'], 'before' => $before, 'after' => $after,
			'changes' => mcp_wpml_control_changes($before['settings'], $after['settings']),
			'controls_before' => $controls_before, 'controls_after' => $controls_after, 'control_changes' => $control_diff,
			'write_outcome' => 'read_back', 'verification_required' => !$verified);
	} catch (Throwable $error) {
		if (!$write_attempted) { return new WP_Error('mcp_wpml_settings_preflight_failed', 'Native settings or job inventory could not be read. No settings were changed.'); }
		$after = mcp_wpml_get_general_settings();
		$readable = !is_wp_error($after);
		return array('success' => false, 'before' => $before, 'after' => $readable ? $after : null,
			'changes' => $readable ? mcp_wpml_control_changes($before['settings'], $after['settings']) : null,
			'partial' => $readable ? $before['settings'] !== $after['settings'] : null,
			'failed_operation' => $operation, 'write_outcome' => $readable ? 'read_back' : 'unknown', 'verification_required' => true);
	}
}

function mcp_wpml_get_registration_status($input = array()) {
	try {
		$available = class_exists('WP_Installer_API');
		return array('success' => true, 'installer_available' => $available,
			'site_key_present' => $available ? (bool) \WP_Installer_API::get_site_key('wpml') : null,
			'license_validity' => 'not_checked', 'account_credentials_returned' => false);
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_registration_status_unavailable', 'Native registration status is unavailable. No account data is returned.'); }
}

function mcp_wpml_get_registration_workflow($input = array()) {
	$status = mcp_wpml_get_registration_status();
	if (is_wp_error($status)) { return $status; }
	try {
		return $status + array('requires_secure_user_step' => true,
			'native_registration_url' => $status['installer_available'] ? \WP_Installer_API::get_product_installer_link('wpml') : null,
			'steps' => array('Open the native WPML Installer registration screen using your existing WordPress login.',
				'Check the correct site in your WPML account and obtain its site key there.',
				'Enter or replace the key only in the native registration form. Do not put it in an MCP request or chat.',
				'After native registration finishes, re-read the selected registration status.'),
			'account_connection_changed' => false, 'registration_performed' => false);
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_registration_workflow_unavailable', 'Native registration workflow is unavailable. No account data is returned.'); }
}

function mcp_wpml_get_diagnostics($input = array()) {
	try {
		$functions = array('strings' => function_exists('icl_add_string_translation') && class_exists('WPML_ST_String_Factory'),
			'languages' => function_exists('wpml_get_setup_instance') && class_exists('WPML\\Element\\API\\Languages'),
			'local_jobs' => function_exists('wpml_tm_load_job_factory') && function_exists('wpml_tm_assign_translation_job'),
			'installer' => class_exists('WP_Installer_API'), 'cache' => function_exists('icl_cache_clear'));
		$registration = mcp_wpml_get_registration_status();
		return array('success' => true, 'wpml_version' => ICL_SITEPRESS_VERSION,
			'string_translation_version' => defined('WPML_ST_VERSION') ? WPML_ST_VERSION : null,
			'php_version' => PHP_VERSION, 'native_features' => $functions,
			'languages' => mcp_wpml_site_languages(), 'translation_controls' => mcp_wpml_translation_controls(),
			'registration' => is_wp_error($registration) ? array('available' => false) : $registration,
			'full_debug_dump_returned' => false, 'external_connectivity_checked' => false,
			'available_repair' => 'wpml/clear-translation-cache',
			'unsupported_repairs' => array('Database schema repair', 'Destructive WPML reset', 'Language/content deletion', 'Remote account or credential repair'));
	} catch (Throwable $error) { return new WP_Error('mcp_wpml_diagnostics_unavailable', 'Selected native diagnostics could not be read. No settings or queue were changed.'); }
}

function mcp_wpml_clear_translation_cache($input = array()) {
	if (true !== ($input['confirm'] ?? false)) { return new WP_Error('mcp_wpml_confirmation_required', 'Confirm the native translation-cache refresh.'); }
	if (!function_exists('icl_cache_clear')) { return new WP_Error('mcp_wpml_cache_unavailable', 'Native WPML cache refresh is unavailable.'); }
	try { icl_cache_clear(); return array('success' => true, 'native_cache_refreshed' => true, 'content_deleted' => false); }
	catch (Throwable $error) { return new WP_Error('mcp_wpml_cache_refresh_failed', 'Native WPML cache refresh failed.'); }
}

function mcp_wpml_register_site_administration_abilities(): void {
	$code = array('type' => 'string', 'pattern' => '^[a-z][a-z0-9-]{0,6}$');
	$codes = array('type' => 'array', 'items' => $code, 'minItems' => 1, 'maxItems' => 200, 'uniqueItems' => true);
	$confirm = array('type' => 'boolean', 'default' => false);
	$id = array('type' => 'integer', 'minimum' => 1);
	$tax = array('type' => 'string', 'minLength' => 1, 'maxLength' => 32);
	mcp_wpml_register_native_admin_definitions(array(
		'get-site-languages' => array('Get Site Languages', 'Read all native languages, including hidden active languages and the default language.', 'mcp_wpml_get_site_languages', array(), array(), true, false, true),
		'add-site-language' => array('Add Site Language', 'Create one reviewed custom WPML language without activation or content changes.', 'mcp_wpml_add_site_language', array('code' => $code, 'english_name' => array('type' => 'string', 'minLength' => 1, 'maxLength' => 128), 'locale' => array('type' => 'string', 'minLength' => 2, 'maxLength' => 35), 'hreflang' => array('type' => 'string', 'minLength' => 1, 'maxLength' => 20), 'confirm' => $confirm), array('code', 'english_name', 'locale', 'hreflang', 'confirm'), false, false, true),
		'set-active-site-languages' => array('Set Active Site Languages', 'Preview or confirm native activation/deactivation. Preserve the default language and stored translation content.', 'mcp_wpml_set_active_site_languages', array('codes' => $codes, 'expected_active_codes' => $codes, 'confirm' => $confirm), array('codes'), false, true, true),
		'set-default-site-language' => array('Set Default Site Language', 'Preview or confirm an active native default language. Native locale and user-language effects remain.', 'mcp_wpml_set_default_site_language', array('code' => $code, 'expected_default' => $code, 'confirm' => $confirm), array('code'), false, true, true),
		'set-term-language-details' => array('Set Term Language Details', 'Register missing language details for an editable term through the public WPML hook. Existing links are preserved.', 'mcp_wpml_set_term_language_details', array('term_id' => $id, 'taxonomy' => $tax, 'language_code' => $code, 'confirm' => $confirm), array('term_id', 'taxonomy', 'language_code'), false, false, true),
		'link-term-translation' => array('Link Term Translation', 'Preview or link two editable terms in the same translated taxonomy. Reject occupied languages and existing multi-term groups.', 'mcp_wpml_link_term_translation', array('source_term_id' => $id, 'target_term_id' => $id, 'taxonomy' => $tax, 'target_language' => $code, 'confirm' => $confirm), array('source_term_id', 'target_term_id', 'taxonomy', 'target_language'), false, false, true),
		'get-general-settings' => array('Get General Settings', 'Read selected scalar native WPML synchronization, translated-slug and translation-editor settings without raw options or registration data.', 'mcp_wpml_get_general_settings', array(), array(), true, false, true),
		'update-general-settings' => array('Update General Settings', 'Preview or confirm an allowlisted native settings patch against the reviewed settings hash. Other settings remain intact.', 'mcp_wpml_update_general_settings', array('changes' => array('type' => 'object', 'properties' => mcp_wpml_general_setting_schema(), 'additionalProperties' => false, 'minProperties' => 1), 'expected_settings_hash' => array('type' => 'string', 'pattern' => '^[a-f0-9]{64}$'), 'confirm' => $confirm), array('changes'), false, true, true),
		'get-diagnostics' => array('Get WPML Diagnostics', 'Read selected native component versions, language/control state and API availability. No full debug dump, secrets or remote probe.', 'mcp_wpml_get_diagnostics', array(), array(), true, false, true),
		'clear-translation-cache' => array('Clear Translation Cache', 'Refresh the native WPML translation cache. Preserve stored content, language relationships and jobs.', 'mcp_wpml_clear_translation_cache', array('confirm' => $confirm), array('confirm'), false, false, true),
		'get-registration-status' => array('Get Registration Status', 'Read only native Installer availability and site-key presence. License validity is not checked and keys are never returned.', 'mcp_wpml_get_registration_status', array(), array(), true, false, true),
		'get-registration-workflow' => array('Get Registration Workflow', 'Locate the existing native Installer screen and describe secure human registration without accepting or returning keys.', 'mcp_wpml_get_registration_workflow', array(), array(), true, false, true),
	));
}
