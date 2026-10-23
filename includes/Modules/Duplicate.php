<?php
/** Permission-aware draft duplication. @package SiteStaples */

namespace SiteStaples\Modules;

use SiteStaples\Guard;

final class Duplicate {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_filter( 'post_row_actions', array( $this, 'actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'actions' ), 10, 2 );
		add_action( 'admin_post_site_staples_duplicate', array( $this, 'handle' ) );
	}

	public function permitted( $post ) {
		$type = $post ? get_post_type_object( $post->post_type ) : null;
		return $type && in_array( $post->post_type, $this->settings['post_types'], true ) && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) && current_user_can( 'read_post', $post->ID ) && current_user_can( $type->cap->create_posts );
	}

	public function actions( $actions, $post ) {
		if ( $this->permitted( $post ) ) {
			// A native button submits a hidden POST form rendered once in the footer.
			$actions['site_staples_duplicate'] = sprintf(
				'<button type="submit" class="button-link" form="site-staples-duplicate-%1$d">%2$s</button>',
				$post->ID,
				esc_html__( 'Duplicate as draft', 'site-staples' )
			);
			add_action( 'admin_footer', function () use ( $post ) {
				echo '<form hidden id="site-staples-duplicate-' . esc_attr( $post->ID ) . '" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
				echo '<input type="hidden" name="action" value="site_staples_duplicate"><input type="hidden" name="post_id" value="' . esc_attr( $post->ID ) . '">';
				Guard::nonce( 'site_staples_duplicate_' . $post->ID );
				echo '</form>';
			} );
		}
		return $actions;
	}

	/** Conservative metadata policy: public fields and known presentation fields. */
	public static function copy_meta_key( $key, $post ) {
		$excluded = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug', '_wp_attached_file', '_wp_attachment_metadata', '_encloseme', '_pingme', 'enclosure', '_sku', '_global_unique_id' );
		$unique = preg_match( '/(?:^|_)(?:id|uuid|guid|sku|token|secret|password|external_id|unique_id)(?:_|$)/i', $key );
		if ( in_array( $key, $excluded, true ) || ( $unique && '_thumbnail_id' !== $key ) ) {
			return false;
		}
		$copy = ! is_protected_meta( $key, 'post' ) || in_array( $key, array( '_thumbnail_id', '_wp_page_template' ), true );
		/** Third parties may opt appropriate protected fields in; hard exclusions remain excluded. */
		return (bool) apply_filters( 'site_staples_duplicate_meta_key', $copy, $key, $post );
	}

	public function duplicate( $source_id ) {
		$post = get_post( $source_id );
		if ( ! $this->permitted( $post ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot read this source or create this post type.', 'site-staples' ) );
		}
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
				return new \WP_Error( 'terms', __( 'You cannot assign the source taxonomies.', 'site-staples' ) );
			}
		}
		$data = array(
			'post_type'    => $post->post_type,
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
			/* translators: %s: source post title. */
			'post_title'   => sprintf( __( '%s (copy)', 'site-staples' ), $post->post_title ),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_parent'  => $post->post_parent,
			'menu_order'   => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'  => $post->ping_status,
		);
		$id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		try {
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_object_terms( $source_id, $taxonomy->name, array( 'fields' => 'ids' ) );
				if ( is_wp_error( $terms ) || is_wp_error( wp_set_object_terms( $id, array_map( 'intval', $terms ), $taxonomy->name ) ) ) {
					throw new \RuntimeException();
				}
			}
			foreach ( get_post_meta( $source_id ) as $key => $values ) {
				if ( self::copy_meta_key( $key, $post ) ) {
					foreach ( $values as $value ) {
						if ( ! add_post_meta( $id, $key, wp_slash( maybe_unserialize( $value ) ) ) ) {
							throw new \RuntimeException();
						}
					}
				}
			}
		} catch ( \Throwable $exception ) {
			wp_delete_post( $id, true );
			return new \WP_Error( 'copy_failed', __( 'The draft could not be completely copied. No duplicate was retained.', 'site-staples' ) );
		}
		return $id;
	}

	public function handle() {
		$id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		Guard::post( 'site_staples_duplicate_' . $id, 'read' );
		$new_id = Guard::result( $this->duplicate( $id ) );
		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}
}
