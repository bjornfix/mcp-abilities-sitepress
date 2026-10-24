<?php
/** Native WordPress i18n, optional process-local site catalog; no persistent writes. */
if (!defined('ABSPATH') || !function_exists('mcp_wpml_public_file_helper_language') || is_admin()) {
    throw new RuntimeException('Active candidate and public context required');
}
$catalog = getenv('MCP_SITEPRESS_TEST_CATALOG');
if (!$catalog || !is_readable($catalog)) { throw new RuntimeException('Native catalog fixture required'); }
$domain = 'mcp-abilities-sitepress';
$source = 'Maximum file size: %s';
$checks = [];
$original = determine_locale();
foreach (['nb_NO', 'en_US'] as $locale) {
    $switched = switch_to_locale($locale);
    if (!$switched && determine_locale() !== $locale) { throw new RuntimeException('Required locale absent'); }
    try {
        unload_textdomain($domain, true);
        $checks[$locale . ' without catalog retains original'] = mcp_wpml_public_file_helper_language($source, $source, 'formidable-pro') === $source;
        if ($locale === 'nb_NO') {
            global $wp_textdomain_registry;
            $wp_textdomain_registry->set_custom_path($domain, dirname($catalog));
            unload_textdomain($domain, true);
        }
        $expected = $locale === 'nb_NO' ? 'Maksimal filstørrelse: %s' : $source;
        $checks[$locale . ' automatic native catalog result'] = mcp_wpml_public_file_helper_language($source, $source, 'formidable-pro') === $expected;
        $checks[$locale . ' existing translation preserved'] = mcp_wpml_public_file_helper_language('Existing native translation', $source, 'formidable-pro') === 'Existing native translation';
        $checks[$locale . ' other domain preserved'] = mcp_wpml_public_file_helper_language($source, $source, 'other-domain') === $source;
        if ($locale === 'nb_NO') {
            $checks['Actual size placeholder preserved'] = sprintf(mcp_wpml_public_file_helper_language($source, $source, 'formidable-pro'), '12 MB') === 'Maksimal filstørrelse: 12 MB';
        }
    } finally {
        unload_textdomain($domain, true);
        if ($switched) { restore_previous_locale(); }
    }
}
$checks['Caller locale restored'] = determine_locale() === $original;
$checks['Native gettext hook registered'] = false !== has_filter('gettext', 'mcp_wpml_public_file_helper_language');
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
echo wp_json_encode(['passed' => !$failed, 'checks' => $checks, 'failed' => $failed, 'persistent_writes' => 0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
if ($failed) { throw new RuntimeException('Native i18n regression failed'); }
