<?php
/** Render the production settings screen with native WordPress submit controls. */
require __DIR__ . '/bootstrap.php';
load_core_functions( ABSPATH . WPINC . '/functions.php', array( 'wp_unique_id' ) );
load_core_functions( ABSPATH . 'wp-admin/includes/template.php', array( 'add_settings_section', 'add_settings_field', 'do_settings_sections', 'do_settings_fields', 'get_submit_button', 'submit_button' ) );
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function register_setting( $group, $name, $args ) { add_filter( 'sanitize_option_' . $name, $args['sanitize_callback'] ); }
function add_options_page( ...$args ) {}
function plugins_url( $path = '', $plugin = '' ) { return 'file://' . dirname( $plugin ) . '/' . ltrim( $path, '/' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = '' ) { return esc_attr( $s ); }
function esc_url( $s ) { return esc_attr( $s ); }
function checked( $a, $b = true, $echo = true ) { $out = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $out; } return $out; }
function translate_user_role( $name ) { return $name; }
function settings_errors() {}
function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">'; wp_nonce_field( $group ); }
function wp_nonce_field( $action, $name = '_wpnonce' ) { echo '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="fixture-nonce">'; }
function __return_false() { return false; }
function _e( $s ) { echo esc_html( $s ); }

$settings = SiteStaples\Settings::defaults();
if ( in_array( '--enabled', $argv, true ) ) {
	foreach ( $settings as $key => &$value ) { if ( is_array( $value ) ) { $value['enabled'] = true; } } unset( $value );
	$state = $settings; $state['comments']['scope'] = 'selected'; $state['comments']['post_types'] = array( 'post' ); $state['uninstall'] = true;
	set_transient( SiteStaples\Modules\Transfer::transient_key(), array( 'settings' => $state, 'before' => $settings, 'token' => 'preview-token' ), 100 );
	set_transient( SiteStaples\Modules\Revisions::transient_key(), array( 'count' => 205, 'deleted' => 100, 'remaining' => 105, 'limit' => 10, 'watermark' => 500, 'token' => 'revision-token' ), 100 );
}
update_option( SiteStaples\Settings::OPTION, $settings );
$admin = new SiteStaples\Admin(); $admin->settings();
$css = 'file://' . ABSPATH . 'wp-admin/css/';
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Site Staples — isolated admin renderer</title>';
foreach ( array( 'common', 'forms', 'buttons', 'list-tables' ) as $sheet ) { echo '<link rel="stylesheet" href="' . esc_attr( $css . $sheet . '.css' ) . '">'; }
echo '<link rel="stylesheet" href="file://' . dirname( __DIR__ ) . '/assets/admin.css"><script defer src="file://' . dirname( __DIR__ ) . '/assets/redirects.js"></script><script defer src="file://' . dirname( __DIR__ ) . '/assets/admin.js"></script>';
echo '</head><body class="wp-admin wp-core-ui"><main id="wpbody-content">';
$admin->render();
echo '</main></body></html>';
rmdir( $GLOBALS['test_uploads'] );
