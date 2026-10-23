<?php
/** Exercise full plugin registration and documentation through its owning Interface. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ICL_SITEPRESS_VERSION', 'contract-fixture');
$abilities = array();
function add_action(...$args) {}
function add_filter(...$args) {}
function current_user_can(...$args) { return false; }
function wp_register_ability($name, $args) { if (isset($GLOBALS['abilities'][$name])) { throw new RuntimeException('Duplicate ability.'); } $GLOBALS['abilities'][$name] = $args; }
require dirname(__DIR__) . '/mcp-abilities-sitepress.php';
mcp_wpml_register_abilities();
if (54 !== count($abilities)) { throw new RuntimeException('Expected 54 full-plugin abilities.'); }
foreach ($abilities as $name => $ability) {
    if (!is_callable($ability['execute_callback']) || !is_callable($ability['permission_callback']) || $ability['permission_callback']()) { throw new RuntimeException('Invalid callback or anonymous access: ' . $name); }
}
$coverage = mcp_wpml_administration_coverage();
foreach ($coverage['coverage'] as $area) {
    foreach ($area['supported'] as $name) { if (!isset($abilities[$name])) { throw new RuntimeException('Coverage names an unregistered ability: ' . $name); } }
}
foreach (array('README.md', 'readme.txt') as $file) {
    $doc = file_get_contents(dirname(__DIR__) . '/' . $file);
    preg_match_all('/`(wpml\/[a-z0-9-]+)`/', $doc, $matches);
    $documented = array_unique($matches[1]);
    if (array_diff(array_keys($abilities), $documented) || array_diff($documented, array_keys($abilities))) { throw new RuntimeException('Documented inventory mismatch: ' . $file); }
}
echo json_encode(array('success' => true, 'registered_abilities' => count($abilities), 'anonymous_permissions_denied' => true, 'coverage_and_documented_inventory_match' => true), JSON_PRETTY_PRINT) . PHP_EOL;
