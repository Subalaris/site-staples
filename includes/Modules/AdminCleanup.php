<?php
/** Presentation-only admin cleanup by role. @package SiteStaples */

namespace SiteStaples\Modules;

final class AdminCleanup {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function applies() {
		return (bool) array_intersect( wp_get_current_user()->roles, $this->settings['roles'] );
	}

	public function register() {
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard' ), 99 );
		add_action( 'admin_bar_menu', array( $this, 'toolbar' ), 999 );
	}

	public function dashboard() {
		if ( ! $this->applies() ) {
			return;
		}
		foreach ( $this->settings['widgets'] as $id ) {
			foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $context ) {
				remove_meta_box( $id, 'dashboard', $context );
			}
		}
	}

	public function toolbar( $bar ) {
		if ( $this->applies() ) {
			foreach ( $this->settings['toolbar'] as $id ) {
				$bar->remove_node( $id );
			}
		}
	}
}
