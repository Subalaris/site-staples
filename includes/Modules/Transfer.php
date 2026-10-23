<?php
/** Versioned configuration-only transfer with preview and confirmation. @package SiteStaples */

namespace SiteStaples\Modules;

use SiteStaples\Guard;
use SiteStaples\Settings;

final class Transfer {
	const SCHEMA_VERSION = 2;
	const MAX_BYTES = 262144;

	public function __construct( $settings ) {}

	public function register() {
		add_action( 'admin_post_site_staples_export', array( $this, 'export' ) );
		add_action( 'admin_post_site_staples_import_preview', array( $this, 'preview' ) );
		add_action( 'admin_post_site_staples_import_apply', array( $this, 'apply' ) );
	}

	public static function document() {
		return array( 'plugin' => 'site-staples', 'schema_version' => self::SCHEMA_VERSION, 'settings' => Settings::get() );
	}

	public static function parse( $json ) {
		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_BYTES ) {
			return new \WP_Error( 'size', __( 'Import a JSON settings file no larger than 256 KB.', 'site-staples' ) );
		}
		try {
			$decoded = json_decode( $json, false, 16, JSON_THROW_ON_ERROR );
			$doc = $decoded instanceof \stdClass ? get_object_vars( $decoded ) : null;
		} catch ( \JsonException $exception ) {
			return new \WP_Error( 'json', __( 'The file is not valid JSON or is nested too deeply.', 'site-staples' ) );
		}
		$keys = array( 'plugin', 'schema_version', 'settings' );
		if ( ! is_array( $doc ) || count( $doc ) !== 3 || array_diff( array_keys( $doc ), $keys ) || 'site-staples' !== ( $doc['plugin'] ?? null ) || ! in_array( $doc['schema_version'] ?? null, array( 1, self::SCHEMA_VERSION ), true ) ) {
			return new \WP_Error( 'version', __( 'This is not a supported Site Staples settings export (schema version 1 or 2).', 'site-staples' ) );
		}
		// Preserve JSON object/array distinctions before converting to PHP arrays.
		if ( ! $doc['settings'] instanceof \stdClass ) {
			return new \WP_Error( 'shape', __( 'Settings must be a JSON object; role and post-type lists must be JSON arrays.', 'site-staples' ) );
		}
		$defaults = 1 === $doc['schema_version'] ? Settings::legacy_defaults() : Settings::defaults();
		if ( array_diff_key( get_object_vars( $doc['settings'] ), $defaults ) || array_diff_key( $defaults, get_object_vars( $doc['settings'] ) ) ) {
			return new \WP_Error( 'shape', __( 'Configuration keys do not match the declared schema version.', 'site-staples' ) );
		}
		foreach ( $defaults as $key => $default ) {
			if ( ! is_array( $default ) ) {
				continue;
			}
			$module = $doc['settings']->$key ?? null;
			if ( ! $module instanceof \stdClass ) {
				return new \WP_Error( 'shape', __( 'Each feature configuration must be a JSON object.', 'site-staples' ) );
			}
			foreach ( array( 'roles', 'post_types', 'disabled_sizes', 'widgets', 'toolbar', 'rules' ) as $list ) {
				if ( array_key_exists( $list, $default ) && ! is_array( $module->$list ?? null ) ) {
					return new \WP_Error( 'shape', __( 'Role and post-type lists must be JSON arrays, including empty lists.', 'site-staples' ) );
				}
			}
		}
		$configuration = json_decode( $json, true, 16, JSON_THROW_ON_ERROR );
		return Settings::validate( 1 === $doc['schema_version'] ? Settings::upgrade( $configuration['settings'] ) : $configuration['settings'] );
	}

	public static function transient_key() {
		return 'site_staples_import_' . get_current_blog_id() . '_' . get_current_user_id();
	}

	public function export() {
		Guard::post( 'site_staples_export' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="site-staples-settings-v2.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		echo wp_json_encode( self::document(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public function preview() {
		Guard::post( 'site_staples_import_preview' );
		$file = $_FILES['settings_file'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified upload and strict JSON parser below.
		if ( ! isset( $file['name'], $file['error'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) || 'json' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || ! is_uploaded_file( $file['tmp_name'] ) || filesize( $file['tmp_name'] ) > self::MAX_BYTES ) {
			Guard::result( new \WP_Error( 'upload', __( 'Choose a valid .json upload no larger than 256 KB.', 'site-staples' ) ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local verified upload only.
		$settings = Guard::result( self::parse( file_get_contents( $file['tmp_name'] ) ) );
		set_transient( self::transient_key(), array( 'settings' => $settings, 'before' => Settings::get(), 'token' => wp_generate_password( 32, false ) ), 15 * MINUTE_IN_SECONDS );
		Guard::redirect( 'import_preview' );
	}

	/** No feature actions are invoked here: importing only changes the option. */
	public function apply_preview( $state, $token ) {
		if ( ! current_user_can( 'manage_options' ) || ! is_array( $state ) || ! isset( $state['token'], $state['settings'], $state['before'] ) || ! is_string( $token ) || ! hash_equals( $state['token'], $token ) || $state['before'] !== Settings::get() ) {
			return new \WP_Error( 'stale', __( 'The preview expired or settings changed. Preview the import again.', 'site-staples' ) );
		}
		$settings = Settings::validate( $state['settings'] );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		update_option( Settings::OPTION, $settings, false );
		if ( Settings::get() !== $settings ) {
			return new \WP_Error( 'save', __( 'Settings could not be saved. Preview and try again.', 'site-staples' ) );
		}
		return true;
	}

	public function apply() {
		Guard::post( 'site_staples_import_apply' );
		$state = get_transient( self::transient_key() );
		$token = isset( $_POST['preview_token'] ) && is_string( $_POST['preview_token'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_token'] ) ) : '';
		if ( '1' !== ( $_POST['confirm'] ?? '' ) ) {
			Guard::result( new \WP_Error( 'confirm', __( 'Confirm the preview before importing settings.', 'site-staples' ) ) );
		}
		delete_transient( self::transient_key() );
		Guard::result( $this->apply_preview( $state, $token ) );
		Guard::redirect( 'imported' );
	}
}
