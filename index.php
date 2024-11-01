<?php
/**
 * Plugin Name: Soukapay for WooCommerce
 * Plugin URI: https://soukapay.com
 * Description: Enable online payments using online banking for e-Commerce business.
 * Version: 1.0.0
 * Author: Soukapay Sdn Bhd
 * WC requires at least: 2.6.0
 * WC tested up to: 6.8.2
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

# Include Soukapay Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'soukapay_init', 0 );

function soukapay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/soukapay.php' );

	add_filter( 'woocommerce_payment_gateways', 'add_soukapay_to_woocommerce' );
	function add_soukapay_to_woocommerce( $methods ) {
		$methods[] = 'Soukapay';

		return $methods;
	}
}

# Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'soukapay_links' );

function soukapay_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=soukapay' ) . '">' . __( 'Settings', 'soukapay' ) . '</a>',
	);

	# Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}

add_action( 'init', 'soukapay_check_response', 15 );

function soukapay_check_response() {
	# If the parent WC_Payment_Gateway class doesn't exist it means WooCommerce is not installed on the site, so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/soukapay.php' );

	$soukapay = new soukapay();
	$soukapay->check_soukapay_response();
}

add_action( 'woocommerce_api_callback', 'callback_soukapay' );

function callback_soukapay() {    
	include_once( 'src/soukapay.php' );
	$soukapayCall = new soukapay();
	$soukapayCall->callback_from_soukapay();
}

# checkout logo
add_filter('woocommerce_gateway_icon', function ($icon, $id) {
  if($id === 'Soukapay') {
	$icon = '<img style="max-height: 240px;max-width: 600px;float: none;" src="https://fitwebx.com/img/cout_logo_soukapay.png" alt="bpwplogo" />';
	return $icon;
  } else {
    return $icon;
  }
}, 10, 2);
# end checkout logo

function soukapay_hash_error_msg( $content ) {
	return '<div class="woocommerce-error">The data that we received is invalid. Thank you.</div>' . $content;
}

function soukapay_payment_declined_msg( $content ) {
	return '<div class="woocommerce-error">The payment was declined. Please check with your bank. Thank you.</div>' . $content;
}

function soukapay_success_msg( $content ) {
	return '<div class="woocommerce-info">The payment was successful. Thank you.</div>' . $content;
}

function soukapay_key_error_msg( $content ) {
	return '<div class="woocommerce-error">Unable to get key.</div>' . $content;
}

function soukapay_payment_error_msg( $content ) {
	return '<div class="woocommerce-error">Unable to request payment.</div>' . $content;
}