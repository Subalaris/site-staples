<?php
/** Opt out of selected intermediate image sizes without deleting existing files. @package SiteStaples */

namespace SiteStaples\Modules;

final class ImageSizes {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'filter' ), 99 );
		// Covers clients that request registered sizes before sending an upload.
		add_filter( 'intermediate_image_sizes', array( $this, 'names' ), 99 );
	}

	public function filter( $sizes ) {
		return array_diff_key( $sizes, array_fill_keys( $this->settings['disabled_sizes'], true ) );
	}

	public function names( $sizes ) {
		return array_values( array_diff( $sizes, $this->settings['disabled_sizes'] ) );
	}
}
