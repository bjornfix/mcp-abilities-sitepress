<?php
/** Fixed WPML pricing-notice policy through the installed native Unnotifier Interface. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

function mcp_wpml_pricing_policy(): array {
	$value = get_option('mcp_wpml_pricing_notice_policy', array());
	if (!is_array($value)) { throw new RuntimeException('Invalid pricing policy.'); }
	$value += array('hidden' => false, 'generation' => 0, 'targets' => array());
	if (!is_bool($value['hidden']) || !is_array($value['targets']) || count($value['targets']) > 32) { throw new RuntimeException('Invalid pricing policy.'); }
	foreach ($value['targets'] as $id => $target) {
		if (!preg_match('/^[a-f0-9]{8}_[a-f0-9]{8}$/D', (string) $id) || !is_array($target)) { throw new RuntimeException('Invalid managed target.'); }
	}
	return $value;
}

/** Confirm exact storage even when WordPress reports an idempotent false result. */
function mcp_wpml_save_pricing_policy(array $policy): void {
	update_option('mcp_wpml_pricing_notice_policy', $policy);
	if (get_option('mcp_wpml_pricing_notice_policy') !== $policy) { throw new RuntimeException('Policy readback failed.'); }
}

function mcp_wpml_pricing_renderer_ready(): bool {
	if (!class_exists('\\UNNO\\Core\\Notices')) { return false; }
	$facade = \UNNO\Core\Notices::instance();
	if (!is_callable(array($facade, 'get_notice_renderer'))) { return false; }
	return false !== has_action('unno_print_notices', array($facade->get_notice_renderer(), 'print_notices'));
}

function mcp_wpml_pricing_native_options() {
	if (!class_exists('\\UNNO\\Data\\Options') || !defined('UNNO_VERSION')) { throw new RuntimeException('Native Unnotifier unavailable.'); }
	$options = \UNNO\Data\Options::instance();
	foreach (array('get', 'get_global_hidden_notices', 'add_global_hidden_notice', 'remove_global_hidden_notice') as $method) {
		if (!is_callable(array($options, $method))) { throw new RuntimeException('Native Unnotifier contract unavailable.'); }
	}
	return $options;
}

/** Fixed text only. Mixed groups, dynamic widgets and unrelated notices remain untouched. */
function mcp_wpml_is_exact_pricing_notice(array $notice): bool {
	if ('WPML Multilingual CMS' !== ($notice['source_plugin'] ?? '') || !is_string($notice['content'] ?? null)
		|| !preg_match('/^[a-f0-9]{8}_[a-f0-9]{8}$/D', (string) ($notice['id'] ?? '')) || strlen($notice['content']) > 65536
		|| preg_match('/<(?:script|iframe|object|embed)\b/i', $notice['content'])) { return false; }
	$text = preg_replace('~</?(?:h[1-6]|p|div|a|br|button|span)\b[^>]*>~i', ' ', $notice['content']);
	$text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = trim((string) preg_replace('/[\s\p{Z}]+/u', ' ', $text));
	$expected = 'Your automatic translation pricing has been upgraded Finish the recommended setup from the Translations Dashboard to use the new pricing. Show me how';
	return in_array($text, array($expected, $expected . ' Dismiss this notice.', $expected . ' Avvis dette varselet.'), true);
}

function mcp_wpml_pricing_lock(): bool {
	if (!class_exists('WP_Upgrader')) { require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; }
	return (bool) \WP_Upgrader::create_lock('mcp_wpml_pricing_notice', 30);
}

