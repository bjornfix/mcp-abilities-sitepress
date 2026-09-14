<?php
/** Verify exact redirect deletion through the native manager adapter. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$abilities = array();
function wp_register_ability($name, $args) { $GLOBALS['abilities'][$name] = $args; }
function current_user_can(...$args) { return true; }
function get_option($name, $default) { return $default; }
class WPSEO_Redirect_Manager {
 public static $redirect;
 public static $deleted = 0;
 public static $fail = false;
 public function __construct($format = 'plain') {}
 public function get_redirect($origin) { return self::$redirect; }
 public function delete_redirects($redirects) { ++self::$deleted; if (self::$fail) { return false; } self::$redirect = false; return true; }
}
class RedirectFixture {
 public function get_url() { return '/new/'; }
}
require dirname(__DIR__) . '/includes/translation-integrity-abilities.php';
mcp_wpml_register_translation_integrity_abilities();
$callback = $abilities['wpml/remove-yoast-redirect']['execute_callback'];
WPSEO_Redirect_Manager::$redirect = new RedirectFixture();
$input = array('origin' => 'old', 'url' => 'new', 'clean_htaccess' => false);
$mismatch = $callback(array_merge($input, array('url' => 'new-other')));
if (!empty($mismatch['success']) || WPSEO_Redirect_Manager::$deleted) { fwrite(STDERR, "Mismatched redirect target accepted\n"); exit(1); }
WPSEO_Redirect_Manager::$fail = true;
if (!empty($callback($input)['success'])) { fwrite(STDERR, "Native deletion failure ignored\n"); exit(1); }
WPSEO_Redirect_Manager::$fail = false;
if (empty($callback($input)['success']) || WPSEO_Redirect_Manager::$redirect) { fwrite(STDERR, "Native redirect deletion did not complete\n"); exit(1); }
echo "Exact native redirect deletion contract passed\n";
