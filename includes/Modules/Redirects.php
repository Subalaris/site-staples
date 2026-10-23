<?php
/** Exact local redirects with validation and graph cycle prevention. @package SiteStaples */

namespace SiteStaples\Modules;

use SiteStaples\RedirectRules;

final class Redirects {
	const MAX_RULES = 100;
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_action( 'template_redirect', array( $this, 'redirect' ), 5 );
	}

	/** Resolve chains in one hop; query strings are deliberately not forwarded. */
	public function match( $uri, $method = 'GET' ) {
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || ! is_string( $uri ) ) {
			return false;
		}
		$query = wp_parse_url( $uri, PHP_URL_QUERY );
		if ( $query ) {
			parse_str( $query, $params );
			if ( array_intersect( array_keys( $params ), array( 'rest_route', 'preview', 'preview_id', 'customize_changeset_uuid' ) ) ) {
				return false;
			}
		}
		$path = RedirectRules::path( wp_parse_url( $uri, PHP_URL_PATH ) );
		if ( false === $path || RedirectRules::protected_path( $path ) ) {
			return false;
		}
		$rules = $this->settings['rules'];
		if ( is_wp_error( RedirectRules::validate_rules( $rules ) ) ) {
			return false;
		}
		$map = array();
		foreach ( $rules as $rule ) {
			$map[ RedirectRules::path( $rule['from'] ) ] = $rule;
		}
		if ( ! isset( $map[ $path ] ) ) {
			return false;
		}
		$status = $map[ $path ]['status'];
		while ( isset( $map[ $path ] ) ) {
			$path = RedirectRules::path( $map[ $path ]['to'] );
		}
		$home = wp_parse_url( home_url( '/' ) );
		$origin = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		return array( 'url' => $origin . $path, 'status' => $status );
	}

	public function redirect() {
		if ( is_admin() || is_preview() || is_feed() || is_trackback() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed with the strict local path grammar above.
		$match = $this->match( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), $_SERVER['REQUEST_METHOD'] ?? '' );
		if ( $match ) {
			nocache_headers();
			if ( wp_safe_redirect( $match['url'], $match['status'], 'Site Staples' ) ) {
				exit;
			}
		}
	}
}
