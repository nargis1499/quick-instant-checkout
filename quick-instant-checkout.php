<?php
/**
 * Plugin Name:       Quick Instant Checkout
 * Plugin URI:        https://example.com/quick-instant-checkout
 * Description:       Tiered "Quick Instant Checkout" package selector with one-click jump to payment for WooCommerce. Blueprint clone of a package-quantity instant checkout widget.
 * Version:           0.6.0
 * Author:            You
 * License:           GPL-2.0-or-later
 * Text Domain:       quick-instant-checkout
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.5
 */

defined( 'ABSPATH' ) || exit;

define( 'QIC_VERSION', '0.6.0' );
define( 'QIC_FILE', __FILE__ );
define( 'QIC_PATH', plugin_dir_path( __FILE__ ) );
define( 'QIC_URL', plugin_dir_url( __FILE__ ) );

require_once QIC_PATH . 'includes/class-qic-tiers.php';
require_once QIC_PATH . 'includes/class-qic-admin.php';
require_once QIC_PATH . 'includes/class-qic-frontend.php';
require_once QIC_PATH . 'includes/class-qic-cart.php';
require_once QIC_PATH . 'includes/class-qic-portal.php';
require_once QIC_PATH . 'includes/class-qic-order.php';
// Dispatch webhook removed: crypto payments are verified manually (v0.5.5).
// require_once QIC_PATH . 'includes/class-qic-dispatch.php';

// High-Performance Order Storage compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', QIC_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', 'qic_boot' );
/**
 * Boot the plugin once we know WooCommerce is present.
 */
function qic_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html__( 'Quick Instant Checkout requires WooCommerce to be installed and active.', 'quick-instant-checkout' )
				);
			}
		);
		return;
	}
	QIC_Admin::instance();
	QIC_Frontend::instance();
	QIC_Cart::instance();
	QIC_Portal::instance();
	QIC_Order::instance();
	// QIC_Dispatch::instance();
}

/**
 * Global plugin settings with defaults mirroring the reference site.
 *
 * @return array
 */
function qic_settings() {
	$defaults = array(
		'dispatch_label'         => __( 'Instant (< 3 minutes)', 'quick-instant-checkout' ),
		'warranty_label'         => __( '72 Hours Guaranteed', 'quick-instant-checkout' ),
		'button_template'        => __( 'Proceed to Payment ({price})', 'quick-instant-checkout' ),
		'note_text'              => __( 'Instant delivery via CSV spreadsheet upon payment confirmation.', 'quick-instant-checkout' ),
		'accent_color'           => '#e8243f',
		'theme_preset'           => 'red',
		'load_fonts'             => 'yes',
		'show_category_products' => 'yes',
		'portal_subtitle'        => __( 'Automated verification & instant recovery file delivery', 'quick-instant-checkout' ),
		'crypto_ids'             => 'nowpayments, coinbase, btcpay, binance, cryptomus, confirmo',
		'terms_text'             => __( 'I agree to the terms of service and the replacement policy.', 'quick-instant-checkout' ),
		'crypto_order_status'    => 'on-hold',
	);
	return wp_parse_args( get_option( 'qic_settings', array() ), $defaults );
}

/**
 * Crypto wallet protocols shown in the portal payment step.
 *
 * @return array[]
 */
function qic_protocols() {
	$defaults = array(
		array(
			'enabled' => 1,
			'code'    => 'BTC',
			'name'    => __( 'Bitcoin', 'quick-instant-checkout' ),
			'address' => '',
			'badge'   => __( 'Popular · Zero Fee', 'quick-instant-checkout' ),
		),
		array(
			'enabled' => 1,
			'code'    => 'ETH',
			'name'    => __( 'Ethereum', 'quick-instant-checkout' ),
			'address' => '',
			'badge'   => '',
		),
		array(
			'enabled' => 1,
			'code'    => 'USDT',
			'name'    => __( 'Tether USDT', 'quick-instant-checkout' ),
			'address' => '',
			'badge'   => '',
		),
		array(
			'enabled' => 1,
			'code'    => 'USDC',
			'name'    => __( 'USD Coin', 'quick-instant-checkout' ),
			'address' => '',
			'badge'   => '',
		),
	);
	$saved = get_option( 'qic_protocols', null );
	return is_array( $saved ) ? $saved : $defaults;
}

/**
 * Fixed QR image template (crypto is verified manually; no API config needed).
 *
 * @return string
 */
function qic_qr_template() {
	return 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data={address}';
}

/**
 * Available color theme presets.
 *
 * @return array preset_id => hex.
 */
function qic_theme_presets() {
	return array(
		'red'    => '#e8243f',
		'blue'   => '#2563eb',
		'green'  => '#16a34a',
		'purple' => '#7c3aed',
		'orange' => '#ea580c',
		'teal'   => '#0d9488',
	);
}

/**
 * Effective accent color: preset hex, or the custom color when preset = custom.
 *
 * @return string Hex color.
 */
function qic_accent_color() {
	$settings = qic_settings();
	$presets  = qic_theme_presets();
	$preset   = $settings['theme_preset'];
	if ( isset( $presets[ $preset ] ) ) {
		return $presets[ $preset ];
	}
	$custom = $settings['accent_color'];
	return $custom ? $custom : '#e8243f';
}
