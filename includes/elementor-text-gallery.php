<?php
/** Native Elementor Pro Gallery metadata Adapter; all layout and media rendering are inherited. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

class MCP_WPML_Elementor_Text_Gallery extends \ElementorPro\Modules\Gallery\Widgets\Gallery {
	protected function get_image_data($attachment, $image_id, $image_src, $settings): array {
		$data = parent::get_image_data($attachment, $image_id, $image_src, $settings);
		if (!MCP_WPML_Attachment_Text::frontend()) { return $data; }
		foreach (MCP_WPML_Attachment_Text::get((int) $image_id) as $field => $text) { $data[$field] = $text; }
		return $data;
	}

	/** Gallery text varies by WPML language and current attachment/string edits. */
	protected function is_dynamic_content(): bool { return true; }
}
