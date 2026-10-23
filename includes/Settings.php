<?php
/** Configuration schema shared by Settings API and imports. @package SiteStaples */

namespace SiteStaples;

final class Settings {
	const OPTION = 'site_staples_settings';

	/** Defaults intentionally disable every module. */
	public static function defaults() {
		return array(
			'comments'  => array( 'enabled' => false, 'scope' => 'all', 'post_types' => array() ),
			'svg'       => array( 'enabled' => false, 'roles' => array() ),
			'duplicate' => array( 'enabled' => false, 'post_types' => array( 'post', 'page' ) ),
			'media'     => array( 'enabled' => false ),
			'revisions' => array( 'enabled' => false, 'limit' => 10 ),
			'transfer'  => array( 'enabled' => false ),
			'image_sizes' => array( 'enabled' => false, 'disabled_sizes' => array() ),
			'redirects' => array( 'enabled' => false, 'rules' => array() ),
			'maintenance' => array( 'enabled' => false, 'roles' => array(), 'title' => '', 'message' => '' ),
			'admin_cleanup' => array( 'enabled' => false, 'roles' => array(), 'widgets' => array(), 'toolbar' => array() ),
			'columns' => array( 'enabled' => false, 'post_types' => array( 'post', 'page' ), 'show_id' => true, 'show_thumbnail' => true, 'show_modified' => false ),
			'uninstall' => false,
		);
	}

	/** The original schema remains available for strict legacy import validation. */
	public static function legacy_defaults() {
		return array_diff_key( self::defaults(), array_fill_keys( array( 'image_sizes', 'redirects', 'maintenance', 'admin_cleanup', 'columns' ), true ) );
	}

	/** Only a complete, exact v1 configuration is eligible for the defaults upgrade. */
	public static function upgrade( $input ) {
		$old = self::legacy_defaults();
		if ( is_array( $input ) && ! array_diff_key( $input, $old ) && ! array_diff_key( $old, $input ) ) {
			return array_replace( self::defaults(), $input );
		}
		return $input;
	}

	public static function widget_labels() {
		return array( 'dashboard_primary' => __( 'WordPress events and news', 'site-staples' ), 'dashboard_quick_press' => __( 'Quick draft', 'site-staples' ), 'dashboard_activity' => __( 'Activity', 'site-staples' ), 'dashboard_right_now' => __( 'At a glance', 'site-staples' ), 'dashboard_site_health' => __( 'Site health status', 'site-staples' ) );
	}

	public static function toolbar_labels() {
		return array( 'wp-logo' => __( 'WordPress menu', 'site-staples' ), 'updates' => __( 'Updates indicator', 'site-staples' ), 'comments' => __( 'Comments', 'site-staples' ), 'new-content' => __( 'New content menu', 'site-staples' ), 'customize' => __( 'Customize', 'site-staples' ), 'search' => __( 'Search', 'site-staples' ) );
	}

