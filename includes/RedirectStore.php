<?php
/** Immutable redirect snapshots backed by WordPress's indexed, non-autoloaded options. @package SiteStaples */

namespace SiteStaples;

final class RedirectStore {
	const PREFIX = 'site_staples_redirect_';
	private static $locked = false;
	private static $lease = '';

	/** Serialize writers; a crashed request's short lease can be reclaimed. */
	public static function lock() {
		if ( self::$locked ) {
			return true;
		}
		$key = self::PREFIX . 'lock';
		$lease = time() . ':' . bin2hex( random_bytes( 8 ) );
		if ( ! add_option( $key, $lease, '', false ) ) {
			$old = get_option( $key );
			if ( ! is_string( $old ) || (int) $old >= time() - 120 ) {
				return self::busy_error();
			}
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-delete only the expired plugin-owned lease, never a renewed lock.
			$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $old ) );
			wp_cache_delete( $key, 'options' );
			if ( ! $removed || ! add_option( $key, $lease, '', false ) ) {
				return self::busy_error();
			}
		}
		self::$lease = $lease;
		// Another writer may have completed while this request was waiting for the lease.
		wp_cache_delete( Settings::OPTION, 'options' );
		self::$locked = true;
		register_shutdown_function( array( self::class, 'unlock' ) );
		return true;
	}

	public static function unlock() {
		if ( self::$locked ) {
			global $wpdb;
			$key = self::PREFIX . 'lock';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release only this request's lease; an expired writer cannot remove its successor's lock.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, self::$lease ) );
			wp_cache_delete( $key, 'options' );
			self::$locked = false;
		}
	}

	private static function busy_error() {
		return new \WP_Error( 'busy', __( 'Another settings update is running. Wait a moment and try again.', 'site-staples' ) );
	}

	public static function owns_lock() {
		wp_cache_delete( self::PREFIX . 'lock', 'options' );
		return self::$locked && get_option( self::PREFIX . 'lock' ) === self::$lease;
	}

	public static function valid_token( $token ) {
		return is_string( $token ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $token );
	}

	/** Origin and site path matter; an admin-only HTTPS scheme must not disable HTTP routing. */
	public static function site_key() {
		$home = wp_parse_url( home_url( '/' ) );
		return strtolower( $home['host'] ?? '' ) . ':' . ( $home['port'] ?? '' ) . ( $home['path'] ?? '/' );
	}

	public static function current_token() {
		$settings = get_option( Settings::OPTION, array() );
		$token = $settings['redirects']['rules'] ?? null;
		return self::valid_token( $token ) ? $token : '';
	}

	public static function document( $token ) {
		return self::valid_token( $token ) ? get_option( self::PREFIX . $token, false ) : false;
	}

	/** Build and verify everything before publishing its token in the settings option. */
	public static function stage( $rules ) {
		$compiled = RedirectRules::compile( $rules );
		if ( is_wp_error( $compiled ) ) {
			return $compiled;
		}
		$current = self::current_token();
		$old = self::document( $current );
		if ( $old && $old['rules'] === $rules && $old['home'] === self::site_key() ) {
			return $current;
		}
		$token = bin2hex( random_bytes( 16 ) );
		$buckets = array();
		foreach ( $compiled as $path => $rule ) {
			$buckets[ substr( hash( 'sha256', $path ), 0, 2 ) ][ $path ] = $rule;
		}
		$document = array( 'rules' => $rules, 'home' => self::site_key(), 'created' => time(), 'buckets' => array_keys( $buckets ) );
		$key = self::PREFIX . $token;
		// Record the staging manifest first so an interrupted write can be collected later.
		if ( ! add_option( $key, $document, '', false ) || get_option( $key ) !== $document ) {
			delete_option( $key );
			return self::save_error();
		}
		foreach ( $buckets as $bucket => $values ) {
			$key = self::PREFIX . $token . '_' . $bucket;
			if ( ! add_option( $key, $values, '', false ) || get_option( $key ) !== $values ) {
				self::cleanup( $token );
				return self::save_error();
			}
		}
		register_shutdown_function( static function () use ( $token, $current ) {
			if ( $token === self::current_token() ) {
				self::retire( $current );
			} else {
				self::retire( $token );
			}
		} );
		return $token;
	}

	/** Visitor requests load one hash bucket, never the full rule document or graph. */
	public static function lookup( $token, $path ) {
		if ( ! self::valid_token( $token ) ) {
			return false;
		}
		$bucket = get_option( self::PREFIX . $token . '_' . substr( hash( 'sha256', $path ), 0, 2 ), array() );
		return $bucket[ $path ] ?? false;
	}

	public static function save_error() {
		return new \WP_Error( 'save', __( 'Redirects could not be saved. The previous rules remain active. Try again.', 'site-staples' ) );
	}

	/** Retire snapshots after a grace period so requests already in flight can finish. */
	public static function retire( $token ) {
		if ( self::valid_token( $token ) && $token !== self::current_token() && self::document( $token ) && ! wp_next_scheduled( 'site_staples_redirect_retire', array( $token ) ) ) {
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'site_staples_redirect_retire', array( $token ) );
		}
	}

	/** Admin-only recovery for abandoned staging writes and deactivated cron jobs. */
	public static function sweep() {
		if ( get_transient( 'site_staples_redirect_sweep' ) ) {
			return;
		}
		set_transient( 'site_staples_redirect_sweep', 1, HOUR_IN_SECONDS );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded to plugin snapshot manifests; never read content or unrelated options.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND LENGTH(option_name) = %d", $wpdb->esc_like( self::PREFIX ) . '%', strlen( self::PREFIX ) + 32 ) );
		foreach ( $names as $name ) {
			$token = substr( $name, strlen( self::PREFIX ) );
			$document = self::document( $token );
			if ( $document && $document['created'] < time() - HOUR_IN_SECONDS ) {
				self::cleanup( $token );
			}
		}
	}

	public static function cleanup( $token ) {
		if ( ! self::valid_token( $token ) || $token === self::current_token() ) {
			return;
		}
		$document = self::document( $token );
		foreach ( $document['buckets'] ?? array() as $bucket ) {
			if ( preg_match( '/^[a-f0-9]{2}$/D', (string) $bucket ) ) {
				delete_option( self::PREFIX . $token . '_' . $bucket );
			}
		}
		delete_option( self::PREFIX . $token );
		wp_clear_scheduled_hook( 'site_staples_redirect_retire', array( $token ) );
	}
}
