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

$product_terms = mcp_wpml_shared_term_hits(
	'Intelligent glass PRIVA-LITE PRIVA-LITE PRIVA-LITE plexiglass lexan plexiglass lexan',
	'Intelligent glass PRIVA-LITE PRIVA-LITE PRIVA-LITE plexiglass lexan plexiglass lexan in office walls',
	mcp_wpml_default_ignore_terms(),
	5,
	2,
	2,
	20
);

if (!empty($product_terms)) {
	fwrite(STDERR, "Shared product/technology terms leaked into shared-term detection\n");
	exit(1);
}

echo "Shared product term filter test passed\n";

$integrity_file = file_get_contents(dirname(__DIR__) . '/includes/translation-integrity-abilities.php');
if (false === $integrity_file) {
	fwrite(STDERR, "Could not read translation integrity ability file\n");
	exit(1);
}
if (str_contains($integrity_file, "'data-locale=\"nb-NO\"'") || str_contains($integrity_file, "'no.trustpilot.com'")) {
	fwrite(STDERR, "Global Trustpilot locale markers must not be default frontend source-language markers\n");
	exit(1);
}

echo "Frontend marker defaults test passed\n";
