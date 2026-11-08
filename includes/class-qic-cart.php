<?php
/**
 * Dedicated add-to-cart endpoint: /?wc-ajax=qic_add_to_cart
 *
 * Why not the core wc-ajax add_to_cart? Because we control the whole path here:
 * stray output from other plugins is discarded (clean JSON guaranteed), variation
 * attributes are derived server-side (never trusted from the client), and failures
 * return a human-readable reason instead of a silent error.
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

class QIC_Cart {

	/** Singleton. */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// wc-ajax events run for logged-in and guest users alike; no nopriv variant needed.
		add_action( 'wc_ajax_qic_add_to_cart', array( $this, 'add_to_cart' ) );
	}

	/**
	 * Handle the CTA request: validate server-side, add package, return checkout URL.
	 */
	public function add_to_cart() {
		// Discard any stray output (PHP notices/warnings from other plugins) so our JSON stays parseable.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		wc_nocache_headers();

		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'The cart is unavailable right now. Please reload the page and try again.', 'quick-instant-checkout' ) ) );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;

		$product = wc_get_product( $product_id );
		if ( ! $product || ! QIC_Tiers::is_enabled( $product ) ) {
			wp_send_json_error( array( 'message' => __( 'This product is not available for quick checkout.', 'quick-instant-checkout' ) ) );
		}

		$variation = $variation_id ? wc_get_product( $variation_id ) : null;
		if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== (int) $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package selection. Please refresh the page and try again.', 'quick-instant-checkout' ) ) );
		}
		if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'This package is sold out. Please choose another one.', 'quick-instant-checkout' ) ) );
		}

		// Derive variation attributes server-side, mirroring WooCommerce's own
		// validation loop (parent variation attributes -> variation values).
		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute->get_variation() ) {
				continue;
			}
			$key   = 'attribute_' . sanitize_title( $attribute->get_name() );
			$value = $variation->get_attribute( $attribute->get_name() );
			if ( '' === $value ) {
				$value = (string) get_post_meta( $variation_id, $key, true );
			}
			$attributes[ $key ] = $value;
		}

		$added = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $attributes );
		if ( ! $added ) {
			// Fallback: add the variation directly (its own line), which bypasses
			// attribute re-validation entirely (covers odd importer storage).
			$added = WC()->cart->add_to_cart( $variation_id, 1 );
		}
		if ( ! $added ) {
			ob_start();
			wc_print_notices();
			$notices = trim( wp_strip_all_tags( (string) ob_get_clean() ) );
			wp_send_json_error(
				array(
					'message' => $notices ? $notices : __( 'The package could not be added to the cart.', 'quick-instant-checkout' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'checkout_url' => wc_get_checkout_url(),
				'cart_hash'    => WC()->cart->get_cart_hash(),
			)
		);
	}
}
