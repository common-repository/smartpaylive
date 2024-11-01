<?php
/**
 * Plugin Name: SmartPayLive
 * Plugin URI: https://smartpaylive.com/woocommerce_extension
 * Description: A World-Class, Innovative, Direct Merchanting Bank to Bank Instant EFT Payment Solution in South Africa.
 * Author: Overflow Business Holdings
 * Author URI: https://overflow.co.za
 * Version: 1.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initialize the gateway.
 * 
 * @since 1.0.0
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    function init_smartpaylive() {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        require_once(plugin_basename('includes/class-smartpaylive.php'));
        require_once(plugin_basename('includes/class-wc-gateway-smartpaylive.php'));
        add_filter('woocommerce_payment_gateways', 'add_smartpaylive_gateway');
    }

    function add_smartpaylive_gateway($methods) {
        $methods[] = 'WC_Gateway_SmartPayLive'; 
        return $methods;
    }

    add_action('plugins_loaded', 'init_smartpaylive');
}