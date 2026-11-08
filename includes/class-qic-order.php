<?php
/**
 * Order pipeline: copy tier/dispatch/warranty info into order line item meta.
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

class QIC_Order {

	/** Singleton. */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'copy_tier_meta' ), 10, 4 );
		// Full-width meta box on the order edit screen (classic + HPOS).
		add_action( 'add_meta_boxes', array( $this, 'register_admin_meta_box' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_admin_block' ), 10, 4 );
	}

	/**
	 * Register the full-width portal details meta box (only on QIC orders).
	 */
	public function register_admin_meta_box() {
		$order_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $order_id && isset( $_GET['post'] ) ) {
			$order_id = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order || '1' !== $order->get_meta( '_qic_portal' ) ) {
			return;
		}
		$title = __( 'Quick Checkout (portal) details', 'quick-instant-checkout' );
		add_meta_box( 'qic-portal-details', $title, array( $this, 'render_admin_meta_box' ), 'shop_order', 'normal', 'high' );
		add_meta_box( 'qic-portal-details', $title, array( $this, 'render_admin_meta_box' ), 'woocommerce_page_wc-orders', 'normal', 'high' );
	}

	/**
	 * Render the portal details card (full width, icon tiles).
	 *
	 * @param WP_Post|WC_Order|int $post_or_order Order being displayed.
	 */
	public function render_admin_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( is_object( $post_or_order ) ? (int) $post_or_order->ID : (int) $post_or_order );
		if ( ! $order || '1' !== $order->get_meta( '_qic_portal' ) ) {
			return;
		}
		$telegram   = $order->get_meta( '_qic_telegram' );
		$whatsapp   = $order->get_meta( '_qic_whatsapp' );
		$txid       = $order->get_meta( '_qic_txid' );
		$protocol   = $order->get_meta( '_qic_protocol' );
		$proof      = (int) $order->get_meta( '_qic_proof_image' );
		$notes      = $order->get_customer_note();
		$proof_url  = $proof ? wp_get_attachment_url( $proof ) : '';
		$proof_thumb = $proof ? wp_get_attachment_image_url( $proof, 'thumbnail' ) : '';

		echo '<style>
			.qic-adm-card{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);border:1px solid #dfe5ee;border-radius:12px;padding:16px 18px;}
			.qic-adm-head{display:flex;align-items:center;gap:10px;margin:0 0 14px;flex-wrap:wrap;}
			.qic-adm-head strong{font-size:14px;color:#1e293b;}
			.qic-adm-pill{margin-left:auto;background:#fee2e2;color:#b91c1c;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;}
			.qic-adm-ico{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:9px;flex:0 0 34px;}
			.qic-adm-ico-head{background:#dc2626;color:#fff;}
			.qic-adm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;}
			.qic-adm-tile{display:flex;gap:12px;align-items:flex-start;background:#fff;border:1px solid #e3e8f0;border-radius:10px;padding:12px 14px;}
			.qic-adm-wide{grid-column:1/-1;}
			.qic-adm-txt{display:flex;flex-direction:column;gap:3px;min-width:0;width:100%;}
			.qic-adm-label{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:700;}
			.qic-adm-value{font-size:13.5px;color:#0f172a;font-weight:600;word-break:break-word;}
			.qic-adm-value code{background:#eef2f7;border:1px solid #dbe2ec;border-radius:6px;padding:4px 8px;font-size:12px;user-select:all;-webkit-user-select:all;display:inline-block;word-break:break-all;}
			.qic-adm-thumb{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
			.qic-adm-thumb img{width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #dbe2ec;}
			.qic-adm-thumb a{color:#2563eb;font-weight:600;text-decoration:none;}
			.qic-adm-thumb a:hover{text-decoration:underline;}
		</style>';

		echo '<div class="qic-adm-card">';
		echo '<div class="qic-adm-head">';
		echo '<span class="qic-adm-ico qic-adm-ico-head">' . $this->adm_icon( 'bolt' ) . '</span>';
		echo '<strong>' . esc_html__( 'Quick Checkout (portal) details', 'quick-instant-checkout' ) . '</strong>';
		echo '<span class="qic-adm-pill">' . esc_html__( 'Portal order', 'quick-instant-checkout' ) . '</span>';
		echo '</div>';
		echo '<div class="qic-adm-grid">';

		$any = false;
		$full_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$email     = $order->get_billing_email();
		$country_code = $order->get_billing_country();
		$country_name = '';
		if ( $country_code && function_exists( 'WC' ) && WC()->countries ) {
			$list         = WC()->countries->get_countries();
			$country_name = isset( $list[ $country_code ] ) ? $list[ $country_code ] : $country_code;
		}
		if ( '' !== $full_name ) {
			$any = true;
			echo $this->adm_tile( 'user', '#fdf2f8', '#db2777', __( 'Customer name', 'quick-instant-checkout' ), esc_html( $full_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $email ) {
			$any = true;
			echo $this->adm_tile( 'mail', '#eff6ff', '#2563eb', __( 'Email', 'quick-instant-checkout' ), '<a href="mailto:' . esc_attr( $email ) . '" style="color:#2563eb;text-decoration:none;">' . esc_html( $email ) . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $country_name ) {
			$any = true;
			echo $this->adm_tile( 'globe', '#fff7ed', '#ea580c', __( 'Country', 'quick-instant-checkout' ), esc_html( $country_name ) . ( $country_code ? ' <span style="color:#94a3b8;font-weight:600;">(' . esc_html( $country_code ) . ')</span>' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $telegram ) {
			$any = true;
			echo $this->adm_tile( 'send', '#e5f3fb', '#0088cc', __( 'Telegram', 'quick-instant-checkout' ), esc_html( $telegram ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $whatsapp ) {
			$any = true;
			echo $this->adm_tile( 'phone', '#e8f9ee', '#1fa855', __( 'WhatsApp', 'quick-instant-checkout' ), esc_html( $whatsapp ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $protocol ) {
			$any = true;
			echo $this->adm_tile( 'coin', '#fdf3e3', '#b45309', __( 'Payment protocol', 'quick-instant-checkout' ), esc_html( $protocol ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $txid ) {
			$any = true;
			echo $this->adm_tile( 'hash', '#eef2ff', '#4f46e5', __( 'Transaction hash (TXID)', 'quick-instant-checkout' ), '<code>' . esc_html( $txid ) . '</code>', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $proof_url ) {
			$any = true;
			$thumb = $proof_thumb ? '<img src="' . esc_url( $proof_thumb ) . '" alt="" />' : '';
			$value = '<span class="qic-adm-thumb">' . $thumb . '<a href="' . esc_url( $proof_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View uploaded image', 'quick-instant-checkout' ) . '</a></span>';
			echo $this->adm_tile( 'image', '#e6f7f4', '#0d9488', __( 'Payment screenshot', 'quick-instant-checkout' ), $value, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $notes ) {
			$any = true;
			echo $this->adm_tile( 'note', '#eef2f6', '#475569', __( 'Customer request', 'quick-instant-checkout' ), nl2br( esc_html( $notes ) ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $any ) {
			echo '<div class="qic-adm-tile qic-adm-wide"><div class="qic-adm-txt"><span class="qic-adm-value">' . esc_html__( 'No portal meta recorded for this order.', 'quick-instant-checkout' ) . '</span></div></div>';
		}

		echo '</div></div>';
	}

	/**
	 * One icon tile for the admin card.
	 *
	 * @param string $icon      Icon key.
	 * @param string $bg        Icon background color.
	 * @param string $fg        Icon color.
	 * @param string $label     Tile label.
	 * @param string $value     Tile value html (pre-escaped).
	 * @param bool   $wide      Span all columns?
	 * @return string
	 */
	private function adm_tile( $icon, $bg, $fg, $label, $value, $wide = false ) {
		$out  = '<div class="qic-adm-tile' . ( $wide ? ' qic-adm-wide' : '' ) . '">';
		$out .= '<span class="qic-adm-ico" style="background:' . esc_attr( $bg ) . ';color:' . esc_attr( $fg ) . ';">' . $this->adm_icon( $icon ) . '</span>';
		$out .= '<div class="qic-adm-txt"><span class="qic-adm-label">' . esc_html( $label ) . '</span><span class="qic-adm-value">' . $value . '</span></div>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * Inline SVG icon (stroke style, inherits currentColor).
	 *
	 * @param string $key Icon key.
	 * @return string
	 */
	private function adm_icon( $key ) {
		$svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
		switch ( $key ) {
			case 'bolt':
				$svg .= '<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8z"/>';
				break;
			case 'send':
				$svg .= '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>';
				break;
			case 'phone':
				$svg .= '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>';
				break;
			case 'coin':
				$svg .= '<circle cx="12" cy="12" r="9"/><path d="M12 7v10"/><path d="M15 9.5c-.8-.8-1.9-1.2-3-1.2-1.7 0-3 .8-3 1.9s1.3 1.6 3 1.9 3 .8 3 1.9-1.3 1.9-3 1.9c-1.1 0-2.2-.4-3-1.2"/>';
				break;
			case 'hash':
				$svg .= '<line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>';
				break;
			case 'image':
				$svg .= '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>';
				break;
			case 'user':
				$svg .= '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="2"/><path d="M5.5 15.5c.6-1.8 5.4-1.8 6 0"/><path d="M14.5 9h4.5"/><path d="M14.5 12.5h4.5"/>';
				break;
			case 'mail':
				$svg .= '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/>';
				break;
			case 'globe':
				$svg .= '<circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3a13.5 13.5 0 0 1 4 9 13.5 13.5 0 0 1-4 9 13.5 13.5 0 0 1-4-9 13.5 13.5 0 0 1 4-9z"/>';
				break;
			default: // note
				$svg .= '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>';
		}
		return $svg . '</svg>';
	}

	/**
	 * Append portal details to admin order emails.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Admin email?
	 * @param bool     $plain_text    Plain text email?
	 * @param WC_Email $email         Email object.
	 */
	public function email_admin_block( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $sent_to_admin || ! $order || '1' !== $order->get_meta( '_qic_portal' ) ) {
			return;
		}
		$rows = $this->portal_detail_rows( $order );
		if ( ! $rows ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Quick Checkout (portal) details', 'quick-instant-checkout' ) . "\n";
			foreach ( $rows as $label => $value ) {
				echo esc_html( $label ) . ': ' . wp_strip_all_tags( $value ) . "\n";
			}
			return;
		}
		echo '<h2>' . esc_html__( 'Quick Checkout (portal) details', 'quick-instant-checkout' ) . '</h2>';
		echo '<table cellspacing="0" cellpadding="6" border="0" style="width:100%;border:1px solid #eee;"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><td style="text-align:left;border:1px solid #eee;padding:6px 10px;"><strong>' . esc_html( $label ) . '</strong></td><td style="text-align:left;border:1px solid #eee;padding:6px 10px;">' . wp_kses_post( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Collect the portal detail rows (label => html value).
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private function portal_detail_rows( WC_Order $order ) {
		$rows     = array();
		$telegram = $order->get_meta( '_qic_telegram' );
		$whatsapp = $order->get_meta( '_qic_whatsapp' );
		$txid     = $order->get_meta( '_qic_txid' );
		$protocol = $order->get_meta( '_qic_protocol' );
		$proof    = (int) $order->get_meta( '_qic_proof_image' );
		$notes    = $order->get_customer_note();

		if ( '' !== $telegram ) {
			$rows[ __( 'Telegram', 'quick-instant-checkout' ) ] = esc_html( $telegram );
		}
		if ( '' !== $whatsapp ) {
			$rows[ __( 'WhatsApp', 'quick-instant-checkout' ) ] = esc_html( $whatsapp );
		}
		if ( '' !== $notes ) {
			$rows[ __( 'Customer request', 'quick-instant-checkout' ) ] = esc_html( $notes );
		}
		if ( '' !== $protocol ) {
			$rows[ __( 'Payment protocol', 'quick-instant-checkout' ) ] = esc_html( $protocol );
		}
		if ( '' !== $txid ) {
			$rows[ __( 'Transaction hash (TXID)', 'quick-instant-checkout' ) ] = '<code style="user-select:all;">' . esc_html( $txid ) . '</code>';
		}
		if ( $proof ) {
			$url = wp_get_attachment_url( $proof );
			if ( $url ) {
				$rows[ __( 'Payment screenshot', 'quick-instant-checkout' ) ] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View uploaded image', 'quick-instant-checkout' ) . '</a>';
			}
		}
		return $rows;
	}

	/**
	 * Stamp package info onto the order item (shows in admin, emails, invoices).
	 *
	 * @param WC_Order_Item_Product $item           Order item being created.
	 * @param string                $cart_item_key  Cart item key.
	 * @param array                 $values         Cart item data.
	 * @param WC_Order              $order          Order being created.
	 */
	public function copy_tier_meta( $item, $cart_item_key, $values, $order ) {
		$product = isset( $values['data'] ) ? $values['data'] : null;
		if ( ! $product instanceof WC_Product_Variation ) {
			return;
		}
		$parent = wc_get_product( $product->get_parent_id() );
		if ( ! $parent || 'yes' !== $parent->get_meta( '_qic_enabled' ) ) {
			return;
		}

		$accounts = (int) $product->get_meta( '_qic_accounts' );
		if ( $accounts > 0 ) {
			$item->add_meta_data( __( 'Accounts in package', 'quick-instant-checkout' ), $accounts );
		}

		$settings = qic_settings();
		$dispatch = $parent->get_meta( '_qic_dispatch' );
		$warranty = $parent->get_meta( '_qic_warranty' );
		$item->add_meta_data( __( 'Automated Dispatch', 'quick-instant-checkout' ), '' !== $dispatch ? $dispatch : $settings['dispatch_label'] );
		$item->add_meta_data( __( 'Replacement Warranty', 'quick-instant-checkout' ), '' !== $warranty ? $warranty : $settings['warranty_label'] );
	}
}
