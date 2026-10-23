<?php
/** Scoped comment suppression without persistent content changes. @package SiteStaples */

namespace SiteStaples\Modules;

final class Comments {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_filter( 'comments_open', array( $this, 'open' ), 99, 2 );
		add_filter( 'pings_open', array( $this, 'open' ), 99, 2 );
		add_filter( 'render_block', array( $this, 'block' ), 99, 3 );
		add_filter( 'comments_array', array( $this, 'comments' ), 99, 2 );
		add_filter( 'get_comments_number', array( $this, 'count' ), 99, 2 );
		add_filter( 'pre_comment_approved', array( $this, 'approve' ), 99, 2 );
		add_filter( 'rest_pre_insert_comment', array( $this, 'rest' ), 99, 2 );
		add_action( 'init', array( $this, 'supports' ), 99 );
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
		add_action( 'admin_bar_menu', array( $this, 'toolbar' ), 99 );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard' ) );
		add_action( 'add_meta_boxes', array( $this, 'metaboxes' ), 99, 2 );
		add_action( 'admin_init', array( $this, 'screen' ) );
		add_action( 'template_redirect', array( $this, 'feed' ) );
	}

	public function affected( $post_id ) {
		return 'all' === $this->settings['scope'] || in_array( get_post_type( $post_id ), $this->settings['post_types'], true );
	}

	public function open( $open, $post_id ) {
		return $this->affected( $post_id ) ? false : $open;
	}

	public function comments( $comments, $post_id ) {
		return $this->affected( $post_id ) ? array() : $comments;
	}

	/** Block themes query comments separately from the classic comments template. */
	public function block( $content, $block, $instance = null ) {
		$name = $block['blockName'] ?? '';
		if ( ! str_starts_with( $name, 'core/comment' ) && ! str_starts_with( $name, 'core/post-comments' ) ) {
			return $content;
		}
		$id = $instance->context['postId'] ?? get_the_ID();
		return $this->affected( $id ) ? '' : $content;
	}

	public function count( $count, $post_id ) {
		return $this->affected( $post_id ) ? 0 : $count;
	}

	public function approve( $approved, $data ) {
		return $this->affected( $data['comment_post_ID'] ?? 0 ) ? $this->error() : $approved;
	}

	public function rest( $comment, $request ) {
		return $this->affected( $request->get_param( 'post' ) ) ? $this->error() : $comment;
	}

	private function error() {
		return new \WP_Error( 'site_staples_comments_closed', __( 'Comments and pings are disabled for this content.', 'site-staples' ), array( 'status' => 403 ) );
	}

	public function supports() {
		$types = 'all' === $this->settings['scope'] ? get_post_types() : $this->settings['post_types'];
		foreach ( $types as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
			add_filter( 'manage_' . $type . '_posts_columns', array( $this, 'columns' ) );
		}
	}

	public function columns( $columns ) {
		unset( $columns['comments'] );
		return $columns;
	}

	public function menu() {
		if ( 'all' === $this->settings['scope'] ) {
			remove_menu_page( 'edit-comments.php' );
			remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		}
	}

	public function toolbar( $bar ) {
		if ( 'all' === $this->settings['scope'] || ( is_singular() && $this->affected( get_queried_object_id() ) ) ) {
			$bar->remove_node( 'comments' );
		}
	}

	public function dashboard() {
		if ( 'all' === $this->settings['scope'] ) {
			remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
			// Activity includes both posts and comments; remove it only in global mode.
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		}
	}

	public function metaboxes( $post_type, $post ) {
		if ( $this->affected( $post->ID ) ) {
			remove_meta_box( 'commentstatusdiv', $post_type, 'normal' );
			remove_meta_box( 'commentsdiv', $post_type, 'normal' );
		}
	}

	public function screen() {
		global $pagenow;
		if ( 'all' === $this->settings['scope'] && in_array( $pagenow, array( 'edit-comments.php', 'options-discussion.php', 'comment.php' ), true ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=site-staples' ) );
			exit;
		}
	}

	public function feed() {
		if ( is_comment_feed() && ( 'all' === $this->settings['scope'] || ( is_singular() && $this->affected( get_queried_object_id() ) ) ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Comment feeds are disabled.', 'site-staples' ), '', array( 'response' => 403 ) );
		}
	}
}
