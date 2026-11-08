<?php
/**
 * Dispatch automation: push paid orders to a fulfillment webhook with retries.
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

class QIC_Dispatch {

	const MAX_RETRIES = 5;

	/** Singleton. */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_dispatch' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_dispatch' ) );
		add_action( 'qic_dispatch_retry', array( $this, 'maybe_dispatch' ) );
	}

	/**
	 * Push the order payload to the configured webhook (once, with retries).
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_dispatch( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$settings = qic_settings();
		if ( '' === $settings['webhook_url'] ) {
			return;
		}
		if ( 'sent' === $order->get_meta( '_qic_dispatch_status' ) ) {
			return;
		}

		$items = $this->qic_items( $order );
		if ( ! $items ) {
			return;
		}

		$payload = array(
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'email'        => $order->get_billing_email(),
			'currency'     => $order->get_currency(),
			'total'        => $order->get_total(),
			'items'        => $items,
		);

		$response = wp_remote_post(
			$settings['webhook_url'],
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		$failed = is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) >= 300;

		if ( $failed ) {
			$retries = (int) $order->get_meta( '_qic_dispatch_retries' );
			$order->update_meta_data( '_qic_dispatch_status', 'failed' );
			$order->update_meta_data( '_qic_dispatch_retries', $retries + 1 );
			$order->add_order_note( __( 'QIC: dispatch webhook failed; retry scheduled.', 'quick-instant-checkout' ) );
			if ( $retries < self::MAX_RETRIES ) {
				wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'qic_dispatch_retry', array( $order->get_id() ) );
			}
		} else {
			$order->update_meta_data( '_qic_dispatch_status', 'sent' );
			$order->add_order_note( __( 'QIC: order pushed to automated dispatch.', 'quick-instant-checkout' ) );
			if ( 'yes' === $settings['auto_complete'] ) {
				$order->update_status( 'completed', __( 'QIC: auto-completed after dispatch.', 'quick-instant-checkout' ) );
			}
		}
		$order->save();
	}

	/**
	 * Collect QIC line items of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array[]
	 */
	private function qic_items( WC_Order $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( $product instanceof WC_Product_Variation ) {
				$parent = wc_get_product( $product->get_parent_id() );
			} elseif ( $product->is_type( 'simple' ) ) {
				$parent = $product;
			} else {
				continue;
			}
			if ( ! $parent || 'yes' !== $parent->get_meta( '_qic_enabled' ) ) {
				continue;
			}
			$accounts = (int) $product->get_meta( '_qic_accounts' );
			if ( $accounts <= 0 && $product->is_type( 'simple' ) ) {
				$accounts = 1;
			}
			$items[] = array(
				'sku'      => $product->get_sku(),
				'label'    => $item->get_name(),
				'accounts' => $accounts,
				'quantity' => $item->get_quantity(),
			);
		}
		return $items;
	}
}
