<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('add_filter')) {
	function add_filter(...$args): bool {
		return true;
	}
}

if (!function_exists('add_action')) {
	function add_action(...$args): bool {
		return true;
	}
}

if (!function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags($text): string {
		return trim(strip_tags((string) $text));
	}
}

require_once dirname(__DIR__) . '/mcp-abilities-sitepress.php';

$parts = array();
mcp_wpml_collect_elementor_text_values(
	array(
		'id' => 'abc123',
		'__globals__' => array(
			'title_color' => 'globals/colors?id=g1-live-teal',
			'typography_typography' => 'globals/typography?id=g1-home-reference-heading',
		),
		'settings' => array(
			'title' => 'Professional expertise gives peace of mind',
			'typography_typography' => 'globals/typography?id=g1-product-body',
			'editor' => '<p>Glass canopy text remains visible.</p>',
		),
	),
	$parts
);

$text = implode("\n", $parts);
if (str_contains($text, 'globals/')) {
	fwrite(STDERR, "Elementor global token leaked into detection text\n");
	exit(1);
}
if (!str_contains($text, 'Professional expertise gives peace of mind') || !str_contains($text, 'Glass canopy text remains visible.')) {
	fwrite(STDERR, "Human Elementor text was not collected\n");
	exit(1);
}

echo "Elementor text filter test passed\n";

$shared_terms = mcp_wpml_shared_term_hits(
	'Glass doors center center center center center',
	'Glass doors center center center center center with glass panels',
	mcp_wpml_default_ignore_terms(),
	5,
	2,
	2,
	20
);

if (!empty($shared_terms)) {
	fwrite(STDERR, "Neutral layout/domain terms leaked into shared-term detection\n");
	exit(1);
}

echo "Shared neutral term filter test passed\n";
