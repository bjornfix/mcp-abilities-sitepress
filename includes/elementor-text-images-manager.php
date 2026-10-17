<?php
/** Native Elementor lightbox text Adapter. No renderer, assets or image substitution. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

class MCP_WPML_Elementor_Text_Images_Manager extends \Elementor\Images_Manager {
	private \Elementor\Images_Manager $native;

	/** The original manager retains its registered AJAX/deletion hooks. */
	public function __construct(\Elementor\Images_Manager $native) { $this->native = $native; }

	public function get_lightbox_image_attributes($id) {
		$attributes = $this->native->get_lightbox_image_attributes($id);
		if (!MCP_WPML_Attachment_Text::frontend()) { return $attributes; }
		$text = MCP_WPML_Attachment_Text::get((int) $id);
		if (!$text) { return $attributes; }
		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		foreach (array('title', 'description') as $attribute) {
			$field = $kit->get_settings('lightbox_' . $attribute . '_src');
			if (is_string($field) && isset($text[$field]) && '' !== $text[$field]) { $attributes[$attribute] = $text[$field]; }
		}
		return $attributes;
	}
}
