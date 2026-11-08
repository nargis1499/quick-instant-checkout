<?php
/**
 * Multistep Order Portal (modal checkout): Package -> Contact -> Payment -> Verify -> Success.
 *
 * Creates orders server-side (no cart involved). Crypto path: manual transfer with
 * TXID + optional proof screenshot (order goes on-hold for verification). Classic
 * gateway path: redirect to the order pay page as before.
 *
 * v0.4.1: billing/contact props are set at order-creation time, every risky step is
 * crash-guarded, and any Throwable is logged + reported instead of white-screening.
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

class QIC_Portal {

	/** Singleton. */
	private static $instance = null;

	/** Whether the modal markup was already printed on this page. */
	private static $rendered = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wc_ajax_qic_nonce', array( $this, 'ajax_nonce' ) );
		add_action( 'wc_ajax_qic_place_order', array( $this, 'ajax_place_order' ) );
		add_action( 'wc_ajax_qic_get_product', array( $this, 'ajax_get_product' ) );
	}

	public static function has_rendered() {
		return self::$rendered;
	}

	public static function mark_rendered() {
		self::$rendered = true;
	}

	/**
	 * All QIC-enabled products with their tiers, for the category grid.
	 *
	 * When a context product is given and relevant category products are enabled,
	 * products sharing at least one WooCommerce product category with it
	 * are listed (the context product itself is always included).
	 * When category products are disabled, only the context product is listed.
	 *
	 * @param int $context_id Product the portal was opened from.
	 * @return array[]
	 */
	public static function catalog( $context_id = 0 ) {
		$context_id             = (int) $context_id;
		$settings               = qic_settings();
		$show_category_products = ! empty( $settings['show_category_products'] ) && 'yes' === $settings['show_category_products'];

		if ( $context_id && ! $show_category_products ) {
			$product = wc_get_product( $context_id );
			if ( ! $product || ! QIC_Tiers::is_enabled( $product ) || ! ( $product->is_type( 'variable' ) || $product->is_type( 'simple' ) ) ) {
				return array();
			}
			$tiers = QIC_Tiers::get_tiers( $product );
			if ( ! $tiers ) {
				return array();
			}
			$prices = array_column( $tiers, 'price' );
			return array(
				array(
					'id'      => (int) $product->get_id(),
					'name'    => $product->get_name(),
					'emoji'   => (string) $product->get_meta( '_qic_emoji' ),
					'tagline' => (string) $product->get_meta( '_qic_tagline' ),
					'from'    => $prices ? min( $prices ) : 0,
					'tiers'   => $tiers,
				),
			);
		}

		$ids = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'key'   => '_qic_enabled',
						'value' => 'yes',
					),
				),
			)
		);

		if ( $context_id ) {
			$context_cats = wc_get_product_term_ids( $context_id, 'product_cat' );
			if ( $context_cats ) {
				$filtered = array();
				foreach ( $ids as $id ) {
					if ( (int) $id === $context_id ) {
						$filtered[] = (int) $id;
						continue;
					}
					$cats = wc_get_product_term_ids( (int) $id, 'product_cat' );
					if ( array_intersect( $cats, $context_cats ) ) {
						$filtered[] = (int) $id;
					}
				}
				$ids = $filtered;
			}
		}

		$cats = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! ( $product->is_type( 'variable' ) || $product->is_type( 'simple' ) ) ) {
				continue;
			}
			$tiers = QIC_Tiers::get_tiers( $product );
			if ( ! $tiers ) {
				continue;
			}
			$prices = array_column( $tiers, 'price' );
			$cats[] = array(
				'id'      => (int) $product->get_id(),
				'name'    => $product->get_name(),
				'emoji'   => (string) $product->get_meta( '_qic_emoji' ),
				'tagline' => (string) $product->get_meta( '_qic_tagline' ),
				'from'    => $prices ? min( $prices ) : 0,
				'tiers'   => $tiers,
			);
		}
		return $cats;
	}

	/**
	 * Enabled crypto protocols from settings.
	 *
	 * @return array[]
	 */
	public static function protocols() {
		$out = array();
		foreach ( qic_protocols() as $proto ) {
			if ( empty( $proto['enabled'] ) || '' === trim( (string) $proto['code'] ) ) {
				continue;
			}
			$out[] = array(
				'code'    => (string) $proto['code'],
				'name'    => (string) $proto['name'],
				'address' => (string) $proto['address'],
				'badge'   => (string) $proto['badge'],
			);
		}
		return $out;
	}

	/**
	 * Available classic gateways for the fallback payment section.
	 *
	 * @return array
	 */
	public static function gateways() {
		$out = array();
		if ( ! WC()->payment_gateways ) {
			return $out;
		}
		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $id => $gateway ) {
			$out[ $id ] = array(
				'title' => $gateway->get_title(),
				'desc'  => wp_strip_all_tags( $gateway->get_description() ),
			);
		}
		return $out;
	}

	/**
	 * AJAX endpoint to fetch portal category and tier data for a specific product.
	 */
	public function ajax_get_product() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		wc_nocache_headers();

		$product_id = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0;
		if ( ! $product_id && isset( $_POST['product_id'] ) ) {
			$product_id = absint( wp_unslash( $_POST['product_id'] ) );
		}
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing product ID.', 'quick-instant-checkout' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! ( $product->is_type( 'variable' ) || $product->is_type( 'simple' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'quick-instant-checkout' ) ) );
		}

		$tiers = QIC_Tiers::get_tiers( $product );
		if ( ! $tiers ) {
			wp_send_json_error( array( 'message' => __( 'No purchasable tiers for this product.', 'quick-instant-checkout' ) ) );
		}

		$prices   = array_column( $tiers, 'price' );
		$symbol   = get_woocommerce_currency_symbol();
		$emoji    = (string) $product->get_meta( '_qic_emoji' );
		$tagline  = (string) $product->get_meta( '_qic_tagline' );
		$from     = $prices ? min( $prices ) : 0;
		$cat_data = array(
			'id'      => (int) $product->get_id(),
			'name'    => $product->get_name(),
			'emoji'   => $emoji,
			'tagline' => $tagline,
			'from'    => $from,
			'tiers'   => $tiers,
		);

		ob_start();
		?>
		<button type="button" class="qic-cat" data-cat="<?php echo esc_attr( $cat_data['id'] ); ?>">
			<span class="qic-cat-info">
				<strong><?php echo esc_html( trim( $cat_data['emoji'] . ' ' . $cat_data['name'] ) ); ?></strong>
				<?php if ( '' !== $cat_data['tagline'] ) : ?>
					<small><?php echo esc_html( $cat_data['tagline'] ); ?></small>
				<?php endif; ?>
			</span>
			<span class="qic-cat-from"><?php esc_html_e( 'From', 'quick-instant-checkout' ); ?> <?php echo esc_html( $symbol . number_format_i18n( $cat_data['from'], 2 ) ); ?></span>
		</button>
		<?php
		$cat_html = ob_get_clean();

		ob_start();
		?>
		<div class="qic-tier-tiles" data-cat="<?php echo esc_attr( $cat_data['id'] ); ?>" hidden>
			<?php foreach ( $cat_data['tiers'] as $tier ) : ?>
				<button type="button"
					class="qic-tile<?php echo $tier['in_stock'] ? '' : ' is-disabled'; ?>"
					data-cat="<?php echo esc_attr( $cat_data['id'] ); ?>"
					data-tier="<?php echo esc_attr( $tier['id'] ); ?>"
					data-accounts="<?php echo esc_attr( $tier['accounts'] ); ?>"
					data-price="<?php echo esc_attr( $tier['price'] ); ?>"
					data-per="<?php echo esc_attr( $tier['per_account'] ); ?>"
					data-default="<?php echo $tier['is_default'] ? '1' : '0'; ?>"
					<?php disabled( ! $tier['in_stock'] ); ?>>
					<?php if ( '' !== $tier['ribbon'] ) : ?>
						<span class="qic-ribbon"><?php echo esc_html( $tier['ribbon'] ); ?></span>
					<?php endif; ?>
					<strong><?php echo esc_html( $tier['accounts'] > 0 ? $tier['accounts'] . 'x' : $tier['label'] ); ?></strong>
					<?php if ( $tier['save'] > 0 ) : ?>
						<span class="qic-tile-save"><?php echo esc_html( sprintf( __( 'Save %s', 'quick-instant-checkout' ), $symbol . number_format_i18n( $tier['save'], 2 ) ) ); ?></span>
					<?php endif; ?>
					<span class="qic-tile-price"><?php echo esc_html( $symbol . number_format_i18n( $tier['price'], 2 ) ); ?></span>
					<small>
						<?php
						if ( ! $tier['in_stock'] ) {
							esc_html_e( 'Sold out', 'quick-instant-checkout' );
						} else {
							echo esc_html( $tier['badge'] );
						}
						?>
					</small>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
		$tiers_html = ob_get_clean();

		wp_send_json_success(
			array(
				'cat'        => $cat_data,
				'cat_html'   => $cat_html,
				'tiers_html' => $tiers_html,
			)
		);
	}

	/**
	 * Session-safe nonce for order placement (fetched via ajax, cache-proof).
	 */
	public function ajax_nonce() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'qic_place_order' ) ) );
	}

	/**
	 * Validate the portal submission and create the order. Crash-guarded.
	 */
	public function ajax_place_order() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		wc_nocache_headers();

		try {
			$this->place_order_inner();
		} catch ( \Throwable $e ) {
			error_log( '[QIC] place_order fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			$detail = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				? ' [' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . ']'
				: ' ' . __( '(Exact cause written to debug.log — enable WP_DEBUG_LOG to view.)', 'quick-instant-checkout' );
			wp_send_json_error(
				array(
					'message' => __( 'The order could not be completed on the server.', 'quick-instant-checkout' ) . $detail,
				)
			);
		}
	}

	/**
	 * The actual order creation flow.
	 */
	private function place_order_inner() {
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( ! wp_verify_nonce( $token, 'qic_place_order' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please close the checkout and try again.', 'quick-instant-checkout' ) ) );
		}

		$settings      = qic_settings();
		$variation_id  = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$is_custom     = ! empty( $_POST['is_custom'] ) && '1' === $_POST['is_custom'];
		$custom_qty    = isset( $_POST['custom_qty'] ) ? absint( wp_unslash( $_POST['custom_qty'] ) ) : 0;
		$custom_price  = isset( $_POST['custom_price'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['custom_price'] ) ) : 0.0;
		$full_name     = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		$email         = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$telegram      = sanitize_text_field( wp_unslash( $_POST['telegram'] ?? '' ) );
		$whatsapp      = sanitize_text_field( wp_unslash( $_POST['whatsapp'] ?? '' ) );
		$country       = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
		$notes         = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		$protocol_code = sanitize_key( wp_unslash( $_POST['protocol'] ?? '' ) );
		$gateway_id    = sanitize_key( wp_unslash( $_POST['gateway'] ?? '' ) );
		$txid          = sanitize_text_field( wp_unslash( $_POST['txid'] ?? '' ) );

		if ( '' === $full_name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your full name.', 'quick-instant-checkout' ) ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid email address for delivery.', 'quick-instant-checkout' ) ) );
		}

		$selected_product = wc_get_product( $variation_id );
		if ( ! $selected_product ) {
			wp_send_json_error( array( 'message' => __( 'The selected package is unavailable. Please choose another one.', 'quick-instant-checkout' ) ) );
		}

		if ( $selected_product->is_type( 'variation' ) ) {
			$parent          = wc_get_product( $selected_product->get_parent_id() );
			$package_product = $selected_product;
		} elseif ( $selected_product->is_type( 'simple' ) ) {
			$parent          = $selected_product;
			$package_product = $selected_product;
		} else {
			$parent          = null;
			$package_product = null;
		}

		if ( ! $parent || ! $package_product || ! $package_product->is_purchasable() || ! $package_product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'The selected package is unavailable. Please choose another one.', 'quick-instant-checkout' ) ) );
		}

		$is_crypto = '' !== $protocol_code;
		$protocol  = null;
		if ( $is_crypto ) {
			foreach ( self::protocols() as $candidate ) {
				if ( strtolower( $candidate['code'] ) === strtolower( $protocol_code ) ) {
					$protocol = $candidate;
					break;
				}
			}
			if ( ! $protocol ) {
				wp_send_json_error( array( 'message' => __( 'Unknown payment protocol. Please pick one from the list.', 'quick-instant-checkout' ) ) );
			}
			if ( strlen( $txid ) < 8 ) {
				wp_send_json_error( array( 'message' => __( 'Please enter your crypto transaction hash / TXID.', 'quick-instant-checkout' ) ) );
			}
		} else {
			$gateways = WC()->payment_gateways ? WC()->payment_gateways()->get_available_payment_gateways() : array();
			if ( ! isset( $gateways[ $gateway_id ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Please choose a payment method.', 'quick-instant-checkout' ) ) );
			}
		}

		// Optional proof screenshot first (fail before creating an orphan order).
		$attachment_id = 0;
		if ( $is_crypto && ! empty( $_FILES['proof'] ) && ! empty( $_FILES['proof']['name'] ) && empty( $_FILES['proof']['error'] ) ) {
			$attachment_id = $this->handle_proof_upload();
			if ( is_wp_error( $attachment_id ) ) {
				wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
			}
		}

		// Contact details go in at creation time so they can never be lost mid-flow.
		$name_parts = explode( ' ', $full_name, 2 );
		$order_args = array(
			'billing_email'      => $email,
			'billing_first_name' => $name_parts[0],
			'billing_last_name'  => isset( $name_parts[1] ) ? $name_parts[1] : '',
			'customer_id'        => 0,
			'created_via'        => 'qic-portal',
		);
		if ( $country && WC()->countries && array_key_exists( $country, WC()->countries->get_countries() ) ) {
			$order_args['billing_country'] = $country;
		}
		$order = wc_create_order( $order_args );
		if ( ! $order || is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce could not create the order object.', 'quick-instant-checkout' ) ) );
		}

		// NB: WC_Order::add_product() returns the item OBJECT in some versions and
		// the item ID (int) in others — handle both.
		$added = $order->add_product( $package_product, 1 );
		$item  = ( $added instanceof WC_Order_Item ) ? $added : $order->get_item( (int) $added );

		// Stamp package info onto the line item (admin + emails).
		if ( $item ) {
			$accounts = (int) $package_product->get_meta( '_qic_accounts' );
			if ( $accounts <= 0 && $package_product->is_type( 'simple' ) ) {
				$accounts = 1;
			}
			if ( $is_custom && $custom_qty > 0 ) {
				$accounts = $custom_qty;
				if ( $custom_price > 0 ) {
					$item->set_subtotal( $custom_price );
					$item->set_total( $custom_price );
				}
				$item->add_meta_data( __( 'Custom Quantity', 'quick-instant-checkout' ), sprintf( __( '%d accounts (custom)', 'quick-instant-checkout' ), $custom_qty ) );
			}
			if ( $accounts > 0 ) {
				$item->add_meta_data( __( 'Accounts in package', 'quick-instant-checkout' ), $accounts );
			}
			$dispatch = $parent->get_meta( '_qic_dispatch' );
			$warranty = $parent->get_meta( '_qic_warranty' );
			$item->add_meta_data( __( 'Automated Dispatch', 'quick-instant-checkout' ), '' !== $dispatch ? $dispatch : $settings['dispatch_label'] );
			$item->add_meta_data( __( 'Replacement Warranty', 'quick-instant-checkout' ), '' !== $warranty ? $warranty : $settings['warranty_label'] );
			$item->save();
		}

		// Extra contact channels + notes.
		if ( '' !== $telegram ) {
			$order->update_meta_data( '_qic_telegram', $telegram );
		}
		if ( '' !== $whatsapp ) {
			$order->update_meta_data( '_qic_whatsapp', $whatsapp );
		}
		if ( '' !== $notes ) {
			$order->set_customer_note( $notes );
		}
		$order->update_meta_data( '_qic_portal', '1' );

		if ( $is_crypto ) {
			$method_title = sprintf( '%1$s (%2$s)', $protocol['name'], $protocol['code'] );
			$order->set_payment_method( 'qic_crypto_manual' );
			$order->set_payment_method_title( $method_title );
			$order->update_meta_data( '_qic_protocol', $protocol['code'] );
			$order->update_meta_data( '_qic_txid', $txid );
			if ( $attachment_id ) {
				$order->update_meta_data( '_qic_proof_image', $attachment_id );
			}

			$order->calculate_totals();
			$order->save();

			$status = in_array( $settings['crypto_order_status'], array( 'on-hold', 'processing', 'pending' ), true )
				? $settings['crypto_order_status']
				: 'on-hold';
			$note   = sprintf( __( 'QIC: crypto order submitted. TXID: %s — awaiting blockchain verification.', 'quick-instant-checkout' ), $txid );
			try {
				$order->update_status( $status, $note );
			} catch ( \Throwable $e ) {
				// Status hooks/emails must never kill the customer response.
				error_log( '[QIC] update_status failed: ' . $e->getMessage() );
				try {
					$order->set_status( $status, $note );
					$order->save();
				} catch ( \Throwable $e2 ) {
					error_log( '[QIC] set_status fallback failed: ' . $e2->getMessage() );
				}
			}

			if ( WC()->session ) {
				WC()->session->set( 'order_awaiting_payment', $order->get_id() );
			}

			$this->audit_and_repair(
				$order,
				array(
					'email'         => $email,
					'full_name'     => $full_name,
					'name_parts'    => $name_parts,
					'country'       => $country,
					'method_title'  => $method_title,
					'txid'          => $txid,
					'attachment_id' => $attachment_id,
					'telegram'      => $telegram,
					'whatsapp'      => $whatsapp,
					'custom_qty'    => $is_custom ? $custom_qty : 0,
				),
				'qic_crypto_manual'
			);

			/** This action fires after a portal order was created. */
			do_action( 'qic_portal_order_created', $order );

			wp_send_json_success(
				array(
					'congrats'     => true,
					'order_id'     => $order->get_id(),
					'order_number' => $order->get_order_number(),
					'email'        => $email,
				)
			);
		}

		// Classic gateway path.
		$gateway = WC()->payment_gateways()->get_available_payment_gateways()[ $gateway_id ];
		$order->set_payment_method( $gateway );
		$order->calculate_totals();
		$order->save();

		if ( WC()->session ) {
			WC()->session->set( 'order_awaiting_payment', $order->get_id() );
		}

		$this->audit_and_repair(
			$order,
			array(
				'email'         => $email,
				'full_name'     => $full_name,
				'name_parts'    => $name_parts,
				'country'       => $country,
				'method_title'  => $gateway->get_title(),
				'txid'          => '',
				'attachment_id' => 0,
				'custom_qty'    => $is_custom ? $custom_qty : 0,
			),
			$gateway->id
		);

		/** This action fires after a portal order was created. */
		do_action( 'qic_portal_order_created', $order );

		wp_send_json_success(
			array(
				'order_id' => $order->get_id(),
				'redirect' => $order->get_checkout_payment_url( true ),
			)
		);
	}

	/**
	 * Post-save audit & repair: re-read the order from the database and re-apply
	 * any props WooCommerce dropped during save (observed on WC 11 + HPOS), then
	 * leave a consolidated admin note with contact + TXID for guaranteed visibility.
	 *
	 * @param WC_Order $order     Order just saved.
	 * @param array    $data      Expected values (email, full_name, name_parts, country, method_title, txid, attachment_id).
	 * @param string   $method_id Payment method id.
	 */
	private function audit_and_repair( WC_Order $order, $data, $method_id ) {
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh ) {
			return;
		}
		$repair = false;

		if ( ! $fresh->get_billing_email() ) {
			$fresh->set_billing_email( $data['email'] );
			$repair = true;
		}
		if ( ! $fresh->get_billing_first_name() ) {
			$fresh->set_billing_first_name( $data['name_parts'][0] );
			$repair = true;
		}
		if ( ! $fresh->get_billing_last_name() && ! empty( $data['name_parts'][1] ) ) {
			$fresh->set_billing_last_name( $data['name_parts'][1] );
			$repair = true;
		}
		if ( ! empty( $data['country'] ) && ! $fresh->get_billing_country() ) {
			$fresh->set_billing_country( $data['country'] );
			$repair = true;
		}
		if ( '' === $fresh->get_payment_method() ) {
			$fresh->set_payment_method( $method_id );
			$repair = true;
		}
		if ( '' === $fresh->get_payment_method_title() && ! empty( $data['method_title'] ) ) {
			$fresh->set_payment_method_title( $data['method_title'] );
			$repair = true;
		}
		if ( ! empty( $data['txid'] ) && ! $fresh->get_meta( '_qic_txid' ) ) {
			$fresh->update_meta_data( '_qic_txid', $data['txid'] );
			$repair = true;
		}
		if ( ! empty( $data['attachment_id'] ) && ! $fresh->get_meta( '_qic_proof_image' ) ) {
			$fresh->update_meta_data( '_qic_proof_image', $data['attachment_id'] );
			$repair = true;
		}
		if ( $repair ) {
			$fresh->save();
		}

		$note = sprintf(
			/* translators: 1: email, 2: full name. */
			__( 'QIC portal contact: %1$s (%2$s)', 'quick-instant-checkout' ),
			$data['email'],
			$data['full_name']
		);
		if ( ! empty( $data['custom_qty'] ) ) {
			$note .= ' | ' . sprintf( __( 'Custom Qty: %d', 'quick-instant-checkout' ), (int) $data['custom_qty'] );
		}
		if ( ! empty( $data['telegram'] ) ) {
			$note .= ' | TG: ' . $data['telegram'];
		}
		if ( ! empty( $data['whatsapp'] ) ) {
			$note .= ' | WA: ' . $data['whatsapp'];
		}
		if ( ! empty( $data['txid'] ) ) {
			$note .= ' — TXID: ' . $data['txid'];
		}
		$fresh->add_order_note( $note );

		error_log(
			'[QIC] audit order #' . $fresh->get_id() .
			': repaired=' . ( $repair ? 'yes' : 'no' ) .
			' email=' . $fresh->get_billing_email() .
			' pm_title=' . $fresh->get_payment_method_title()
		);
	}

	/**
	 * Save the uploaded payment screenshot into the media library.
	 *
	 * @return int|WP_Error Attachment id.
	 */
	private function handle_proof_upload() {
		$file    = $_FILES['proof']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$allowed = array( 'image/png', 'image/jpeg', 'image/gif' );
		$type    = function_exists( 'mime_content_type' ) && ! empty( $file['tmp_name'] )
			? mime_content_type( $file['tmp_name'] )
			: $file['type'];
		if ( ! in_array( $type, $allowed, true ) ) {
			return new WP_Error( 'qic_bad_file', __( 'Only PNG, JPG or GIF screenshots are allowed.', 'quick-instant-checkout' ) );
		}
		if ( (int) $file['size'] > 5 * 1024 * 1024 ) {
			return new WP_Error( 'qic_big_file', __( 'The screenshot must be 5MB or smaller.', 'quick-instant-checkout' ) );
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$attachment_id = media_handle_upload( 'proof', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'qic_upload', __( 'The screenshot could not be uploaded. You can still submit without it.', 'quick-instant-checkout' ) );
		}
		return (int) $attachment_id;
	}
}