/** Read-only state includes no notice HTML, callback, nonce, user identity or arbitrary ID. */
function mcp_wpml_get_pricing_notice_state($input = array()): array {
	if (!current_user_can('manage_options')) { return array('success' => false, 'code' => 'permission_denied'); }
	try {
		$options = mcp_wpml_pricing_native_options(); $policy = mcp_wpml_pricing_policy(); $global = $options->get_global_hidden_notices();
		$managed = 0;
		foreach ($policy['targets'] as $id => $target) { $managed += (int) !empty($global[$id]['mcp_wpml_pricing_notice']); }
		$mode = $options->get('mode', 'individual');
		$observation = get_transient('mcp_wpml_pricing_notice_observation');
		$last = is_array($observation) ? array_intersect_key($observation, array_flip(array('code', 'generation', 'matched_before', 'matched_after', 'other_records_preserved', 'other_visible_count', 'render_pipeline_completed', 'observed_at', 'wpml_version', 'unnotifier_version'))) : null;
		$verified = $policy['hidden'] && $managed > 0 && is_array($last) && ($last['generation'] ?? -1) === $policy['generation']
			&& !empty($last['render_pipeline_completed']) && !empty($last['other_records_preserved']) && 0 === ($last['matched_after'] ?? null)
			&& ($last['wpml_version'] ?? '') === ICL_SITEPRESS_VERSION && ($last['unnotifier_version'] ?? '') === UNNO_VERSION;
		$hash = hash('sha256', (string) wp_json_encode(array($policy, $mode, array_keys($global), ICL_SITEPRESS_VERSION, UNNO_VERSION)));
		return array('success' => true, 'target' => 'wpml_pricing_upgrade', 'label' => 'Your automatic translation pricing has been upgraded',
			'hidden_requested' => $policy['hidden'], 'mode' => in_array($mode, array('individual','show_all','hide_all'), true) ? $mode : 'unknown',
			'supported' => 'individual' === $mode, 'managed_fingerprint_count' => count($policy['targets']), 'native_hidden_managed_count' => $managed,
			'global_hidden_notice_count' => count($global), 'visibility_verified' => $verified, 'last_native_render' => $last,
			'state_hash' => $hash, 'raw_notice_data_returned' => false, 'note' => 'Only the exact standalone WPML pricing notice is eligible. Native admin rendering supplies its fingerprint; no callback is executed by MCP. Mixed groups remain visible.');
	} catch (Throwable $error) { return array('success' => false, 'code' => 'native_pricing_notice_state_unavailable', 'raw_notice_data_returned' => false); }
}

/** Preview or change only the fixed pricing policy; restore only entries created by this adapter. */
function mcp_wpml_set_pricing_notice_hidden($input = array()): array {
	if (!current_user_can('manage_options')) { return array('success' => false, 'code' => 'permission_denied'); }
	if (!is_bool($input['hidden'] ?? null)) { return array('success' => false, 'code' => 'invalid_hidden_state'); }
	$before = mcp_wpml_get_pricing_notice_state();
	if (empty($before['success']) || empty($before['supported'])) { return array('success' => false, 'code' => 'native_individual_mode_required', 'before' => $before); }
	if (true !== ($input['confirm'] ?? false)) { return array('success' => true, 'applied' => false, 'preview' => true, 'requested_hidden' => $input['hidden'], 'before' => $before); }
	$locked = false; $attempted = false;
	try {
		$locked = mcp_wpml_pricing_lock(); if (!$locked) { return array('success' => false, 'code' => 'pricing_policy_busy', 'applied' => false); }
		$before = mcp_wpml_get_pricing_notice_state();
		if (empty($before['success']) || !hash_equals((string) ($before['state_hash'] ?? ''), (string) ($input['expected_state_hash'] ?? ''))) { return array('success' => false, 'code' => 'stale_pricing_policy', 'applied' => false); }
		$options = mcp_wpml_pricing_native_options(); $policy = mcp_wpml_pricing_policy();
		$needs_restore = !$input['hidden'] && $before['native_hidden_managed_count'] > 0;
		if ($policy['hidden'] === $input['hidden'] && !$needs_restore) { return array('success' => true, 'applied' => false, 'before' => $before, 'after' => $before); }
		$attempted = true; $policy['hidden'] = $input['hidden']; ++$policy['generation'];
		mcp_wpml_save_pricing_policy($policy);
		if (!$policy['hidden']) {
			foreach ($policy['targets'] as $id => $target) {
				$global = $options->get_global_hidden_notices();
				if (isset($global[$id]) && empty($global[$id]['mcp_wpml_pricing_notice'])) { throw new RuntimeException('Native entry changed outside adapter.'); }
				if (isset($global[$id]) && !$options->remove_global_hidden_notice($id)) { throw new RuntimeException('Native restore failed.'); }
			}
			$policy['targets'] = array(); mcp_wpml_save_pricing_policy($policy);
		}
		$after = mcp_wpml_get_pricing_notice_state();
		return array('success' => !empty($after['success']) && $after['hidden_requested'] === $input['hidden'] && ($input['hidden'] || 0 === $after['native_hidden_managed_count']),
			'applied' => true, 'before' => $before, 'after' => $after, 'write_outcome' => 'read_back',
			'visibility_verification_pending' => $input['hidden'] && empty($after['visibility_verified']));
	} catch (Throwable $error) {
		return array('success' => false, 'code' => 'pricing_policy_write_failed', 'before' => $before, 'after' => mcp_wpml_get_pricing_notice_state(),
			'write_outcome' => 'read_back', 'write_attempted' => $attempted, 'verification_required' => $attempted);
	} finally { if ($locked) { \WP_Upgrader::release_lock('mcp_wpml_pricing_notice'); } }
}

