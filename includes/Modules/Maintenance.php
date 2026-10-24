<?php
/** Maintenance responses with an explicit access bypass and recovery path. @package SiteStaples */

namespace SiteStaples\Modules;

use SiteStaples\Settings;

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

	/** Set the selected page context before rendering it in the maintenance canvas. */
	public function prepare_page() {
		$page = Settings::maintenance_page( $this->settings['page_id'] ?? 0 );
		if ( ! $page ) {
			return false;
		}
		global $wp_query, $wp_the_query, $post;
		$previous = array( $wp_query, $wp_the_query, $post );
		$query = new \WP_Query();
		$wp_query = $query;
		$wp_the_query = $query;
		$query->query( array( 'page_id' => $page->ID, 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
		// Query filters must never turn the maintenance response into unrelated or private content.
		if ( ! is_array( $query->posts ) || 1 !== count( $query->posts ) || (int) $query->posts[0]->ID !== (int) $page->ID || 'publish' !== $query->posts[0]->post_status || '' !== (string) ( $query->posts[0]->post_password ?? '' ) || ! Settings::maintenance_page( $query->posts[0]->ID ) ) {
			list( $wp_query, $wp_the_query, $post ) = $previous;
			return false;
		}
		$post = $query->posts[0];
		setup_postdata( $post );
		show_admin_bar( false );
		// Keep the original URL rather than redirecting to the chosen page's permalink.
		remove_action( 'template_redirect', 'redirect_canonical' );
		remove_action( 'template_redirect', 'wp_old_slug_redirect' );
		add_filter( 'template_include', array( $this, 'page_template' ), PHP_INT_MAX );
		do_action( 'site_staples_maintenance_page', $page->ID );
		return true;
	}

	/** Serve only the page content; retain asset hooks without loading theme header/footer templates. */
	public function page_template( $template ) {
		nocache_headers();
		status_header( 503 );
		if ( ! headers_sent() ) {
			header( 'Retry-After: 3600' );
		}
		$canvas = dirname( __DIR__ ) . '/templates/maintenance-page.php';
		if ( ! is_file( $canvas ) ) {
			wp_die( $this->html(), esc_html__( 'Maintenance', 'site-staples' ), array( 'response' => 503 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes all configurable text.
		}
		// The canvas includes its own viewport metadata for both classic and block themes.
		remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 );
		return $canvas;
	}

	public function display() {
		if ( $this->allowed() || is_admin() ) {
			return;
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Retry-After: 3600' );
		}
		if ( $this->prepare_page() ) {
			status_header( 503 );
			return;
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
			$bar->add_node( array( 'id' => 'site-staples-maintenance', 'title' => __( 'Maintenance mode is on', 'site-staples' ), 'href' => admin_url( 'options-general.php?page=site-staples' ) . '#staples-group-site' ) );
		}
	}
}
