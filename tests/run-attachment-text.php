<?php
/** Exercise native Gallery/lightbox/metadata callers through the real SitePress bootstrap. */
declare(strict_types=1);
namespace {
	define('ABSPATH', __DIR__ . '/');
	define('ICL_SITEPRESS_VERSION', 'native-contract');
	$mode = getenv('MCP_WPML_TEST_MODE') ?: 'frontend';
	if ('rest' === $mode) { define('REST_REQUEST', true); }
	if ('cli' === $mode) { define('WP_CLI', true); }
	$GLOBALS['mcp_test_mode'] = $mode;
	$GLOBALS['mcp_hooks'] = array(); $GLOBALS['mcp_lang'] = 'en';
	$GLOBALS['mcp_strings'] = array(); $GLOBALS['mcp_registered'] = array();
	class WP_Post {
		public int $ID; public int $post_parent = 0; public string $post_type = 'attachment'; public string $post_status = 'inherit';
		public string $post_title; public string $post_excerpt; public string $post_content;
		public function __construct(int $id, string $title, string $caption, string $description) {
			$this->ID = $id; $this->post_title = $title; $this->post_excerpt = $caption; $this->post_content = $description;
		}
	}
	$GLOBALS['mcp_posts'] = array(
		1 => new WP_Post(1, 'Fenster mit Sprossen', 'Deutsche Bildunterschrift', 'Deutsche Beschreibung'),
		2 => new WP_Post(2, 'Windows with glazing bars', 'English caption', 'English description'),
		3 => new WP_Post(3, 'Deutsch ohne Medienpartner', 'Bildunterschrift ohne Medienpartner', 'Beschreibung ohne Medienpartner'),
		4 => new WP_Post(4, 'Andere Quelle', 'Andere Bildunterschrift', 'Andere Beschreibung'),
		5 => new WP_Post(5, 'Private translated media', 'Private caption', 'Private description'),
		6 => new WP_Post(6, 'Eine Seite', 'Seitenauszug', 'Seitentext'),
		7 => new WP_Post(7, 'Quelle ohne fertigen Text', 'Bildunterschrift beibehalten', 'Beschreibung beibehalten'),
		8 => new WP_Post(8, 'Quelle', 'Foto: Fotograf', 'Originalbeschreibung'),
		9 => new WP_Post(9, '<script>bad()</script>Safe title', '<strong>Credit</strong><script>bad()</script>', '<em>Description</em>'),
	);
	$GLOBALS['mcp_posts'][10] = new WP_Post(10, 'Private Quelle', 'Private Bildunterschrift', 'Private Beschreibung');
	$GLOBALS['mcp_posts'][10]->post_status = 'private';
	$GLOBALS['mcp_posts'][5]->post_status = 'private'; $GLOBALS['mcp_posts'][6]->post_type = 'page';
	foreach (array(11 => 'private', 12 => 'draft', 13 => 'publish') as $id => $status) {
		$GLOBALS['mcp_posts'][$id] = new WP_Post($id, 'Geerbte Quelle', 'Geerbte Bildunterschrift', 'Geerbte Beschreibung');
		$GLOBALS['mcp_posts'][$id]->post_parent = $id + 100;
		$GLOBALS['mcp_posts'][$id + 100] = new WP_Post($id + 100, 'Elternseite', '', '');
		$GLOBALS['mcp_posts'][$id + 100]->post_type = 'page';
		$GLOBALS['mcp_posts'][$id + 100]->post_status = $status;
		$GLOBALS['mcp_posts'][$id + 10] = new WP_Post($id + 10, 'Inherited English title', 'Inherited English caption', 'Inherited English description');
		$GLOBALS['mcp_posts'][$id + 10]->post_parent = $id + 100;
		$GLOBALS['mcp_posts'][$id + 20] = new WP_Post($id + 20, 'Öffentliche Quelle', 'Öffentliche Bildunterschrift', 'Öffentliche Beschreibung');
	}
	$GLOBALS['mcp_meta'] = array(1 => 'Deutscher Alternativtext', 2 => 'English alt', 3 => 'Alternativtext ohne Medienpartner', 4 => 'Anderer Alternativtext', 7 => 'Alternativtext beibehalten', 8 => 'Original alt', 9 => '<img src=x onerror=bad()>Safe alt');
	$GLOBALS['mcp_pairs'] = array(1 => 2, 2 => 1, 4 => 5, 8 => 9, 31 => 21, 32 => 22, 33 => 23);
	$GLOBALS['mcp_strings'] = array(
		'attachment-3-title:en' => 'No separate media record', 'attachment-3-caption:en' => 'Caption without a separate media record',
		'attachment-3-description:en' => 'Description without a separate media record', 'attachment-3-alt:en' => 'Alt without a separate media record',
	);
	function add_filter($name, $callback, $priority = 10, $accepted = 1) { $GLOBALS['mcp_hooks'][$name][$priority][] = array($callback, $accepted); return true; }
	function add_action($name, $callback, $priority = 10, $accepted = 1) { return add_filter($name, $callback, $priority, $accepted); }
	function apply_filters($name, $value, ...$args) {
		$groups = $GLOBALS['mcp_hooks'][$name] ?? array(); ksort($groups);
		foreach ($groups as $callbacks) { foreach ($callbacks as [$callback, $accepted]) { $value = $callback(...array_slice(array_merge(array($value), $args), 0, $accepted)); } }
		return $value;
	}
	function do_action($name, ...$args) {
		$groups = $GLOBALS['mcp_hooks'][$name] ?? array(); ksort($groups);
		foreach ($groups as $callbacks) { foreach ($callbacks as [$callback, $accepted]) { $callback(...array_slice($args, 0, $accepted)); } }
	}
	function is_admin() { return 'admin' === $GLOBALS['mcp_test_mode']; }
	function current_user_can(...$args) { return false; }
	function get_post($id) { return isset($GLOBALS['mcp_posts'][$id]) ? clone $GLOBALS['mcp_posts'][$id] : null; }
	function is_post_publicly_viewable($post) {
		$status = $post->post_status;
		if ('inherit' === $status) { $parent = get_post($post->post_parent); $status = $parent ? $parent->post_status : 'publish'; }
		return 'publish' === $status;
	}
	function get_the_title($id) { $post = get_post($id); return apply_filters('the_title', $post ? $post->post_title : '', $id); }
	function get_post_meta($id, $key, $single = false) {
		$filtered = apply_filters('get_post_metadata', null, $id, $key, $single);
		if (null !== $filtered) { return $filtered; }
		$value = $GLOBALS['mcp_meta'][$id] ?? '';
		return $single ? $value : array($value);
	}
	function wp_register_ability(...$args) {}
	function sanitize_text_field($value) { return trim(strip_tags(preg_replace('#<script[^>]*>.*?</script>#si', '', $value))); }
	function wp_kses_post($value) { return strip_tags(preg_replace('#<script[^>]*>.*?</script>#si', '', $value), '<strong><em><p><br>'); }
	function wp_insert_post(...$args) { throw new \RuntimeException('Attachment/content creation is forbidden in this text Interface.'); }
	function wp_update_post(...$args) { throw new \RuntimeException('Source storage mutation is forbidden in this rendering Interface.'); }
	add_filter('wpml_current_language', static fn($value) => $GLOBALS['mcp_lang']);
	add_filter('wpml_element_language_details', static function($value, $args) { return (object) array('language_code' => in_array($args['element_id'], array(2, 5, 9, 21, 22, 23), true) ? 'en' : 'de'); }, 10, 2);
	add_filter('wpml_object_id', static function($id, $type, $fallback, $lang) {
		$source_lang = in_array($id, array(2, 5, 9, 21, 22, 23), true) ? 'en' : 'de';
		return $source_lang === $lang ? $id : ($GLOBALS['mcp_pairs'][$id] ?? null);
	}, 10, 4);
	add_action('wpml_register_single_string', static function($domain, $name, $value, $allow_empty, $source_lang) { $GLOBALS['mcp_registered'][$name] = compact('domain', 'value', 'source_lang'); }, 10, 5);
	add_filter('wpml_translate_single_string', static fn($value, $domain, $name, $lang) => $GLOBALS['mcp_strings'][$name . ':' . $lang] ?? $value, 10, 4);
}
namespace Elementor {
	class Control_Media { public static function get_image_title($image) { return empty($image['id']) ? '' : \get_the_title($image['id']); } }
	class Images_Manager {
		public function __construct() { \add_action('wp_ajax_elementor_get_images_details', array($this, 'get_images_details')); }
		public function get_images_details() { return 'native lifecycle'; }
		public function get_lightbox_image_attributes($id) {
			$p = \get_post($id); if (!$p) { return array(); }
			$fields = array('title' => $p->post_title, 'caption' => $p->post_excerpt, 'description' => $p->post_content, 'alt' => \get_post_meta($id, '_wp_attachment_image_alt', true));
			$kit = Plugin::$instance->kits_manager->get_active_kit(); $out = array();
			foreach (array('title', 'description') as $attribute) { $field = $kit->get_settings('lightbox_' . $attribute . '_src'); if ($field && !empty($fields[$field])) { $out[$attribute] = $fields[$field]; } }
			return $out;
		}
	}
	class Plugin { public static $instance; }
}
namespace ElementorPro\Modules\Gallery\Widgets {
	class Gallery {
		public function get_name() { return 'gallery'; }
		protected function is_dynamic_content(): bool { return false; }
		protected function get_image_data($attachment, $id, $src, $settings): array {
			return array('alt' => \get_post_meta($attachment->ID, '_wp_attachment_image_alt', true), 'title' => $attachment->post_title,
				'caption' => $attachment->post_excerpt, 'description' => $attachment->post_content,
				'media' => '/original/' . $id . '.jpg', 'src' => $src[0], 'width' => $src[1], 'height' => $src[2]);
		}
		public function native_call(int $id): array { return $this->get_image_data(\get_post($id), $id, array('/thumbnail/' . $id . '.jpg', 412, 309), array()); }
		public function native_cache_contract(): bool { return $this->is_dynamic_content(); }
	}
}
namespace {
	$kit = new class {
		public array $settings = array('lightbox_title_src' => 'title', 'lightbox_description_src' => 'description');
		public function get_settings($key) { return $this->settings[$key] ?? ''; }
	};
	$kits = new class($kit) { public $kit; public function __construct($kit) { $this->kit = $kit; } public function get_active_kit() { return $this->kit; } };
	\Elementor\Plugin::$instance = (object) array('images_manager' => new \Elementor\Images_Manager(), 'kits_manager' => $kits);
	$manager = new class {
		public array $widgets;
		public function __construct() { $this->widgets = array('gallery' => new \ElementorPro\Modules\Gallery\Widgets\Gallery()); }
		public function get_widget_types($name) { return $this->widgets[$name] ?? null; }
		public function unregister($name) { unset($this->widgets[$name]); }
		public function register($widget) { $this->widgets[$widget->get_name()] = $widget; }
	};
	$source_before = serialize(array($GLOBALS['mcp_posts'], $GLOBALS['mcp_meta'], $GLOBALS['mcp_pairs']));
	require getenv('MCP_WPML_TEST_ENTRY') ?: dirname(__DIR__) . '/mcp-abilities-sitepress.php';
	do_action('elementor/init'); do_action('elementor/widgets/register', $manager);
	$checks = 0;
	$assert = static function($condition, $message) use (&$checks) { $checks++; if (!$condition) { throw new \RuntimeException($message); } };
	$gallery = $manager->get_widget_types('gallery'); $images = \Elementor\Plugin::$instance->images_manager;
	$front = 'frontend' === $mode;
	$out = $gallery->native_call(1);
	$assert($out['title'] === ($front ? 'Windows with glazing bars' : 'Fenster mit Sprossen'), 'Native Gallery reads wrong-language original title.');
	$assert($out['caption'] === ($front ? 'English caption' : 'Deutsche Bildunterschrift'), 'Native Gallery caption language mismatch.');
	$assert($out['description'] === ($front ? 'English description' : 'Deutsche Beschreibung'), 'Native Gallery description language mismatch.');
	$assert($out['alt'] === ($front ? 'English alt' : 'Deutscher Alternativtext'), 'Native Gallery accessible label mismatch.');
	$assert($out['media'] === '/original/1.jpg' && $out['src'] === '/thumbnail/1.jpg' && $out['width'] === 412 && $out['height'] === 309, 'Gallery substituted media identity, URL, dimensions or crop.');
	$assert(\Elementor\Control_Media::get_image_title(array('id' => 1)) === ($front ? 'Windows with glazing bars' : 'Fenster mit Sprossen'), 'Native retained-ID image tooltip reads wrong-language attachment title.');
	$assert(get_the_title(6) === 'Eine Seite', 'Attachment text resolver changed an ordinary page title.');
	$assert(apply_filters('the_title', 'Custom editorial tooltip', 1) === 'Custom editorial tooltip', 'Explicit custom title from another native filter was overwritten.');
	$assert(apply_filters('the_title', '', 1) === '', 'Disabled/empty image title was enabled.');
	add_filter('the_title', static fn($title) => '[' . $title . ']', 10, 1);
	$assert(\Elementor\Control_Media::get_image_title(array('id' => 1)) === '[' . ($front ? 'Windows with glazing bars' : 'Fenster mit Sprossen') . ']', 'Native title formatting after translation was lost.');
	array_pop($GLOBALS['mcp_hooks']['the_title'][10]);

