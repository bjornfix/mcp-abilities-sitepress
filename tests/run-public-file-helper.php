<?php
/** Optional native catalog; without configuration every locale keeps source text. */
define('ABSPATH', __DIR__ . '/');
function add_filter(...$args) {}
function is_admin() { return $GLOBALS['fixture_admin']; }
function __($text, $domain) {
    if ($domain !== 'mcp-abilities-sitepress') { throw new RuntimeException('Unexpected domain'); }
    return $GLOBALS['fixture_catalog'][$GLOBALS['fixture_locale']][$text] ?? $text;
}
require dirname(__DIR__) . '/includes/public-file-helper-language.php';
$source = 'Maximum file size: %s';
$GLOBALS['fixture_admin'] = false;
$GLOBALS['fixture_catalog'] = [];
$checks = [];
foreach (['nb_NO', 'en_US'] as $locale) {
    $GLOBALS['fixture_locale'] = $locale;
    $checks[$locale . ' without catalog retains original'] = mcp_wpml_public_file_helper_language($source, $source, 'formidable-pro') === $source;
}
$GLOBALS['fixture_catalog'] = ['nb_NO' => [$source => 'Maksimal filstørrelse: %s']];
foreach (['nb_NO', 'en_US', 'fr_FR'] as $locale) {
    $GLOBALS['fixture_locale'] = $locale;
    $expected = $locale === 'nb_NO' ? 'Maksimal filstørrelse: %s' : $source;
    $checks[$locale . ' uses only native locale catalog'] = mcp_wpml_public_file_helper_language($source, $source, 'formidable-pro') === $expected;
    $checks[$locale . ' preserves existing translation'] = mcp_wpml_public_file_helper_language('Existing native translation', $source, 'formidable-pro') === 'Existing native translation';
    $checks[$locale . ' preserves other domain'] = mcp_wpml_public_file_helper_language($source, $source, 'unrelated-domain') === $source;
    $GLOBALS['fixture_admin'] = true;
    $checks[$locale . ' preserves admin'] = mcp_wpml_public_file_helper_language($source, $source, 'formidable-pro') === $source;
    $GLOBALS['fixture_admin'] = false;
}
$GLOBALS['fixture_locale'] = 'nb_NO';
$checks['Size placeholder preserved'] = sprintf(mcp_wpml_public_file_helper_language($source, $source, 'formidable-pro'), '12 MB') === 'Maksimal filstørrelse: 12 MB';
$checks['Other messages preserved'] = mcp_wpml_public_file_helper_language('Other', 'Other', 'formidable-pro') === 'Other';
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
echo json_encode(['passed' => !$failed, 'checks' => $checks, 'failed' => $failed], JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed ? 1 : 0);
