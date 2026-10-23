<?php
/** Shared local redirect schema validation; no feature hooks. @package SiteStaples */

namespace SiteStaples;

final class RedirectRules {
	const MAX_RULES = 100;
	/** A deliberately small path grammar avoids ambiguous browser URL parsing. */
	public static function path( $path ) {
		if ( ! is_string( $path ) || strlen( $path ) > 1024 || ! preg_match( '#^/[a-zA-Z0-9._~!$&\'()+,;=:@/%-]*$#D', $path ) ) {
			return false;
		}
		if ( str_contains( $path, '//' ) || preg_match( '/%(?![a-fA-F0-9]{2})|%(?:2f|3f|23|5c|25)/i', $path ) ) {
			return false;
		}
		$decoded = rawurldecode( $path );
		if ( ! preg_match( '//u', $decoded ) ) {
			return false;
		}
		// Reject double encoding, delimiters, backslashes and dot path segments.
		if ( preg_match( '~[\x00-\x20\x7f\\\\?#%]|(?:^|/)\.{1,2}(?:/|$)~', $decoded ) || str_starts_with( $decoded, '//' ) ) {
			return false;
		}
		$path = preg_replace_callback( '/%[a-fA-F0-9]{2}/', static function ( $match ) {
			$char = rawurldecode( $match[0] );
			return preg_match( '/[a-zA-Z0-9._~-]/', $char ) ? $char : strtoupper( $match[0] );
		}, $path );
		return '/' === $path ? '/' : rtrim( $path, '/' );
	}

	/** WordPress canonicalizes case and trailing punctuation for many permalinks. */
	private static function identity( $path ) {
		$decoded = rtrim( rawurldecode( $path ), '/' );
		return strtolower( preg_replace( '/[!\"\'(),.;{}“”]+$/u', '', $decoded ) ) ?: '/';
	}

	/** Protect login, administration, APIs and filesystem endpoints from rules. */
	public static function protected_path( $path ) {
		$path = self::identity( $path );
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$relative = $base && ( $path === $base || str_starts_with( $path, $base . '/' ) ) ? substr( $path, strlen( $base ) ) : $path;
		return (bool) preg_match( '~(?:^|/)(?:wp-admin|wp-json|wp-content|wp-includes)(?:/|$)|(?:^|/)(?:wp-login|wp-cron|xmlrpc|wp-signup|wp-activate|wp-comments-post)\.php(?:/|$)~i', $relative ) || (bool) preg_match( '~\.php(?:/|$)~i', $relative );
	}

	public static function validate_rules( $rules, $check_site = true ) {
		if ( ! is_array( $rules ) || ! array_is_list( $rules ) || count( $rules ) > self::MAX_RULES ) {
			return self::error();
		}
		$graph = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || count( $rule ) !== 3 || array_diff( array_keys( $rule ), array( 'from', 'to', 'status' ) ) || ! isset( $rule['from'], $rule['to'], $rule['status'] ) || ! in_array( $rule['status'], array( 301, 302 ), true ) ) {
				return self::error();
			}
			$from = self::path( $rule['from'] );
			$to = self::path( $rule['to'] );
			$base = $check_site ? rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' ) : '';
			if ( false === $from || false === $to || self::identity( $from ) === self::identity( $to ) || isset( $graph[ self::identity( $from ) ] ) || self::protected_path( $from ) || self::protected_path( $to ) || ( $base && ( ! str_starts_with( $from . '/', $base . '/' ) || ! str_starts_with( $to . '/', $base . '/' ) ) ) ) {
				return self::error();
			}
			$graph[ self::identity( $from ) ] = self::identity( $to );
		}
		foreach ( array_keys( $graph ) as $from ) {
			$seen = array();
			while ( isset( $graph[ $from ] ) ) {
				if ( isset( $seen[ $from ] ) ) {
					return self::error();
				}
				$seen[ $from ] = true;
				$from = $graph[ $from ];
			}
		}
		return $rules;
	}

	public static function error() {
		return new \WP_Error( 'redirects', __( 'Redirects need unique local source paths, local destinations, and status 301 or 302 (maximum 100). Remove loops, self-redirects, query strings, dot segments, encoded delimiters, and protected WordPress paths. On subdirectory sites, include the site path.', 'site-staples' ) );
	}

}
