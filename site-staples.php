<?php
/**
 * Plugin Name: Site Staples
 * Description: Practical, opt-in utilities for content, media, redirects, maintenance, and WordPress administration.
 * Version: 1.2.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: Marko Moguljak
 * License: GPL-2.0-or-later
 * Text Domain: site-staples
 * Domain Path: /languages
 *
 * @package SiteStaples
 */

namespace SiteStaples;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SITE_STAPLES_FILE', __FILE__ );
define( 'SITE_STAPLES_VERSION', '1.2.0' );
require_once __DIR__ . '/includes/autoload.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
