<?php
/**
 * Plugin Name: Product Data Generator
 * Plugin URI: https://github.com/Nicscott01/product-data-generator
 * Description: A WooCommerce product data generator that uses AI to automatically generate product descriptions, short descriptions, and other product data. Includes developer hooks for customizing AI context.
 * Version: 0.0.1
 * Author: Nic Scott
 * Author URI: https://crearewebsolutions.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: product-data-generator
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'PRODUCT_DATA_GENERATOR_VERSION', '1.0.0' );
define( 'PRODUCT_DATA_GENERATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRODUCT_DATA_GENERATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>' . __( 'Product Data Generator requires WooCommerce to be installed and active.', 'product-data-generator' ) . '</p></div>';
    });
    return;
}

// Your plugin code starts here

require_once( __DIR__ . '/vendor/autoload.php' );
require_once( __DIR__ . '/init.php' );