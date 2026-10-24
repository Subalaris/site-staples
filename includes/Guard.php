<?php
/** Shared administrative request guards. @package SiteStaples */

namespace SiteStaples;

final class Guard {
	/** All state-changing actions are POST requests with a scoped nonce. */
	public static function post( $nonce_action, $capability = 'manage_options' ) {
		if ( ! current_user_can( $capability ) || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'site-staples' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action, $nonce_action . '_nonce' );
	}

	/** Give independent action forms distinct nonce IDs and names. */
	public static function nonce( $action ) {
		wp_nonce_field( $action, $action . '_nonce' );
	}

	/** Render errors without exposing server paths or raw exception messages. */
	public static function result( $result ) {
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Site Staples', 'site-staples' ), array( 'back_link' => true ) );
		}
		return $result;
	}

	/** Return to the settings screen with an allowlisted message code. */
	public static function redirect( $code ) {
		$anchors = array( 'revision_preview' => '#staples-revision-tools', 'import_preview' => '#staples-transfer-tools' );
		$url = add_query_arg( 'staples_notice', $code, admin_url( 'options-general.php?page=site-staples' ) );
		wp_safe_redirect( $url . ( $anchors[ $code ] ?? '' ) );
		exit;
	}
}
