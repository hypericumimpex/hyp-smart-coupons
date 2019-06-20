<?php
/**
 * Plugin Name: HYP Smart Coupons
 * Plugin URI: https://github.com/hypericumimpex/hyp-smart-coupons/
 * Description: <strong>Smart Coupons</strong> permite clienților să cumpere cu ușurință vouchere cadou, credite sau cupoane.
 * Version: 4.0.7
 * Author: Romeo C.
 * Author URI: https://github.com/hypericumimpex/
 * Developer: StoreApps
 * Developer URI: https://github.com/hypericumimpex/
 * Requires at least: 4.4
 * Tested up to: 5.2.1
 * WC requires at least: 2.5.0
 * WC tested up to: 3.6.4
 * Text Domain: woocommerce-smart-coupons
 * Domain Path: /languages
 * Woo: 18729:05c45f2aa466106a466de4402fff9dde
 * Copyright (c) 2014-2018 WooCommerce, StoreApps All rights reserved.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package woocommerce-smart-coupons
 */

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once 'woo-includes/woo-functions.php';
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '05c45f2aa466106a466de4402fff9dde', '18729' );

/**
 * Include class having function to execute during activation & deactivation of plugin
 */
require_once 'includes/class-wc-sc-act-deact.php';

/**
 * On activation
 */
register_activation_hook( __FILE__, array( 'WC_SC_Act_Deact', 'smart_coupon_activate' ) );

/**
 * On deactivation
 */
register_deactivation_hook( __FILE__, array( 'WC_SC_Act_Deact', 'smart_coupon_deactivate' ) );

if ( is_woocommerce_active() ) {

	if ( ! defined( 'WC_SC_PLUGIN_FILE' ) ) {
		define( 'WC_SC_PLUGIN_FILE', __FILE__ );
	}
	if ( ! defined( 'WC_SC_PLUGIN_DIRNAME' ) ) {
		define( 'WC_SC_PLUGIN_DIRNAME', dirname( plugin_basename( __FILE__ ) ) );
	}

	include_once 'includes/sc-functions.php';

	include_once 'includes/class-wc-smart-coupons.php';

	$GLOBALS['woocommerce_smart_coupon'] = WC_Smart_Coupons::get_instance();

} // End woocommerce active check