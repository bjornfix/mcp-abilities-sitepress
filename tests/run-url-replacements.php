<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function add_filter(...$args) {}
function apply_filters($hook, $value, ...$args) {
 if ('wpml_element_language_details' === $hook) { return in_array($args[0]['element_type'], array('page', 'category'), true) ? (object) array('trid' => 10) : null; }
 return $value;
}
function esc_url($url) { return str_replace('&', '&#038;', $url); }
function esc_url_raw($url) { return $url; }
require dirname(__DIR__) . '/mcp-abilities-sitepress.php';
if (!mcp_wpml_lang_details(1, 'page')) { fwrite(STDERR, "Language lookup did not use WPML's documented raw post type\n"); exit(1); }
$input = '<a href="/service">one</a><a href="/service-more">two</a><a href="https://example.org/service">external</a>';
$expected = '<a href="/fr/service">one</a><a href="/service-more">two</a><a href="https://example.org/service">external</a>';
[$actual, $count] = mcp_wpml_replace_url_variants($input, '/service', '/fr/service');
if ($expected !== $actual || 1 !== $count) { fwrite(STDERR, "Replacement changed a different URL sharing the same text\n"); exit(1); }
$json = '{"url":"https:\/\/example.com\/service","html":"<a href=\"https://example.com/service\">link</a>"}';
[$actual, $count] = mcp_wpml_replace_url_variants($json, 'https://example.com/service', 'https://example.com/service/fr');
$data = json_decode($actual, true);
if (2 !== $count || !is_array($data) || 'https://example.com/service/fr' !== $data['url'] || '<a href="https://example.com/service/fr">link</a>' !== $data['html']) { fwrite(STDERR, "JSON replacement was corrupted or applied more than once\n"); exit(1); }
echo "Exact HTML and JSON URL replacement checks passed\n";
