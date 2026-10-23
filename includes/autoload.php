<?php
/**
 * Small autoloader; disabled modules do not load their PHP files.
 *
 * @package SiteStaples
 */

namespace SiteStaples;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		if ( str_starts_with( $relative, 'Vendor\\SvgSanitize\\' ) ) {
			$file = dirname( __DIR__ ) . '/lib/svg-sanitize/src/' . str_replace( '\\', '/', substr( $relative, 19 ) ) . '.php';
		} else {
			$file = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		}
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);
