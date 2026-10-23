<?php
/** Maintenance responses with an explicit access bypass and recovery path. @package SiteStaples */

namespace SiteStaples\Modules;

final class Maintenance {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_action( 'template_redirect', array( $this, 'display' ), 0 );
		add_filter( 'rest_pre_dispatch', array( $this, 'rest' ), 1, 3 );
		add_action( 'admin_bar_menu', array( $this, 'toolbar' ), 100 );
	}

	public function allowed() {
		return current_user_can( 'manage_options' ) || ( is_user_logged_in() && (bool) array_intersect( wp_get_current_user()->roles, $this->settings['roles'] ) );
	}

	/** Plain text configuration is escaped here, never interpreted as HTML. */
	public function html() {
		$title = $this->settings['title'] ?: __( 'Temporarily unavailable', 'site-staples' );
		$message = $this->settings['message'] ?: __( 'We are making improvements. Please check back soon.', 'site-staples' );
		return '<h1>' . esc_html( $title ) . '</h1><p>' . nl2br( esc_html( $message ) ) . '</p>';
	}

	public function display() {
		if ( $this->allowed() || is_admin() ) {
			return;
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Retry-After: 3600' );
		}
		// wp_die provides WordPress's accessible standalone page with HTTP 503.
		wp_die( $this->html(), esc_html__( 'Maintenance', 'site-staples' ), array( 'response' => 503 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes all configurable text.
	}

	public function rest( $result, $server, $request ) {
		if ( $this->allowed() ) {
			return $result;
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Retry-After: 3600' );
		}
		return new \WP_Error( 'site_staples_maintenance', __( 'This site is temporarily unavailable for maintenance.', 'site-staples' ), array( 'status' => 503 ) );
	}

	public function toolbar( $bar ) {
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node( array( 'id' => 'site-staples-maintenance', 'title' => __( 'Maintenance mode is on', 'site-staples' ), 'href' => admin_url( 'options-general.php?page=site-staples' ) ) );
		}
	}
}
