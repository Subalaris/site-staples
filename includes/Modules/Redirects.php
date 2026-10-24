<?php
/** Exact local redirects with validation and graph cycle prevention. @package SiteStaples */

namespace SiteStaples\Modules;

use SiteStaples\RedirectRules;
use SiteStaples\RedirectStore;

final class Redirects {
	const MAX_RULES = RedirectRules::MAX_RULES;
	private $settings;
	private $map;

	public function __construct( $settings ) {
		$this->settings = $settings;
		// Legacy/in-memory configurations compile once; migrated sites use indexed storage.
		$this->map = isset( $settings['generation'] ) ? null : RedirectRules::compile( $settings['rules'] );
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
		if ( isset( $this->settings['generation'] ) ) {
			if ( ( $this->settings['store_home'] ?? '' ) !== RedirectStore::site_key() ) {
				return false;
			}
			$rule = RedirectStore::lookup( $this->settings['generation'], $path );
		} else {
			$rule = is_wp_error( $this->map ) ? false : ( $this->map[ $path ] ?? false );
		}
		if ( ! is_array( $rule ) || ! isset( $rule['to'], $rule['status'] ) || ! in_array( $rule['status'], array( 301, 302 ), true ) || false === RedirectRules::path( $rule['to'] ) || $path === $rule['to'] || RedirectRules::protected_path( $rule['to'] ) ) {
			return false;
		}
		$status = $rule['status'];
		$path = $rule['to'];
		$home = wp_parse_url( home_url( '/' ) );
		$origin = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		return array( 'url' => $origin . $path, 'status' => $status );
	}

	public function redirect() {
		if ( did_action( 'site_staples_maintenance_page' ) || is_admin() || is_preview() || is_feed() || is_trackback() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed with the strict local path grammar above.
		$match = $this->match( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) );
		if ( $match ) {
			nocache_headers();
			if ( wp_safe_redirect( $match['url'], $match['status'], 'Site Staples' ) ) {
				exit;
			}
		}
	}
}