	/** Unfiltered registry for configuration, including sizes disabled by this plugin. */
	public static function image_sizes() {
		$sizes = wp_get_additional_image_sizes();
		foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large' ) as $name ) {
			if ( ! isset( $sizes[ $name ] ) ) {
				$sizes[ $name ] = array( 'width' => (int) get_option( $name . '_size_w' ), 'height' => (int) get_option( $name . '_size_h' ), 'crop' => get_option( $name . '_crop' ) );
			}
		}
		return array_filter( $sizes, static function ( $size ) { return ! empty( $size['width'] ) || ! empty( $size['height'] ); } );
	}

	/** Read without writing or registering any feature hooks. */
	public static function get() {
		$stored = self::upgrade( get_option( self::OPTION, self::defaults() ) );
		return is_wp_error( self::validate( $stored, false ) ) ? self::defaults() : $stored;
	}

	/** Available types exclude attachments, revisions and internal types. */
	public static function post_types() {
		return array_filter(
			get_post_types( array( 'show_ui' => true ), 'objects' ),
			static function ( $type ) {
				return ! in_array( $type->name, array( 'attachment', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ), true );
			}
		);
	}

	/** Validate exact keys and types; never coerce imported values. */
	public static function validate( $input, $check_registered = true ) {
		$defaults = self::defaults();
		if ( ! is_array( $input ) || array_diff_key( $input, $defaults ) || array_diff_key( $defaults, $input ) ) {
			return new \WP_Error( 'schema', __( 'Settings must contain exactly the documented configuration keys.', 'site-staples' ) );
		}
		foreach ( $defaults as $key => $value ) {
			if ( 'uninstall' === $key ) {
				if ( ! is_bool( $input[ $key ] ) ) {
					return self::invalid( $key );
				}
				continue;
			}
			$module = $input[ $key ];
			if ( ! is_array( $module ) || array_diff_key( $module, $value ) || array_diff_key( $value, $module ) || ! is_bool( $module['enabled'] ) ) {
				return self::invalid( $key );
			}
			foreach ( array( 'roles', 'post_types', 'disabled_sizes', 'widgets', 'toolbar' ) as $list ) {
				if ( ! array_key_exists( $list, $value ) ) {
					continue;
				}
				$items = $module[ $list ];
				if ( ! is_array( $items ) || ! array_is_list( $items ) || count( $items ) > 100 ) {
					return self::invalid( $key );
				}
				$allowed = null;
				if ( $check_registered && in_array( $list, array( 'roles', 'post_types' ), true ) ) {
					$allowed = 'roles' === $list ? array_keys( wp_roles()->roles ) : array_keys( self::post_types() );
				}
				if ( 'widgets' === $list ) {
					$allowed = array( 'dashboard_primary', 'dashboard_quick_press', 'dashboard_activity', 'dashboard_right_now', 'dashboard_site_health' );
				} elseif ( 'toolbar' === $list ) {
					$allowed = array( 'wp-logo', 'updates', 'comments', 'new-content', 'customize', 'search' );
				}
				foreach ( $items as $item ) {
					if ( ! is_string( $item ) || ! preg_match( 'disabled_sizes' === $list ? '/^[^\x00-\x1f\x7f]{1,100}$/uD' : '/^[a-z0-9_-]{1,64}$/D', $item ) || ( null !== $allowed && ! in_array( $item, $allowed, true ) ) ) {
						return self::invalid( $key );
					}
				}
				if ( count( array_unique( $items ) ) !== count( $items ) ) {
					return self::invalid( $key );
				}
			}
		}
		if ( ! in_array( $input['comments']['scope'], array( 'all', 'selected' ), true ) || ! is_int( $input['revisions']['limit'] ) || $input['revisions']['limit'] < 0 || $input['revisions']['limit'] > 1000 ) {
			return self::invalid( 'comments / revisions' );
		}
		foreach ( array( 'show_id', 'show_thumbnail', 'show_modified' ) as $flag ) {
			if ( ! is_bool( $input['columns'][ $flag ] ) ) {
				return self::invalid( 'columns' );
			}
		}
		foreach ( array( 'title' => 120, 'message' => 2000 ) as $field => $max ) {
			$text = $input['maintenance'][ $field ];
			if ( ! is_string( $text ) || strlen( $text ) > $max * 4 || false === preg_match_all( '/./us', $text ) || preg_match_all( '/./us', $text ) > $max || wp_strip_all_tags( $text ) !== $text || preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text ) ) {
				return self::invalid( 'maintenance' );
			}
		}
		$rules = $input['redirects']['rules'];
		if ( array() !== $rules && is_wp_error( RedirectRules::validate_rules( $rules, $check_registered ) ) ) {
			return RedirectRules::error();
		}
		return $input;
	}

	/** Friendly schema error. */
	private static function invalid( $key ) {
		/* translators: %s: configuration section. */
		return new \WP_Error( 'schema', sprintf( __( 'Invalid values in %s. Check field types, selected roles and post types, allowed items, text lengths, and the revision limit (0–1000).', 'site-staples' ), $key ) );
	}

	/** Convert browser form values, then use the same strict schema. */
	public static function sanitize( $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! is_array( $input ) ) {
			return self::get();
		}
		$typed = self::validate( $input );
		if ( ! is_wp_error( $typed ) ) {
			return $typed;
		}
		$result = self::defaults();
		foreach ( $result as $key => &$module ) {
			if ( 'uninstall' === $key ) {
				$module = isset( $input[ $key ] ) && '1' === $input[ $key ];
				continue;
			}
			$source = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
			$module['enabled'] = isset( $source['enabled'] ) && '1' === $source['enabled'];
			foreach ( array( 'roles', 'post_types', 'disabled_sizes', 'widgets', 'toolbar' ) as $list ) {
				if ( isset( $module[ $list ] ) ) {
					$module[ $list ] = isset( $source[ $list ] ) && is_array( $source[ $list ] ) ? array_values( $source[ $list ] ) : array();
				}
			}
			if ( 'columns' === $key ) {
				foreach ( array( 'show_id', 'show_thumbnail', 'show_modified' ) as $flag ) {
					$module[ $flag ] = isset( $source[ $flag ] ) && '1' === $source[ $flag ];
				}
			}
			if ( 'maintenance' === $key ) {
				foreach ( array( 'title', 'message' ) as $field ) {
					$module[ $field ] = isset( $source[ $field ] ) && is_string( $source[ $field ] ) ? sanitize_textarea_field( $source[ $field ] ) : '';
				}
			}
			if ( 'redirects' === $key ) {
				$module['rules'] = isset( $source['rows_present'] ) ? self::parse_rules_rows( $source['rules_rows'] ?? array() ) : self::parse_rules_form( $source['rules_text'] ?? '' );
			}
			if ( 'comments' === $key ) {
				$module['scope'] = $source['scope'] ?? 'all';
			}
			if ( 'revisions' === $key ) {
				$raw = $source['limit'] ?? '';
				$module['limit'] = is_string( $raw ) && preg_match( '/^\d{1,4}$/D', $raw ) ? (int) $raw : -1;
			}
		}
		unset( $module );
		$valid = self::validate( $result );
		if ( is_wp_error( $valid ) ) {
			add_settings_error( self::OPTION, 'invalid', $valid->get_error_message() );
			return self::get();
		}
		return $result;
	}

	/** Convert row controls; the existing shared validator checks paths and cycles. */
	private static function parse_rules_rows( $rows ) {
		if ( ! is_array( $rows ) || count( $rows ) > RedirectRules::MAX_RULES ) {
			return false;
		}
		$rules = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || count( $row ) !== 3 || array_diff( array_keys( $row ), array( 'from', 'to', 'status' ) ) || ! isset( $row['from'], $row['to'], $row['status'] ) || ! is_string( $row['from'] ) || ! is_string( $row['to'] ) || ! in_array( $row['status'], array( '301', '302' ), true ) ) {
				return false;
			}
			$from = trim( $row['from'] );
			$to = trim( $row['to'] );
			if ( '' === $from && '' === $to ) {
				continue;
			}
			$rules[] = array( 'from' => $from, 'to' => $to, 'status' => (int) $row['status'] );
		}
		return $rules;
	}

	/** Native textarea input has one status/source/destination rule per line. */
	private static function parse_rules_form( $text ) {
		if ( ! is_string( $text ) || strlen( $text ) > 60000 ) {
			return false;
		}
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', trim( $text ) ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			if ( ! preg_match( '/^(301|302)\s+(\S+)\s+(\S+)$/D', trim( $line ), $parts ) ) {
				return false;
			}
			$rules[] = array( 'status' => (int) $parts[1], 'from' => $parts[2], 'to' => $parts[3] );
		}
		return $rules;
	}

}
