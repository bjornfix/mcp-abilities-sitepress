<?php
/** Native Elementor image-HTML filter contract with WordPress's actual HTML processor. */
declare(strict_types=1);
$api = getenv('MCP_WPML_TEST_HTML_API');
if (!$api || !is_file($api . '/class-wp-html-tag-processor.php')) {
	throw new RuntimeException('Set MCP_WPML_TEST_HTML_API to the native WordPress wp-includes/html-api directory.');
}
require_once dirname($api) . '/compat.php';
require_once dirname($api) . '/class-wp-token-map.php';
require_once $api . '/html5-named-character-references.php';
foreach (array('span', 'attribute-token', 'text-replacement', 'decoder', 'tag-processor') as $class) {
	require_once $api . '/class-wp-html-' . $class . '.php';
}
// The actual processor only asks these WordPress helpers about our fixed alt attribute.
function wp_has_noncharacters($name) { if ('alt' !== $name) { throw new RuntimeException('Unexpected attribute mutation.'); } return false; }
function wp_kses_uri_attributes() { return array('href', 'src'); }
putenv('MCP_WPML_TEST_BOOTSTRAP_ONLY=1');
require __DIR__ . '/run-attachment-text.php';
// Real native image outputs: empty stored alt, then attachment caption or title fallback.
$GLOBALS['mcp_meta'][1] = ''; $GLOBALS['mcp_meta'][2] = '';
$GLOBALS['mcp_meta'][3] = ''; $GLOBALS['mcp_meta'][4] = ''; $GLOBALS['mcp_meta'][7] = ''; $GLOBALS['mcp_meta'][8] = ''; $GLOBALS['mcp_meta'][9] = '';
$GLOBALS['mcp_posts'][33]->post_excerpt = ''; $GLOBALS['mcp_posts'][23]->post_excerpt = '';
$GLOBALS['mcp_posts'][15] = new WP_Post(15, 'Quellentitel', 'Deutsche "Zitat" & Bildunterschrift', '');
$GLOBALS['mcp_posts'][16] = new WP_Post(16, 'English title', 'English "quoted" caption & customer\'s &amp; credit', '');
$GLOBALS['mcp_pairs'][15] = 16;
$GLOBALS['mcp_posts'][17] = new WP_Post(17, '', '', '');
$GLOBALS['mcp_posts'][18] = new WP_Post(18, 'English title', 'English caption', '');
$GLOBALS['mcp_posts'][19] = new WP_Post(19, 'Quellentitel', " \t\n ", '');
$GLOBALS['mcp_posts'][20] = new WP_Post(20, 'English title', 'English caption', '');
$GLOBALS['mcp_posts'][25] = new WP_Post(25, 'Quellentitel', 'Quellbeschriftung', '');
$GLOBALS['mcp_posts'][26] = new WP_Post(26, 'English title', '<strong> </strong>', '');
$GLOBALS['mcp_pairs'] += array(17 => 18, 19 => 20, 25 => 26);
$before = serialize(array($GLOBALS['mcp_posts'], $GLOBALS['mcp_meta'], $GLOBALS['mcp_pairs']));
$checks = 0;
$assert = static function($ok, $message) use (&$checks) { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$call = static fn($html, $settings, $size = 'image', $key = null) => apply_filters('elementor/image_size/get_attachment_image_html', $html, $settings, $size, $key);
$image = static fn($alt) => '<img src="/original.jpg" title="Native title" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" width="600" height="400" loading="lazy" data-native="kept" />';
$front = 'frontend' === $mode;
$html = $image('Deutsche Bildunterschrift');
$out = $call($html, array('image' => array('id' => 1), 'image_size' => 'custom'));
$assert($out === ($front ? $image('English caption') : $html), 'Native custom-size caption fallback kept the source language or changed other HTML.');
$assert('' === get_post_meta(1, '_wp_attachment_image_alt', true), 'Rendering invented stored/source alt text.');
$assert($call($image(''), array('image' => array('id' => 1))) === $image(''), 'An intentionally empty rendered alt was enabled.');
$assert($call($image('Editorial alternative'), array('image' => array('id' => 1))) === $image('Editorial alternative'), 'An explicit caller alternative was overwritten.');
foreach (array(17, 19) as $empty_id) {
	$assert($call($image(''), array('image' => array('id' => $empty_id))) === $image(''), 'An empty or whitespace-only native fallback was enabled.');
}
$assert($call($image('Quellbeschriftung'), array('image' => array('id' => 25))) === $image('Quellbeschriftung'), 'Translation markup without readable text erased the native fallback.');
$other_key = $call($image('Öffentliche Bildunterschrift'), array('thumbnail' => array('id' => 33), 'thumbnail_size' => 'custom'), 'thumbnail');
$assert($other_key === $image('Öffentliche Bildunterschrift'), 'A nonmatching alternative text was rewritten.');
$title = $image('Öffentliche Quelle');
$out = $call($title, array('featured' => array('id' => 33), 'thumbnail_size' => 'custom'), 'thumbnail', 'featured');
$assert($out === ($front ? $image('Inherited English title') : $title), 'Another native caller did not translate the title fallback with its explicit image key.');
$quoted = $image('Deutsche "Zitat" & Bildunterschrift');
$out = $call($quoted, array('image' => array('id' => 15)));
$parsed = new WP_HTML_Tag_Processor($out); $parsed->next_tag('IMG');
$assert($parsed->get_attribute('alt') === ($front ? 'English "quoted" caption & customer\'s & credit' : 'Deutsche "Zitat" & Bildunterschrift'), 'Quoted caption or ampersand was skipped or double-encoded.');
$assert(preg_replace('/ alt="[^"]*"/', '', $out) === preg_replace('/ alt="[^"]*"/', '', $quoted), 'Entity handling changed image identity or unrelated HTML.');
$st = $image('Bildunterschrift ohne Medienpartner');
$out = $call($st, array('image' => array('id' => 3)));
$assert($out === ($front ? $image('Caption without a separate media record') : $st), 'An existing native String Translation caption was ignored.');
$safe = $image('Foto: Fotograf');
$assert($call($safe, array('image' => array('id' => 8))) === ($front ? $image('Credit') : $safe), 'Translated caption markup or scripts reached the native alt attribute.');
$pending = $image('Bildunterschrift beibehalten');
$assert($call($pending, array('image' => array('id' => 7))) === $pending, 'Missing translations were hidden or invented.');
$private = $image('Andere Bildunterschrift');
$assert($call($private, array('image' => array('id' => 4))) === $private, 'A private translated caption became public.');
$hidden = $image('Private Bildunterschrift');
$assert($call($hidden, array('image' => array('id' => 10))) === $hidden, 'A private source caption became public.');
$explicit = $image('Deutscher Alternativtext');
$assert($call($explicit, array('image' => array('id' => 14))) === $explicit, 'The native stored-alt branch was replaced with caption fallback.');
$assert($call($html, array('image' => array('url' => '/external.jpg'))) === $html, 'An inserted URL without an attachment was rewritten.');
$assert($call($html, array('image' => array('id' => 6))) === $html, 'An ordinary page was treated as attachment text.');
$assert($call('<span>Native empty image</span>', array('image' => array('id' => 1))) === '<span>Native empty image</span>', 'Non-image markup changed.');
foreach (array(null, 'invalid', array(), array('image' => null), array('image' => array('id' => -1))) as $settings) {
	$assert($call($html, $settings) === $html, 'Malformed native settings changed output or threw.');
}
if ($front) {
	$assert(isset($GLOBALS['mcp_registered']['attachment-3-caption']) && 'de' === $GLOBALS['mcp_registered']['attachment-3-caption']['source_lang'], 'Native fallback string used the wrong source identity.');
	$assert(!isset($GLOBALS['mcp_registered']['attachment-10-caption']), 'A hidden private caption was registered.');
	$GLOBALS['mcp_lang'] = 'de';
	$assert($call($html, array('image' => array('id' => 1))) === $html, 'Translated fallback leaked into the source language.');
}
$assert($before === serialize(array($GLOBALS['mcp_posts'], $GLOBALS['mcp_meta'], $GLOBALS['mcp_pairs'])), 'Rendering changed posts, media metadata, files or WPML relationships.');
echo json_encode(array('success' => true, 'mode' => $mode, 'native_fallback_interface_checks' => $checks, 'native_HTML_processor' => true, 'source_and_media_unchanged' => true), JSON_PRETTY_PRINT) . PHP_EOL;
