<?php
/** Future revision limits and explicitly confirmed, bounded cleanup. @package SiteStaples */

namespace SiteStaples\Modules;

use SiteStaples\Guard;
use SiteStaples\Settings;

final class Revisions {
	const BATCH = 100;
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_filter( 'wp_revisions_to_keep', array( $this, 'limit' ), 99, 2 );
		add_action( 'admin_post_site_staples_revision_preview', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_site_staples_revision_cleanup', array( $this, 'handle_cleanup' ) );
	}

	public function limit( $limit, $post ) {
		return post_type_supports( $post->post_type, 'revisions' ) ? $this->settings['limit'] : $limit;
	}

	/** SQL selects only normal revisions older than the newest N normal revisions.
	 * Autosaves do not count toward the limit and are never candidates. A snapshot
	 * watermark keeps revisions created after preview out of the cleanup run.
	 */
	public function candidates( $watermark, $count = false, $limit = self::BATCH ) {
		global $wpdb;
		if ( $count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Prepared cross-revision count; reflects live content for cleanup confirmation.
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} r INNER JOIN {$wpdb->posts} p ON p.ID = r.post_parent
			WHERE r.post_type = 'revision' AND r.post_status = 'inherit' AND r.ID <= %d
			AND r.post_name NOT LIKE %s
			AND (SELECT COUNT(*) FROM {$wpdb->posts} n WHERE n.post_parent = r.post_parent
			AND n.post_type = 'revision' AND n.post_status = 'inherit' AND n.ID <= %d
			AND n.post_name NOT LIKE %s AND n.ID > r.ID) >= %d",
				$watermark, '%autosave%', $watermark, '%autosave%', $this->settings['limit']
			) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Prepared and bounded cross-revision query; candidates are revalidated before deletion.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT r.ID FROM {$wpdb->posts} r INNER JOIN {$wpdb->posts} p ON p.ID = r.post_parent
			WHERE r.post_type = 'revision' AND r.post_status = 'inherit' AND r.ID <= %d
			AND r.post_name NOT LIKE %s
			AND (SELECT COUNT(*) FROM {$wpdb->posts} n WHERE n.post_parent = r.post_parent
			AND n.post_type = 'revision' AND n.post_status = 'inherit' AND n.ID <= %d
			AND n.post_name NOT LIKE %s AND n.ID > r.ID) >= %d ORDER BY r.ID ASC LIMIT %d",
			$watermark, '%autosave%', $watermark, '%autosave%', $this->settings['limit'], min( self::BATCH, absint( $limit ) )
		) ) );
	}

	public function preview() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Snapshot watermark only.
		$watermark = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
		return array( 'watermark' => $watermark, 'count' => $this->candidates( $watermark, true ), 'limit' => $this->settings['limit'], 'deleted' => 0 );
	}

	/** One synchronous batch; retry is safe and candidates are revalidated. */
	public function cleanup( $state ) {
		if ( ! current_user_can( 'manage_options' ) || $state['limit'] !== $this->settings['limit'] ) {
			return new \WP_Error( 'changed', __( 'Permissions or the revision limit changed. Preview cleanup again.', 'site-staples' ) );
		}
		$lock = 'site_staples_revision_lock_' . get_current_blog_id();
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'busy', __( 'Another revision cleanup is running. Wait for it to finish and preview again.', 'site-staples' ) );
		}
		try {
			return $this->cleanup_batch( $state );
		} finally {
			delete_option( $lock );
		}
	}

	/** Remove one bounded batch while holding the per-site cleanup lock. */
	private function cleanup_batch( $state ) {
		$ids = $this->candidates( $state['watermark'] );
		foreach ( $ids as $id ) {
			$parent = wp_is_post_revision( $id );
			if ( ! $parent || wp_is_post_autosave( $id ) ) {
				continue;
			}
			if ( ! current_user_can( 'delete_post', $parent ) || ! wp_delete_post_revision( $id ) ) {
				return new \WP_Error( 'delete_failed', __( 'Cleanup stopped because a revision could not be removed. Preview again to see what remains.', 'site-staples' ) );
			}
			++$state['deleted'];
		}
		$state['remaining'] = $this->candidates( $state['watermark'], true );
		return $state;
	}

	public static function transient_key() {
		return 'site_staples_revisions_' . get_current_blog_id() . '_' . get_current_user_id();
	}

	public function handle_preview() {
		Guard::post( 'site_staples_revision_preview' );
		$state = $this->preview();
		$state['token'] = wp_generate_password( 32, false );
		set_transient( self::transient_key(), $state, HOUR_IN_SECONDS );
		Guard::redirect( 'revision_preview' );
	}

	public function handle_cleanup() {
		Guard::post( 'site_staples_revision_cleanup' );
		$state = get_transient( self::transient_key() );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guard::post() verifies the scoped nonce above.
		$token = isset( $_POST['preview_token'] ) && is_string( $_POST['preview_token'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_token'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guard::post() verifies the scoped nonce above.
		$confirm = isset( $_POST['confirm'] ) && is_string( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( ! $state || ! hash_equals( $state['token'], $token ) || '1' !== $confirm ) {
			Guard::result( new \WP_Error( 'confirmation', __( 'Preview cleanup and explicitly confirm before removing revisions.', 'site-staples' ) ) );
		}
		// Consume the token before mutating data to prevent replay/concurrent submissions.
		delete_transient( self::transient_key() );
		$state = Guard::result( $this->cleanup( $state ) );
		$state['token'] = wp_generate_password( 32, false );
		set_transient( self::transient_key(), $state, HOUR_IN_SECONDS );
		Guard::redirect( 'revision_batch' );
	}
}
