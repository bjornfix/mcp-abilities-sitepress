<?php
/** Exercise the request-language Adapter through its registered native hook. */
declare(strict_types=1);

namespace Elementor\Modules\Mcp\Preview {
	if (($GLOBALS['argv'][1] ?? '') !== 'no-elementor') {
		class Preview_Token {
			public const QUERY_ARG = 'elementor_preview_token';
			public static array $claims = array();
			public static function secret(): string { return 'test-only-native-secret'; }
			public static function decode(string $token, string $secret): ?array { return self::$claims[$token] ?? null; }
			public static function is_expired(array $claims, int $now): bool { return $now >= $claims['expires_at']; }
		}
	}
}

namespace {
	$mode = $argv[1] ?? 'frontend';
	define('ABSPATH', '/tmp/');
	if ('no-wpml' !== $mode) { define('ICL_SITEPRESS_VERSION', 'fixture'); }
	if ('rest' === $mode) { define('REST_REQUEST', true); }
	if ('cli' === $mode) { define('WP_CLI', true); }
	class WP { public array $query_vars = array(); }
	$GLOBALS['hooks'] = array();
	function add_action($name, $callback, $priority = 10, $accepted = 1): void { $GLOBALS['hooks'][$name][$priority][] = $callback; }
	function is_admin(): bool { return 'admin' === $GLOBALS['mode']; }
	function sanitize_text_field($value): string { return trim($value); }
	function wp_unslash($value): string { return stripslashes($value); }
	function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
	function wp_is_post_revision($id) { $post = get_post($id); return $post && 'revision' === $post->post_type ? (int) $post->post_parent : false; }
	function mcp_wpml_element_type_for_post_type(string $type): string { return 'post_' . $type; }
	function mcp_wpml_lang_details(int $id, string $type) {
		$GLOBALS['details_reads'][] = array($id, $type);
		return isset($GLOBALS['languages'][$id]) && $type === (get_post($id)->post_type ?? null) ? (object) array('language_code' => $GLOBALS['languages'][$id]) : null;
	}
	function apply_filters($name, $value) { return 'wpml_current_language' === $name ? $GLOBALS['language'] : $value; }
	function do_action($name, ...$args): void {
		$GLOBALS['actions'][] = array($name, $args);
		if ('wpml_switch_language' === $name) { $GLOBALS['language'] = $args[0]; }
	}
	function wp_insert_post(...$args) { throw new \RuntimeException('Unexpected post write'); }
	function wp_update_post(...$args) { throw new \RuntimeException('Unexpected post write'); }
	function update_post_meta(...$args) { throw new \RuntimeException('Unexpected metadata write'); }
	function update_option(...$args) { throw new \RuntimeException('Unexpected setting write'); }
	$module = $argv[2] ?? dirname(__DIR__) . '/includes/elementor-preview-language.php';
	require $module;
	$checks = 0;
	function check(bool $ok, string $message): void {
		++$GLOBALS['checks'];
		if (!$ok) { fwrite(STDERR, $message . "\n"); exit(1); }
	}
	check(in_array('mcp_wpml_elementor_preview_language', $GLOBALS['hooks']['parse_request'][2] ?? array(), true), 'Native after-activation request hook not registered');
	$posts = array(
		101 => (object) array('ID' => 101, 'post_type' => 'page', 'post_status' => 'draft'),
		202 => (object) array('ID' => 202, 'post_type' => 'page', 'post_status' => 'draft'),
		303 => (object) array('ID' => 303, 'post_type' => 'page', 'post_status' => 'publish'),
		404 => (object) array('ID' => 404, 'post_type' => 'post', 'post_status' => 'publish'),
		1101 => (object) array('ID' => 1101, 'post_type' => 'revision', 'post_parent' => 101),
		1202 => (object) array('ID' => 1202, 'post_type' => 'revision', 'post_parent' => 202),
		1303 => (object) array('ID' => 1303, 'post_type' => 'revision', 'post_parent' => 303),
		1404 => (object) array('ID' => 1404, 'post_type' => 'revision', 'post_parent' => 404),
	);
	$languages = array(101 => 'de', 202 => 'en', 303 => 'fr', 404 => 'en');
	$claims = array();
	foreach (array(101,202,303,404) as $id) { $claims['valid-' . $id] = array('post_id' => $id, 'revision_id' => $id + 1000, 'expires_at' => time() + 60); }
	$claims['expired'] = array('post_id' => 202, 'revision_id' => 1202, 'expires_at' => time() - 1);
	$claims['wrong-revision-parent'] = array('post_id' => 202, 'revision_id' => 1101, 'expires_at' => time() + 60);
	$claims['missing-revision'] = array('post_id' => 202, 'revision_id' => 9999, 'expires_at' => time() + 60);
	$claims['revision-target'] = array('post_id' => 1202, 'revision_id' => 1202, 'expires_at' => time() + 60);
	if ('no-elementor' !== $mode) { \Elementor\Modules\Mcp\Preview\Preview_Token::$claims = $claims; }
	$cases = array(
		array('signed nondefault draft', 'valid-202', 202, 'page', 'de', 'en'),
		array('signed public post', 'valid-404', 404, 'post', 'de', 'en'),
		array('another nondefault language', 'valid-303', 303, 'page', 'de', 'fr'),
		array('source-language draft', 'valid-101', 101, 'page', 'de', 'de'),
		array('already matching request', 'valid-202', 202, 'page', 'en', 'en'),
		array('ordinary request', null, 202, 'page', 'de', 'de'),
		array('empty token', '', 202, 'page', 'de', 'de'),
		array('array token', array('x'), 202, 'page', 'de', 'de'),
		array('invalid native signature', 'invalid', 202, 'page', 'de', 'de'),
		array('expired native token', 'expired', 202, 'page', 'de', 'de'),
		array('main query not activated', 'valid-202', 0, 'page', 'de', 'de'),
		array('other post selected', 'valid-202', 303, 'page', 'de', 'de'),
		array('different post type', 'valid-202', 202, 'post', 'de', 'de'),
		array('wrong revision parent', 'wrong-revision-parent', 202, 'page', 'de', 'de'),
		array('deleted revision', 'missing-revision', 202, 'page', 'de', 'de'),
	);
	$preserved = serialize($posts);
	foreach ($cases as list($name,$token,$id,$type,$initial,$expected)) {
		$GLOBALS['language'] = $initial; $GLOBALS['actions'] = array(); $GLOBALS['details_reads'] = array();
		$_GET = null === $token ? array() : array('elementor_preview_token' => $token);
		$input = $_GET;
		$wp = new WP(); $wp->query_vars = array('p' => $id, 'post_type' => $type, 'post_status' => array('publish','draft','private'));
		$query_before = $wp->query_vars;
		($GLOBALS['hooks']['parse_request'][2][0])($wp);
		if ('frontend' !== $mode) { $expected = $initial; }
		check($GLOBALS['language'] === $expected, $name . ': wrong request language');
		check($wp->query_vars === $query_before && $_GET === $input, $name . ': native query or token changed');
		check(serialize($posts) === $preserved, $name . ': stored posts changed');
		check(count($GLOBALS['actions']) === ($initial === $expected ? 0 : 1), $name . ': unexpected language action');
		if ($initial !== $expected) { check($GLOBALS['details_reads'] === array(array($id, $type)), $name . ': native language reader requires raw post type'); }
	}
	if ('frontend' === $mode) {
		unset($languages[202]);
		$GLOBALS['language'] = 'de'; $_GET = array('elementor_preview_token' => 'valid-202');
		$wp = new WP(); $wp->query_vars = array('p' => 202, 'post_type' => 'page');
		($GLOBALS['hooks']['parse_request'][2][0])($wp);
		check('de' === $GLOBALS['language'], 'Missing native language metadata must not change context');
	}
	echo json_encode(array('success'=>true,'mode'=>$mode,'checks'=>$checks,'posts_query_and_tokens_preserved'=>true),JSON_PRETTY_PRINT) . "\n";
}
