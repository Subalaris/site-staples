<?php
/** Optional, unsortable content information columns with native thumbnails. @package SiteStaples */

namespace SiteStaples\Modules;

final class Columns {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_action( 'init', array( $this, 'types' ), 100 );
	}

	public function types() {
		foreach ( $this->settings['post_types'] as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			add_filter( 'manage_' . $type . '_posts_columns', array( $this, 'columns' ) );
			add_action( 'manage_' . $type . '_posts_custom_column', array( $this, 'cell' ), 10, 2 );
		}
	}

	public function columns( $columns ) {
		if ( $this->settings['show_id'] ) {
			$columns['site_staples_id'] = __( 'ID', 'site-staples' );
		}
		if ( $this->settings['show_thumbnail'] ) {
			$columns['site_staples_thumbnail'] = __( 'Featured image', 'site-staples' );
		}
		if ( $this->settings['show_modified'] ) {
			$columns['site_staples_modified'] = __( 'Last modified', 'site-staples' );
		}
		return $columns;
	}

	public function cell( $column, $id ) {
		if ( ! current_user_can( 'read_post', $id ) ) {
			return;
		}
		switch ( $column ) {
			case 'site_staples_id':
				if ( $this->settings['show_id'] ) {
					echo esc_html( $id );
				}
				break;
			case 'site_staples_thumbnail':
				if ( $this->settings['show_thumbnail'] ) {
					$image = get_the_post_thumbnail( $id, array( 50, 50 ), array( 'alt' => '', 'loading' => 'lazy' ) );
					echo $image ?: esc_html__( 'No featured image', 'site-staples' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated image markup.
				}
				break;
			case 'site_staples_modified':
				if ( $this->settings['show_modified'] ) {
					echo esc_html( get_post_modified_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $id, true ) );
				}
				break;
		}
	}
}
