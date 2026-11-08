<?php
/**
 * Tier reader service: normalizes variable-product variations into package tiers.
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

class QIC_Tiers {

	/**
	 * Resolve mixed input to a WC_Product.
	 *
	 * @param mixed $product Product object or ID.
	 * @return WC_Product|false
	 */
	public static function product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}
		return $product;
	}

	/**
	 * Is the Quick Instant Checkout widget enabled for this product?
	 *
	 * @param mixed $product Product object or ID.
	 * @return bool
	 */
	public static function is_enabled( $product ) {
		$product = self::product( $product );
		return $product && 'yes' === $product->get_meta( '_qic_enabled' );
	}

	/**
	 * Build the tier list from the product's variations.
	 *
	 * @param mixed $product Product object or ID.
	 * @return array[] Tiers: id, label, accounts, price, per_account, badge, in_stock, attributes, is_default.
	 */
	public static function get_tiers( $product ) {
		$product = self::product( $product );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return array();
		}

		$tiers         = array();
		$default_tier  = (int) $product->get_meta( '_qic_default_tier' );
		$found_default = false;

		foreach ( $product->get_available_variations() as $variation_data ) {
			$variation = wc_get_product( $variation_data['variation_id'] );
			if ( ! $variation ) {
				continue;
			}

			$price = (float) $variation_data['display_price'];
			$label = (string) $variation->get_meta( '_qic_label' );
			if ( '' === $label ) {
				$label = $variation->get_attribute_summary();
			}
			if ( '' === $label ) {
				$label = $variation->get_name();
			}
			// Strip an attribute-name prefix (e.g. "Package: 2 Accounts" -> "2 Accounts").
			$clean_label = trim( (string) preg_replace( '/^[\w\s-]+:\s*/', '', $label ) );
			if ( '' === $clean_label ) {
				$clean_label = $label;
			}

			// Accounts: explicit meta wins; otherwise parse the number from the label
			// so manually-created variations never show "0x".
			$accounts = (int) $variation->get_meta( '_qic_accounts' );
			if ( $accounts <= 0 && preg_match( '/(\d+)/', $clean_label, $matches ) ) {
				$accounts = (int) $matches[1];
			}

			$title_base = $accounts > 0
				? sprintf( _n( '%d Account', '%d Accounts', $accounts, 'quick-instant-checkout' ), $accounts )
				: $clean_label;

			$tiers[] = array(
				'id'          => (int) $variation_data['variation_id'],
				'label'       => $clean_label,
				'title'       => sprintf( __( '%s Package', 'quick-instant-checkout' ), $title_base ),
				'accounts'    => $accounts,
				'price'       => $price,
				'regular'     => (float) $variation_data['display_regular_price'],
				'save'        => max( 0, round( (float) $variation_data['display_regular_price'] - $price, 2 ) ),
				'per_account' => $accounts > 0 ? round( $price / $accounts, 2 ) : $price,
				'badge'       => (string) $variation->get_meta( '_qic_badge' ),
				'ribbon'      => (string) $variation->get_meta( '_qic_ribbon' ),
				'in_stock'    => (bool) $variation_data['is_in_stock'] && (bool) $variation_data['is_purchasable'],
				'attributes'  => $variation_data['attributes'],
				'is_default'  => false,
			);
		}

		// Mark the configured default tier, falling back to the first purchasable one.
		foreach ( $tiers as $i => $tier ) {
			if ( $default_tier && $tier['id'] === $default_tier && $tier['in_stock'] ) {
				$tiers[ $i ]['is_default'] = true;
				$found_default             = true;
			}
		}
		if ( ! $found_default ) {
			foreach ( $tiers as $i => $tier ) {
				if ( $tier['in_stock'] ) {
					$tiers[ $i ]['is_default'] = true;
					break;
				}
			}
		}

		return $tiers;
	}
}