	$lightbox = $images->get_lightbox_image_attributes(1);
	$assert($lightbox['title'] === ($front ? 'Windows with glazing bars' : 'Fenster mit Sprossen') && $lightbox['description'] === ($front ? 'English description' : 'Deutsche Beschreibung'), 'Native lightbox attributes ignored translated text.');
	$kit->settings['lightbox_title_src'] = 'caption'; $kit->settings['lightbox_description_src'] = 'alt';
	$lightbox = $images->get_lightbox_image_attributes(1);
	$assert($lightbox['title'] === ($front ? 'English caption' : 'Deutsche Bildunterschrift') && $lightbox['description'] === ($front ? 'English alt' : 'Deutscher Alternativtext'), 'Lightbox ignored native Kit field selections.');
	$kit->settings['lightbox_title_src'] = ''; $kit->settings['lightbox_description_src'] = '';
	$assert(array() === $images->get_lightbox_image_attributes(1), 'Disabled native lightbox text was enabled.');
	$assert(count($GLOBALS['mcp_hooks']['wp_ajax_elementor_get_images_details'][10]) === 1 && $images->get_images_details() === 'native lifecycle', 'Images manager duplicated or removed native hooks.');
	$assert(\get_post_meta(1, '_wp_attachment_image_alt', true) === ($front ? 'English alt' : 'Deutscher Alternativtext'), 'Retained-ID image/carousel metadata path failed.');
	$assert(\get_post_meta(1, '_wp_attachment_image_alt', false) === array($front ? 'English alt' : 'Deutscher Alternativtext'), 'Metadata multi-value shape changed.');
	if ($front) {
		$missing = $gallery->native_call(3);
		$assert($missing['title'] === 'No separate media record' && $missing['caption'] === 'Caption without a separate media record' && $missing['alt'] === 'Alt without a separate media record', 'Native WPML strings did not cover a missing attachment translation.');
		$assert($GLOBALS['mcp_registered']['attachment-3-title'] === array('domain' => 'MCP Media Text', 'value' => 'Deutsch ohne Medienpartner', 'source_lang' => 'de'), 'Wrong source language/identity registered for native strings.');
		$pending = $gallery->native_call(7);
		$assert($pending['title'] === 'Quelle ohne fertigen Text' && $pending['caption'] === 'Bildunterschrift beibehalten', 'Missing translations were hidden or invented.');
		$private_source = $gallery->native_call(10); $assert($private_source['title'] === 'Private Quelle' && !isset($GLOBALS['mcp_registered']['attachment-10-title']), 'Private source text entered public String Translation registration.');
		$private = $gallery->native_call(4); $assert($private['title'] === 'Andere Quelle', 'Private translated attachment text was exposed.');
		foreach (array(11, 12) as $id) {
			$inherited = $gallery->native_call($id);
			$assert($inherited['title'] === 'Geerbte Quelle' && !isset($GLOBALS['mcp_registered']['attachment-' . $id . '-title']), 'Source text inherited from a private/draft parent entered public registration.');
			$target_inherited = $gallery->native_call($id + 20);
			$assert($target_inherited['title'] === 'Öffentliche Quelle', 'Translated attachment text inherited from a private/draft parent was exposed.');
		}
		$public_inherited = $gallery->native_call(33);
		$assert($public_inherited['title'] === 'Inherited English title', 'Public inherited attachment translation was rejected.');
		$safe = $gallery->native_call(8); $assert($safe['title'] === 'Safe title' && $safe['caption'] === '<strong>Credit</strong>' && $safe['alt'] === 'Safe alt', 'Translated metadata bypassed native text/HTML sanitization.');
		$assert($gallery->native_cache_contract(), 'Language-dependent Gallery text can be served as a static fragment.');
		$GLOBALS['mcp_lang'] = 'de'; $original = $gallery->native_call(1);
		$assert($original['title'] === 'Fenster mit Sprossen' && $original['caption'] === 'Deutsche Bildunterschrift' && $original['alt'] === 'Deutscher Alternativtext', 'Translated text leaked back into the original language.');
		$assert(\Elementor\Control_Media::get_image_title(array('id' => 1)) === 'Fenster mit Sprossen', 'Translated image tooltip leaked into the original language.');
	}
	$assert($source_before === serialize(array($GLOBALS['mcp_posts'], $GLOBALS['mcp_meta'], $GLOBALS['mcp_pairs'])), 'Rendering mutated source/target posts, media metadata or WPML relationships.');
	echo json_encode(array('success' => true, 'mode' => $mode, 'native_interface_checks' => $checks, 'source_and_media_unchanged' => true), JSON_PRETTY_PRINT) . PHP_EOL;
}
