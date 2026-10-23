<?php
/** Plugin lifecycle and opt-in loading. @package SiteStaples */

namespace SiteStaples;

final class Plugin {
	/** Register modules for this site. */
	public static function boot() {
		add_action( 'init', array( self::class, 'translations' ) );
		$settings = Settings::get();
		if ( is_admin() ) {
			( new Admin() )->register();
		}
		$modules = array(
			'comments'  => Modules\Comments::class,
			'svg'       => Modules\Svg::class,
			'duplicate' => Modules\Duplicate::class,
			'media'     => Modules\Media::class,
			'revisions' => Modules\Revisions::class,
			'transfer'  => Modules\Transfer::class,
			'image_sizes' => Modules\ImageSizes::class,
			'redirects' => Modules\Redirects::class,
			'maintenance' => Modules\Maintenance::class,
			'admin_cleanup' => Modules\AdminCleanup::class,
			'columns' => Modules\Columns::class,
		);
		foreach ( $modules as $key => $class ) {
			if ( $settings[ $key ]['enabled'] && ( is_admin() || ! in_array( $key, array( 'duplicate', 'media', 'transfer', 'columns' ), true ) ) ) {
				( new $class( $settings[ $key ] ) )->register();
			}
		}
	}

	/** Register bundled translations after WordPress initializes. */
	public static function translations() {
		load_plugin_textdomain( 'site-staples', false, dirname( plugin_basename( SITE_STAPLES_FILE ) ) . '/languages' );
	}

	/** Activation never changes site content or network options. */
	public static function activate() {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( get_bloginfo( 'version' ), '6.6', '<' ) ) {
			wp_die( esc_html__( 'Site Staples requires WordPress 6.6 and PHP 8.1 or newer.', 'site-staples' ) );
		}
		add_option( Settings::OPTION, Settings::defaults(), '', false );
	}

	/** Deactivation preserves settings and content; no scheduled jobs exist. */
	public static function deactivate() {}
}