/** Passive native-render Adapter. Never invokes an admin-notice callback. */
function mcp_wpml_before_pricing_notice_render($hook = null): void {
	global $unno_captured_notices_by_hook, $mcp_wpml_pricing_render_pending;
	if (!is_admin() || !current_user_can('manage_options') || !in_array($hook, array('admin_notices','network_admin_notices','all_admin_notices'), true)
		|| !is_array($unno_captured_notices_by_hook[$hook] ?? null)) { return; }
	$locked = false; $observation = null;
	try {
		if (!mcp_wpml_pricing_renderer_ready()) { return; }
		$options = mcp_wpml_pricing_native_options(); if ('individual' !== $options->get('mode','individual')) { return; }
		$rows = $unno_captured_notices_by_hook[$hook]; if (count($rows) > 200) { return; }
		$matches = array(); $rejected = 0;
		foreach ($rows as $index => $notice) {
			if (!is_array($notice)) { continue; }
			if (mcp_wpml_is_exact_pricing_notice($notice)) { $matches[$index] = $notice; }
			elseif ('WPML Multilingual CMS' === ($notice['source_plugin'] ?? '') && is_string($notice['content'] ?? null)
				&& false !== strpos($notice['content'], 'Your automatic translation pricing has been upgraded')) { ++$rejected; }
		}
		if (!$matches && !$rejected) { return; }
		$locked = mcp_wpml_pricing_lock(); if (!$locked) { return; }
		$policy = mcp_wpml_pricing_policy(); $global = $options->get_global_hidden_notices();
		// A human native restore is authoritative. Do not reinstate an AI-owned preference after it was removed.
		foreach ($policy['targets'] as $id => $target) {
			if ($policy['hidden'] && !empty($target['native_applied']) && !isset($global[$id])) {
				$policy['hidden'] = false; ++$policy['generation']; mcp_wpml_save_pricing_policy($policy); break;
			}
		}
		$observation = array('generation' => $policy['generation'], 'matched_before' => count($matches), 'matched_after' => count($matches),
			'other_records_preserved' => true, 'other_visible_count' => count($rows) - count($matches), 'render_pipeline_completed' => false,
			'observed_at' => time(), 'wpml_version' => ICL_SITEPRESS_VERSION, 'unnotifier_version' => UNNO_VERSION,
			'code' => $rejected || count($matches) !== 1 ? 'ambiguous_or_mixed_group_preserved' : 'exact_native_pricing_notice_observed');
		if ($policy['hidden'] && 1 === count($matches) && 0 === $rejected) {
			$index = array_key_first($matches); $id = $matches[$index]['id'];
			$other_before = $rows; unset($other_before[$index]);
			if (!isset($global[$id])) {
				if (count($policy['targets']) >= 32) { throw new RuntimeException('Managed target limit reached.'); }
				// Track ownership before native storage, so a partial write always remains restorable.
				$policy['targets'][$id] = array('wpml_version'=>ICL_SITEPRESS_VERSION, 'unnotifier_version'=>UNNO_VERSION, 'native_applied'=>false);
				mcp_wpml_save_pricing_policy($policy);
				$metadata = array('source_plugin'=>'WPML Multilingual CMS', 'excerpt'=>'Your automatic translation pricing has been upgraded', 'mcp_wpml_pricing_notice'=>true, 'hidden_at'=>time());
				if (!$options->add_global_hidden_notice($id, $metadata)) { throw new RuntimeException('Native hide failed.'); }
			}
			$after_global = $options->get_global_hidden_notices();
			if (empty($after_global[$id]['mcp_wpml_pricing_notice']) || !isset($policy['targets'][$id])) { throw new RuntimeException('Native hide readback failed.'); }
			$policy['targets'][$id]['native_applied'] = true;
			mcp_wpml_save_pricing_policy($policy);
			unset($unno_captured_notices_by_hook[$hook][$index]);
			$observation['matched_after'] = 0;
			$observation['other_records_preserved'] = $other_before === $unno_captured_notices_by_hook[$hook];
			$observation['code'] = 'exact_pricing_notice_filtered_by_native_preference';
		}
		set_transient('mcp_wpml_pricing_notice_observation', $observation, DAY_IN_SECONDS);
		$mcp_wpml_pricing_render_pending = $hook;
	} catch (Throwable $error) {
		// Never remove a rendered record after an incomplete native write. Keep precise partial evidence.
		if (is_array($observation)) {
			$observation['code'] = 'native_pricing_preference_partial_failure';
			set_transient('mcp_wpml_pricing_notice_observation', $observation, DAY_IN_SECONDS);
		}
	} finally { if ($locked) { \WP_Upgrader::release_lock('mcp_wpml_pricing_notice'); } }
}

