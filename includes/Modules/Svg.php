<?php
/** SVG uploads with a bundled sanitizer and fail-closed validation. @package SiteStaples */

namespace SiteStaples\Modules;

final class Svg {
	const MAX_BYTES = 2097152;
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_filter( 'upload_mimes', array( $this, 'mimes' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'prefilter' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'prefilter' ) );
		// Validation here also covers callers using custom upload action names.
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'filetype' ), 99, 5 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'preview' ), 10, 2 );
		add_filter( 'wp_handle_upload', array( $this, 'final_upload' ) );
		add_filter( 'image_downsize', array( $this, 'downsize' ), 10, 2 );
	}

	public function allowed() {
		return current_user_can( 'upload_files' ) && ( current_user_can( 'manage_options' ) || array_intersect( wp_get_current_user()->roles, $this->settings['roles'] ) );
	}

	public function mimes( $mimes ) {
		unset( $mimes['svg'], $mimes['svgz'] );
		if ( $this->allowed() && class_exists( 'DOMDocument' ) && class_exists( 'finfo' ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return is_multisite() ? check_upload_mimes( $mimes ) : $mimes;
	}

	/** Sanitize bytes, require a real SVG root and strip fetch-capable CSS/links. */
	public static function sanitize( $dirty ) {
		if ( ! class_exists( 'DOMDocument' ) || ! is_string( $dirty ) || '' === $dirty || strlen( $dirty ) > self::MAX_BYTES || preg_match( '/<!DOCTYPE|<!ENTITY|<\?(?!xml\s)/i', $dirty ) ) {
			return self::error();
		}
		try {
			$sanitizer = new \SiteStaples\Vendor\SvgSanitize\Sanitizer();
			$sanitizer->removeRemoteReferences( true );
			$clean = $sanitizer->sanitize( $dirty );
			$doc = new \DOMDocument();
			$previous = libxml_use_internal_errors( true );
			$loaded = $clean && $doc->loadXML( $clean, LIBXML_NONET );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
			if ( ! $loaded || ! $doc->documentElement || 'svg' !== $doc->documentElement->localName || 'http://www.w3.org/2000/svg' !== $doc->documentElement->namespaceURI ) {
				return self::error();
			}
			foreach ( iterator_to_array( $doc->getElementsByTagName( '*' ) ) as $element ) {
				if ( 'http://www.w3.org/2000/svg' !== $element->namespaceURI || in_array( strtolower( $element->localName ), array( 'style', 'animatecolor', 'animatemotion', 'animatetransform' ), true ) ) {
					$element->parentNode->removeChild( $element );
					continue;
				}
				foreach ( iterator_to_array( $element->attributes ) as $attribute ) {
					$name = strtolower( $attribute->localName );
					$value = trim( $attribute->value );
					if ( in_array( $name, array( 'style', 'srcset', 'poster', 'background', 'action', 'formaction' ), true ) || str_contains( $value, '\\' ) || ( in_array( $name, array( 'href', 'src' ), true ) && ! str_starts_with( $value, '#' ) ) || ( str_contains( strtolower( $value ), 'url(' ) && ! preg_match( '/^url\(\s*[\'"]?#[a-zA-Z0-9_-]+[\'"]?\s*\)$/D', $value ) ) ) {
						$element->removeAttributeNode( $attribute );
					}
				}
			}
			return $doc->saveXML( $doc->documentElement );
		} catch ( \Throwable $exception ) {
			return self::error();
		}
	}

	private static function error() {
		return new \WP_Error( 'unsafe_svg', __( 'This SVG could not be safely sanitized. Use a valid SVG with an SVG namespace, no DTD/entities, and a maximum size of 2 MB.', 'site-staples' ) );
	}

	/** Validate and rewrite the temporary upload before WordPress moves it. */
	private function clean_file( $path ) {
		if ( ! $this->allowed() || ! is_file( $path ) || filesize( $path ) > self::MAX_BYTES ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload only.
		$clean = self::sanitize( file_get_contents( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary local upload only.
		return ! is_wp_error( $clean ) && false !== file_put_contents( $path, $clean, LOCK_EX );
	}

	public function prefilter( $file ) {
		if ( 'svg' === strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) && ! $this->clean_file( $file['tmp_name'] ) ) {
			$file['error'] = __( 'SVG upload denied: check your role and provide a safely sanitizable SVG under 2 MB.', 'site-staples' );
		}
		return $file;
	}

	public function filetype( $data, $file, $filename, $mimes, $real_mime = false ) {
		if ( 'svg' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			return $data;
		}
		$allowed = $mimes ?? get_allowed_mime_types();
		$expected = wp_check_filetype( $filename, $allowed );
		if ( 'image/svg+xml' === $expected['type'] && $this->clean_file( $file ) ) {
			return array( 'ext' => 'svg', 'type' => 'image/svg+xml', 'proper_filename' => false );
		}
		return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
	}

	/** Final safeguard for custom upload actions and pre-existing unfiltered users. */
	public function final_upload( $upload ) {
		if ( ! empty( $upload['file'] ) && 'svg' === strtolower( pathinfo( $upload['file'], PATHINFO_EXTENSION ) ) && ( ! in_array( 'image/svg+xml', get_allowed_mime_types(), true ) || ! $this->clean_file( $upload['file'] ) ) ) {
			wp_delete_file( $upload['file'] );
			return array( 'error' => __( 'SVG upload rejected: role, MIME policy, or sanitization failed.', 'site-staples' ) );
		}
		return $upload;
	}

	public function downsize( $out, $id ) {
		if ( 'image/svg+xml' === get_post_mime_type( $id ) ) {
			return array( wp_get_attachment_url( $id ), 300, 150, false );
		}
		return $out;
	}

	public function preview( $response, $attachment ) {
		if ( 'image/svg+xml' === $attachment->post_mime_type ) {
			$response['image'] = array( 'src' => $response['url'], 'width' => 300, 'height' => 150 );
			$response['sizes'] = array( 'full' => array( 'url' => $response['url'], 'width' => 300, 'height' => 150, 'orientation' => 'landscape' ) );
			$response['icon'] = $response['url'];
		}
		return $response;
	}
}
