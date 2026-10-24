<?php
/**
 * Isolated tests: real WordPress hooks, MIME validation, revision helpers and GD
 * editor; in-memory options/posts/permissions replace the database and session.
 * No existing WordPress installation data is read or changed.
 */
$root = getenv( 'WP_ROOT' ) ?: dirname( __DIR__, 4 );
if ( ! is_file( $root . '/wp-includes/plugin.php' ) ) {
	fwrite( STDERR, "Set WP_ROOT to an unpacked WordPress installation.\n" );
	exit( 1 );
}
define( 'ABSPATH', rtrim( $root, '/' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_PLUGIN_DIR', dirname( __DIR__, 2 ) );
define( 'WPMU_PLUGIN_DIR', ABSPATH . 'wp-content/mu-plugins' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MB_IN_BYTES', 1048576 );
define( 'KB_IN_BYTES', 1024 );
define( 'OBJECT', 'OBJECT' );
define( 'WP_DEBUG', true );
define( 'WP_POST_REVISIONS', true );
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/shortcodes.php';
require ABSPATH . WPINC . '/media.php';
require ABSPATH . WPINC . '/revision.php';

/** Load exact core functions without loading the DB-dependent bootstrap. */
function load_core_functions( $file, $names ) {
	$tokens = token_get_all( file_get_contents( $file ) );
	for ( $i = 0, $n = count( $tokens ); $i < $n; ++$i ) {
		if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) { continue; }
		$j = $i + 1;
		while ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) { ++$j; }
		if ( ! is_array( $tokens[ $j ] ) || ! in_array( $tokens[ $j ][1], $names, true ) ) { continue; }
		$code = ''; $depth = 0; $started = false;
		for ( ; $i < $n; ++$i ) {
			$t = $tokens[ $i ]; $code .= is_array( $t ) ? $t[1] : $t;
			if ( '{' === $t || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $started = true; }
			if ( '}' === $t ) { --$depth; }
			if ( $started && 0 === $depth ) { break; }
		}
		eval( $code );
	}
}
load_core_functions( ABSPATH . WPINC . '/functions.php', array( 'wp_check_filetype', 'wp_check_filetype_and_ext', 'wp_get_image_mime', 'wp_get_mime_types', 'wp_get_default_extension_for_mime_type', 'wp_filesize', 'wp_is_stream', 'wp_fuzzy_number_match', 'wp_unique_filename', 'absint', 'wp_convert_hr_to_bytes' ) );
load_core_functions( ABSPATH . WPINC . '/formatting.php', array( 'wp_slash', 'wp_unslash', 'trailingslashit', 'untrailingslashit', 'wp_basename', 'sanitize_key', 'stripslashes_deep', 'stripslashes_from_strings_only', 'map_deep', 'wp_strip_all_tags' ) );
$GLOBALS['wp_plugin_paths'] = array();
load_core_functions( ABSPATH . WPINC . '/load.php', array( 'absint', 'wp_convert_hr_to_bytes' ) );
require dirname( __DIR__ ) . '/site-staples.php';

$GLOBALS['test_options'] = array( 'thumbnail_size_w' => 150, 'thumbnail_size_h' => 150, 'thumbnail_crop' => true );
$GLOBALS['test_posts'] = array();
$GLOBALS['test_meta'] = array();
$GLOBALS['test_terms'] = array();
$GLOBALS['test_transients'] = array();
$GLOBALS['test_caps'] = array( 'manage_options' => true, 'upload_files' => true, 'read' => true, 'read_post' => true, 'create_posts' => true, 'delete_post' => true, 'edit_post' => true, 'assign_terms' => true );
$GLOBALS['test_roles'] = array( 'administrator' );
$GLOBALS['test_nonces'] = true;
$GLOBALS['test_uploads'] = sys_get_temp_dir() . '/site-staples-test-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) );
mkdir( $GLOBALS['test_uploads'] );
function __( $s, $domain = '' ) { return $s; }
function esc_html__( $s, $domain = '' ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $k, $default = false ) { $GLOBALS['test_option_reads'][] = $k; return $GLOBALS['test_options'][ $k ] ?? $default; }
function add_option( $k, $v, $deprecated = '', $autoload = null ) { if ( isset($GLOBALS['test_fail_option_add']) && ($GLOBALS['test_fail_option_add'])($k) ) { return false; } $GLOBALS['test_autoload'][$k]=$autoload; if ( array_key_exists( $k, $GLOBALS['test_options'] ) ) { return false; } $GLOBALS['test_options'][ $k ] = $v; return true; }
function update_option( $k, $v, $autoload = null ) { if (!empty($GLOBALS['test_fail_option_update']) && $GLOBALS['test_fail_option_update']===$k) { return false; } $GLOBALS['test_autoload'][$k]=$autoload; $v = apply_filters( 'sanitize_option_' . $k, $v ); $changed = get_option( $k ) !== $v; $GLOBALS['test_options'][ $k ] = $v; return $changed; }
function delete_option( $k ) { unset( $GLOBALS['test_options'][ $k ] ); return true; }
function get_bloginfo( $key ) { return '6.6'; }
function is_admin() { return $GLOBALS['test_is_admin'] ?? true; }
function is_multisite() { return ! empty( $GLOBALS['test_multisite'] ); }
function check_upload_mimes( $mimes ) { if ( empty( $GLOBALS['test_network_svg'] ) ) { unset( $mimes['svg'] ); } return $mimes; }
function current_user_can( $cap, ...$args ) { return $GLOBALS['test_caps'][ $cap ] ?? false; }
function wp_roles() { return (object) array( 'roles' => array( 'administrator' => array( 'name' => 'Administrator' ), 'editor' => array( 'name' => 'Editor' ), 'subscriber' => array( 'name' => 'Subscriber' ) ) ); }
function wp_get_current_user() { return (object) array( 'roles' => $GLOBALS['test_roles'] ); }
function get_current_user_id() { return 1; }
function get_current_blog_id() { return 1; }
function get_post_types( $args = array(), $output = 'names' ) { $names = array( 'post', 'page', 'book' ); return 'objects' === $output ? array_combine( $names, array_map( 'get_post_type_object', $names ) ) : $names; }
function get_post_type_object( $type ) { return (object) array( 'name' => $type, 'labels' => (object) array( 'name' => ucfirst( $type ) ), 'cap' => (object) array( 'create_posts' => 'create_posts' ) ); }
function get_post( $id = 0 ) { return is_object( $id ) ? $id : ( $GLOBALS['test_posts'][ $id ] ?? null ); }
function get_post_type( $id ) { return get_post( $id )->post_type ?? false; }
function get_post_mime_type( $id ) { return get_post( $id )->post_mime_type ?? false; }
function post_type_supports( $type, $feature ) { return in_array( $type, array( 'post', 'page', 'book' ), true ); }
function wp_insert_post( $data, $error = false ) { $id = count( $GLOBALS['test_posts'] ) + 1000; $data = wp_unslash( $data ); $data['ID'] = $id; $GLOBALS['test_posts'][ $id ] = (object) $data; return $id; }
function wp_delete_post( $id, $force = false ) { $post = get_post( $id ); unset( $GLOBALS['test_posts'][ $id ], $GLOBALS['test_meta'][ $id ] ); return $post; }
function get_post_meta( $id, $key = '', $single = false ) { $meta = $GLOBALS['test_meta'][ $id ] ?? array(); if ( '' === $key ) { return $meta; } $v = $meta[ $key ] ?? array(); return $single ? ( $v[0] ?? '' ) : $v; }
function metadata_exists( $type, $id, $key ) { return isset( $GLOBALS['test_meta'][ $id ][ $key ] ); }
function add_post_meta( $id, $key, $value ) { $GLOBALS['test_meta'][ $id ][ $key ][] = wp_unslash( $value ); return true; }
function update_post_meta( $id, $key, $value ) { if ( ! empty( $GLOBALS['test_meta_fail_once'] ) ) { $GLOBALS['test_meta_fail_once'] = false; return false; } $GLOBALS['test_meta'][ $id ][ $key ] = array( $value ); return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['test_meta'][ $id ][ $key ] ); return true; }
function is_protected_meta( $key, $type ) { return str_starts_with( $key, '_' ); }
function maybe_unserialize( $value ) { $v = @unserialize( $value ); return false === $v ? $value : $v; }
function get_object_taxonomies( $type, $output ) { return array( (object) array( 'name' => 'category', 'cap' => (object) array( 'assign_terms' => 'assign_terms' ) ) ); }
function wp_get_object_terms( $id, $taxonomy, $args ) { return $GLOBALS['test_terms'][ $id ][ $taxonomy ] ?? array(); }
function wp_set_object_terms( $id, $terms, $taxonomy ) { $GLOBALS['test_terms'][ $id ][ $taxonomy ] = $terms; return $terms; }
function wp_get_attachment_url( $id ) { return 'https://example.test/uploads/' . basename( get_attached_file( $id ) ); }
function get_attached_file( $id, $unfiltered = false ) { return get_post_meta( $id, '_wp_attached_file', true ); }
function wp_get_upload_dir() { return array( 'basedir' => $GLOBALS['test_uploads'] ); }
function _wp_relative_upload_path( $file ) { return ltrim( substr( $file, strlen( $GLOBALS['test_uploads'] ) ), '/' ); }
function get_allowed_mime_types( $user = null ) { return apply_filters( 'upload_mimes', wp_get_mime_types() ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function wp_delete_file( $path ) { return ! file_exists( $path ) || unlink( $path ); }
function wp_raise_memory_limit( $context ) { return '256M'; }
function wp_read_image_metadata( $path ) { return array(); }
function wp_update_attachment_metadata( $id, $meta ) { return update_post_meta( $id, '_wp_attachment_metadata', apply_filters( 'wp_update_attachment_metadata', $meta, $id ) ); }
function wp_generate_password( $length = 12, $special = true ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function wp_cache_get( ...$args ) { return false; }
function wp_cache_set( ...$args ) { return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function add_settings_error( ...$args ) { $GLOBALS['test_settings_errors'][] = $args; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message, $args['response'] ?? 0 ); }
function check_admin_referer( $action ) { if ( ! $GLOBALS['test_nonces'] ) { wp_die( 'Invalid nonce', '', array( 'response' => 403 ) ); } return true; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['test_transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['test_transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['test_transients'][ $key ] ); return true; }
function sanitize_text_field( $value ) { return strip_tags( $value ); }

/** Execute production SQL in SQLite via Python; no MySQL socket is available. */
class TestDatabase {
	public $posts = 'posts';
	public $options = 'options';
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { $i = 0; return preg_replace_callback( '/%[ds]/', function ( $match ) use ( $args, &$i ) { $v = $args[ $i++ ]; return '%d' === $match[0] ? (string) (int) $v : "'" . str_replace( "'", "''", $v ) . "'"; }, $sql ); }
	public function get_col( $sql ) {
		$payload = array( 'sql' => $sql, 'posts' => array_values( $GLOBALS['test_posts'] ), 'options' => array_keys( $GLOBALS['test_options'] ) );
		$p = proc_open( array( 'python3', __DIR__ . '/sql.py' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		fwrite( $pipes[0], json_encode( $payload ) ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[2] ); if ( proc_close( $p ) ) { throw new RuntimeException( $err ); } return json_decode( $out, true );
	}
	public function query( $sql ) {
		if ( preg_match( "/^DELETE FROM options WHERE option_name = '([^']+)' AND option_value = '([^']+)'$/", $sql, $matches ) ) {
			if ( ($GLOBALS['test_options'][$matches[1]] ?? null) !== $matches[2] ) { return 0; }
			unset($GLOBALS['test_options'][$matches[1]]); return 1;
		}
		throw new RuntimeException('Unexpected test query: ' . $sql);
	}
	public function get_var( $sql ) { return $this->get_col( $sql )[0] ?? null; }
}
$GLOBALS['wpdb'] = new TestDatabase();

function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return ( $GLOBALS['test_home_url'] ?? 'https://example.test' ) . $path; }
function sanitize_textarea_field( $value ) { return trim( wp_strip_all_tags( $value ) ); }
function is_user_logged_in() { return ! empty( $GLOBALS['test_logged_in'] ); }
function nocache_headers() { $GLOBALS['test_nocache'] = true; }
function post_type_exists( $type ) { return in_array( $type, array( 'post', 'page', 'book' ), true ); }
function get_the_post_thumbnail( $id, $size, $args ) { return ! empty( $GLOBALS['test_thumbnail'] ) ? '<img src="thumbnail.png" alt="" width="50" height="50">' : ''; }
function get_post_modified_time( ...$args ) { return '2026-10-22 12:00'; }
function remove_meta_box( $id, $screen, $context ) { $GLOBALS['test_removed_boxes'][] = array( $id, $screen, $context ); }
function is_preview() { return false; }
function is_feed() { return false; }
function is_trackback() { return false; }
function wp_safe_redirect( $url, $status = 302, $by = '' ) { $GLOBALS['test_redirect'] = array( $url, $status, $by ); return false; }

function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }

function wp_cache_delete( ...$args ) { return true; }
function wp_create_nonce( $action ) { return 'fixture-nonce'; }
function wp_schedule_single_event( $time, $hook, $args = array() ) { $GLOBALS['test_events'][$hook][serialize($args)] = array($time,$args); return true; }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['test_events'][$hook][serialize($args)][0] ?? false; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { unset($GLOBALS['test_events'][$hook][serialize($args)]); return true; }
function wp_unschedule_hook( $hook ) { unset($GLOBALS['test_events'][$hook]); return true; }

function get_posts( $args ) {
	$posts = array_values(array_filter($GLOBALS['test_posts'], static function($post) use($args) {
		return $post->post_type===($args['post_type']??'post') && $post->post_status===($args['post_status']??'publish') && (!isset($args['has_password']) || (bool)$args['has_password']===('' !== (string)($post->post_password ?? '')));
	}));
	usort($posts,static fn($a,$b)=>strcmp($a->post_title,$b->post_title));
	return $posts;
}
function status_header( $code ) { $GLOBALS['test_status']=$code; }

function show_admin_bar( $show ) { $GLOBALS['test_show_admin_bar']=$show; }
