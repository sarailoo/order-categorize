<?php
/**
 * Plugin Name: Order Categorize
 * Plugin URI: https://github.com/sarailoo/order-categorize
 * Description: 
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Version: 1.0.0
 * Author: Reza Sarailoo
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: order-categorize
 *
 * @package OrderCategorize
 */

declare(strict_types=1);

/**
 * Bootstrap for the Order Categorize plugin.
 */

namespace OrderCategorize;

if ( ! class_exists( Plugin::class ) ) {
	$orcz_autoloader = __DIR__ . '/vendor/autoload.php';

	if ( is_readable( $orcz_autoloader ) ) {
		require_once $orcz_autoloader;
	}
}

// Plugin version.
if ( ! defined( 'ORCZ_VERSION' ) ) {
	define( 'ORCZ_VERSION', '1.0.0' );
}

// Plugin directory.
if ( ! defined( 'ORCZ_DIR' ) ) {
	define( 'ORCZ_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin url.
if ( ! defined( 'ORCZ_URL' ) ) {
	define( 'ORCZ_URL', plugin_dir_url( __FILE__ ) );
}

// Build directory.
if ( ! defined( 'ORCZ_BUILD_DIR' ) ) {
	define( 'ORCZ_BUILD_DIR', ORCZ_DIR . 'build/' );
}

// Build url.
if ( ! defined( 'ORCZ_BUILD_URL' ) ) {
	define( 'ORCZ_BUILD_URL', ORCZ_URL . 'build/' );
}

class_exists( Plugin::class ) && Plugin::instance();