function mcp_wpml_after_pricing_notice_render($hook = null): void {
	global $mcp_wpml_pricing_render_pending;
	if ($hook !== ($mcp_wpml_pricing_render_pending ?? null) || !is_admin() || !current_user_can('manage_options') || !mcp_wpml_pricing_renderer_ready()) { return; }
	$observation = get_transient('mcp_wpml_pricing_notice_observation');
	if (is_array($observation)) { $observation['render_pipeline_completed'] = true; set_transient('mcp_wpml_pricing_notice_observation', $observation, DAY_IN_SECONDS); }
	$mcp_wpml_pricing_render_pending = null;
}

/** Observe only native update-check traffic, and retain enums/versions rather than URLs or payloads. */
function mcp_wpml_update_http_observer($response, $context, $class, $args, $url): void {
	if (empty($GLOBALS['mcp_wpml_observe_update_http']) || 'response' !== $context || !is_string($url)) { return; }
	$host = wp_parse_url($url, PHP_URL_HOST);
	if (!in_array($host, array('api.wpml.org','wpml.org'), true)) { return; }
	$record = array('checked_at'=>time(), 'http_status'=>null, 'error_code'=>null, 'feed_core_version'=>null);
	if (is_wp_error($response)) {
		$message = $response->get_error_message();
		$record['error_code'] = false !== stripos($message, 'timed out') || false !== stripos($message, 'cURL error 28') ? 'timeout'
			: (false !== stripos($message, 'SSL') || false !== stripos($message, 'certificate') ? 'tls_verification_failed' : 'native_http_request_failed');
	} else {
		$record['http_status'] = (int) wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		if (strlen($body) <= 2097152) { $record['feed_core_version'] = mcp_wpml_installer_core_version(json_decode($body, true)); }
	}
	set_transient('mcp_wpml_update_channel_observation', $record, DAY_IN_SECONDS);
}

/** Extract only the core product version from a bounded native repository result. */
function mcp_wpml_installer_core_version($data, int $depth = 0): ?string {
	if (!is_array($data) || $depth > 7 || count($data) > 3000) { return null; }
	foreach ($data as $key => $value) {
		if (in_array((string) $key, array('sitepress-multilingual-cms','sitepress-multilingual-cms/sitepress.php'), true) && is_array($value)
			&& preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:[-.][A-Za-z0-9]+)*$/D', (string) ($value['version'] ?? ''))) { return $value['version']; }
		if (is_array($value) && in_array($value['slug'] ?? '', array('sitepress-multilingual-cms','sitepress'), true)
			&& preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:[-.][A-Za-z0-9]+)*$/D', (string) ($value['version'] ?? ''))) { return $value['version']; }
		if (is_array($value)) { $found = mcp_wpml_installer_core_version($value, $depth + 1); if (null !== $found) { return $found; } }
	}
	return null;
}

