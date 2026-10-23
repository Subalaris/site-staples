<?php
/** Explicit opt-in removal of configuration, never site content. @package SiteStaples */

namespace SiteStaples;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Delete only this plugin’s settings and temporary administrative state. */
function uninstall_site() {
	$settings = get_option( 'site_staples_settings' );
	if ( ! is_array( $settings ) || true !== ( $settings['uninstall'] ?? false ) ) {
		return;
	}
	delete_option( 'site_staples_settings' );
	global $wpdb;
	$prefixes = array( '_transient_site_staples_', '_transient_timeout_site_staples_', 'site_staples_media_lock_', 'site_staples_revision_lock_' );
	foreach ( $prefixes as $prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Delete only plugin-owned options; no content tables.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		foreach ( $names as $name ) {
			delete_option( $name );
		}
	}
}

if ( is_multisite() ) {
	// Iterate bounded pages; each site independently chooses whether to erase its options.
	$offset = 0;
	do {
		$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $offset ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			uninstall_site();
			restore_current_blog();
		}
		$offset += 100;
	} while ( count( $site_ids ) === 100 );
} else {
	uninstall_site();
}