/** Native commercial provider status, without registration/account values or raw feed data. */
function mcp_wpml_get_update_channel_status($input = array()): array {
	if (!current_user_can('manage_options') || !current_user_can('update_plugins')) { return array('success'=>false,'code'=>'permission_denied'); }
	try {
		$installer = function_exists('WP_Installer') ? WP_Installer() : null;
		$support = array();
		foreach (array('get_repository','get_repositories','get_settings','refresh_repositories_data','refresh_repo_data','get_last_error','get_update_channel') as $method) {
			$support[$method] = is_object($installer) && method_exists($installer,$method) && is_callable(array($installer,$method)) ? (new ReflectionMethod($installer,$method))->getNumberOfRequiredParameters() : null;
		}
		$native = null;
		if (0 === $support['get_repositories']) { $all = $installer->get_repositories(); $native = is_array($all) ? ($all['wpml'] ?? null) : null; }
		elseif (1 === $support['get_repository']) { $native = $installer->get_repository('wpml'); }
		$channel = is_array($native) ? ($native['channel'] ?? $native['update_channel'] ?? 'unknown') : 'unknown';
		if (!in_array($channel,array('stable','production','beta','development'),true)) { $channel='unknown'; }
		if (!empty($input['refresh'])) {
			$GLOBALS['mcp_wpml_observe_update_http'] = true;
			try { wp_clean_update_cache(); wp_update_plugins(); } finally { unset($GLOBALS['mcp_wpml_observe_update_http']); }
		}
		$cache = get_site_transient('update_plugins'); $offer = is_object($cache) ? ($cache->response['sitepress-multilingual-cms/sitepress.php'] ?? null) : null;
		$offered = is_object($offer) && preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:[-.][A-Za-z0-9]+)*$/D',(string)($offer->new_version??'')) ? $offer->new_version : null;
		$http = get_transient('mcp_wpml_update_channel_observation');
		return array('success'=>true,'installed_version'=>ICL_SITEPRESS_VERSION,'provider'=>'wpml','native_installer_available'=>is_object($installer),
			'native_api_support'=>$support,'channel'=>$channel,'native_feed_core_version'=>mcp_wpml_installer_core_version($native),'wordpress_offered_version'=>$offered,
			'refresh_requested'=>!empty($input['refresh']),'last_native_http_check'=>is_array($http)?$http:null,
			'native_repository_inspected'=>is_array($native),'provider_response_observed'=>is_array($http),'provider_health'=>'not_established','account_data_returned'=>false,
			'note'=>'Native repository and observed update-check metadata only. A missing WordPress offer does not establish license validity, staged rollout or provider health. No registration, billing or download action is performed.');
	} catch (Throwable $error) { return array('success'=>false,'code'=>'native_update_channel_status_unavailable','account_data_returned'=>false); }
}

function mcp_wpml_register_maintenance_abilities(): void {
	mcp_wpml_register_native_admin_definitions(array(
		'get-pricing-notice-state'=>array('Get Pricing Notice State','Read only the fixed WPML pricing-notice policy and redacted native-render verification. No raw notices, callbacks, nonces or arbitrary IDs.','mcp_wpml_get_pricing_notice_state',array(),array(),true,false,true),
		'set-pricing-notice-hidden'=>array('Set Pricing Notice Hidden','Preview or confirm reversible hiding of only the exact standalone WPML pricing/onboarding notice through native Unnotifier. Reject mixed groups; preserve all other notices and human native restores.','mcp_wpml_set_pricing_notice_hidden',array('hidden'=>array('type'=>'boolean'),'confirm'=>array('type'=>'boolean','default'=>false),'expected_state_hash'=>array('type'=>'string','pattern'=>'^[a-f0-9]{64}$')),array('hidden'),false,false,true),
		'get-update-channel-status'=>array('Get WPML Update Channel Status','Read bounded native commercial-provider metadata and optionally refresh normal WordPress update checks. No site keys, tokens, account data, setup or payments.','mcp_wpml_get_update_channel_status',array('refresh'=>array('type'=>'boolean','default'=>false)),array(),true,false,true,static fn():bool=>current_user_can('manage_options')&&current_user_can('update_plugins')),
	));
}

add_action('unno_print_notices','mcp_wpml_before_pricing_notice_render',1,1);
add_action('unno_print_notices','mcp_wpml_after_pricing_notice_render',20,1);
add_action('http_api_debug','mcp_wpml_update_http_observer',10,5);
