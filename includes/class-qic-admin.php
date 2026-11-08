<?php
/**
 * Admin UI: product opt-in fields, per-variation tier fields, settings page.
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

class QIC_Admin {

	/** Singleton. */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
		add_action( 'woocommerce_variation_options_inventory', array( $this, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'settings_page' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
	}

	/**
	 * Fields on the product General tab.
	 */
	public function product_fields() {
		global $post;
		$product = wc_get_product( $post->ID );
		$settings = qic_settings();

		echo '<div class="options_group qic-options-group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_qic_enabled',
				'label'       => __( 'Enable Quick Instant Checkout widget', 'quick-instant-checkout' ),
				'description' => __( 'Replaces the default add-to-cart form with the tiered package selector (product must be a Variable product with one variation per package).', 'quick-instant-checkout' ),
				'value'       => $product ? $product->get_meta( '_qic_enabled' ) : 'no',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_qic_dispatch',
				'label'       => __( 'Dispatch label (summary row)', 'quick-instant-checkout' ),
				'description' => __( 'Leave empty to use the global default.', 'quick-instant-checkout' ),
				'placeholder' => $settings['dispatch_label'],
				'value'       => $product ? $product->get_meta( '_qic_dispatch' ) : '',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_qic_warranty',
				'label'       => __( 'Warranty label (summary row)', 'quick-instant-checkout' ),
				'description' => __( 'Leave empty to use the global default.', 'quick-instant-checkout' ),
				'placeholder' => $settings['warranty_label'],
				'value'       => $product ? $product->get_meta( '_qic_warranty' ) : '',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_qic_tagline',
				'label'       => __( 'Portal tagline (e.g. Best Seller • USA IP)', 'quick-instant-checkout' ),
				'description' => __( 'Small sub-line under the category name in the Order Portal.', 'quick-instant-checkout' ),
				'value'       => $product ? $product->get_meta( '_qic_tagline' ) : '',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_qic_emoji',
				'label'       => __( 'Portal icon / emoji (e.g. 🔥)', 'quick-instant-checkout' ),
				'value'       => $product ? $product->get_meta( '_qic_emoji' ) : '',
			)
		);
		echo '</div>';
	}

	/**
	 * Save product-level fields.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save_product_fields( $product ) {
		$product->update_meta_data( '_qic_enabled', isset( $_POST['_qic_enabled'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_qic_dispatch', isset( $_POST['_qic_dispatch'] ) ? sanitize_text_field( wp_unslash( $_POST['_qic_dispatch'] ) ) : '' );
		$product->update_meta_data( '_qic_warranty', isset( $_POST['_qic_warranty'] ) ? sanitize_text_field( wp_unslash( $_POST['_qic_warranty'] ) ) : '' );
		$product->update_meta_data( '_qic_tagline', isset( $_POST['_qic_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['_qic_tagline'] ) ) : '' );
		$product->update_meta_data( '_qic_emoji', isset( $_POST['_qic_emoji'] ) ? sanitize_text_field( wp_unslash( $_POST['_qic_emoji'] ) ) : '' );
	}

	/**
	 * Per-variation tier fields (accounts, badge, default flag, ribbon).
	 *
	 * Hardened: resolves the variation from whichever argument shape the current
	 * WooCommerce version passes, and can never fatal inside the variations
	 * AJAX render (a fatal here would blank the variation list).
	 *
	 * @param int                 $loop           Variation loop index.
	 * @param array               $variation_data Legacy variation data array.
	 * @param mixed               $variation      Variation object (or id/array in some contexts).
	 */
	public function variation_fields( $loop, $variation_data, $variation ) {
		$variation_id = 0;
		if ( $variation instanceof WC_Product ) {
			$variation_id = $variation->get_id();
		} elseif ( is_array( $variation_data ) && ! empty( $variation_data['variation_id'] ) ) {
			$variation_id = absint( $variation_data['variation_id'] );
		} elseif ( is_numeric( $variation ) ) {
			$variation_id = absint( $variation );
		}
		if ( ! $variation_id ) {
			return;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			return;
		}

		try {
			?>
			<div class="qic-variation-fields options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'            => "variable_qic_accounts{$loop}",
						'name'          => "variable_qic_accounts[{$loop}]",
						'label'         => __( 'Accounts in package', 'quick-instant-checkout' ),
						'type'          => 'number',
						'value'         => $variation->get_meta( '_qic_accounts' ),
						'wrapper_class' => 'form-row form-row-first',
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'            => "variable_qic_badge{$loop}",
						'name'          => "variable_qic_badge[{$loop}]",
						'label'         => __( 'Badge (e.g. Save 10%)', 'quick-instant-checkout' ),
						'value'         => $variation->get_meta( '_qic_badge' ),
						'wrapper_class' => 'form-row form-row-last',
					)
				);
				woocommerce_wp_checkbox(
					array(
						'id'    => "variable_qic_default{$loop}",
						'name'  => "variable_qic_default[{$loop}]",
						'label' => __( 'Default selected tier', 'quick-instant-checkout' ),
						'value' => get_post_meta( $variation_id, '_qic_default', true ),
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'    => "variable_qic_ribbon{$loop}",
						'name'  => "variable_qic_ribbon[{$loop}]",
						'label' => __( 'Ribbon (e.g. POPULAR)', 'quick-instant-checkout' ),
						'value' => $variation->get_meta( '_qic_ribbon' ),
					)
				);
				?>
			</div>
			<?php
		} catch ( \Throwable $e ) {
			// Never break the variations screen; log instead.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[QIC] variation_fields: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Save per-variation tier fields.
	 *
	 * @param int $variation_id Variation post ID.
	 * @param int $loop         Variation loop index.
	 */
	public function save_variation_fields( $variation_id, $loop ) {
		if ( isset( $_POST['variable_qic_accounts'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_qic_accounts', max( 1, absint( wp_unslash( $_POST['variable_qic_accounts'][ $loop ] ) ) ) );
		}
		if ( isset( $_POST['variable_qic_badge'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_qic_badge', sanitize_text_field( wp_unslash( $_POST['variable_qic_badge'][ $loop ] ) ) );
		}
		$is_default = isset( $_POST['variable_qic_default'][ $loop ] ) ? 'yes' : 'no';
		update_post_meta( $variation_id, '_qic_default', $is_default );
		if ( isset( $_POST['variable_qic_ribbon'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_qic_ribbon', sanitize_text_field( wp_unslash( $_POST['variable_qic_ribbon'][ $loop ] ) ) );
		}
		if ( 'yes' === $is_default ) {
			$parent_id = wp_get_post_parent_id( $variation_id );
			if ( $parent_id ) {
				update_post_meta( $parent_id, '_qic_default_tier', $variation_id );
			}
		}
	}

	/**
	 * Settings submenu under WooCommerce.
	 */
	public function settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Quick Checkout', 'quick-instant-checkout' ),
			__( 'Quick Checkout', 'quick-instant-checkout' ),
			'manage_woocommerce',
			'qic-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render settings form.
	 */
	public function render_settings() {
		$s = qic_settings();
		?>
		<div class="wrap">
		<style>
			.qic-set-wrap{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);border:1px solid #dfe5ee;border-radius:14px;padding:22px;max-width:1150px;}
			.qic-set-hero{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#0f172a 0%,#334155 100%);border-radius:12px;padding:18px 22px;margin-bottom:18px;color:#fff;}
			.qic-set-hero-ico{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:12px;background:#dc2626;color:#fff;flex:0 0 44px;box-shadow:0 6px 18px rgba(220,38,38,.35);}
			.qic-set-hero h1{margin:0;color:#fff;font-size:20px;line-height:1.3;}
			.qic-set-hero p{margin:2px 0 0;color:#cbd5e1;font-size:12.5px;}
			.qic-set-ver{margin-left:auto;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#e2e8f0;border-radius:999px;padding:4px 12px;font-size:11px;font-weight:700;}
			.qic-set-card{background:#fff;border:1px solid #e3e8f0;border-radius:12px;padding:4px 20px 12px;margin:0 0 16px;box-shadow:0 1px 2px rgba(15,23,42,.05);}
			.qic-set-h{display:flex;align-items:center;gap:10px;font-size:14.5px;color:#1e293b;margin:16px 0 2px;padding:0;}
			.qic-set-ico{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9px;flex:0 0 32px;}
			.qic-set-card .form-table th{width:230px;padding:16px 12px 16px 0;}
			.qic-set-card .form-table td{padding:16px 0;}
			#qic-proto-table th{background:#f8fafc;}
			.qic-set-wrap :focus,.qic-set-wrap :focus-visible{outline:none;}
			.qic-set-wrap input:focus,.qic-set-wrap select:focus,.qic-set-wrap textarea:focus{box-shadow:none;border-color:#8c8f94;}
			.qic-set-proto-wrap{margin:14px 0 6px;}
		</style>
		<div class="qic-set-wrap">
			<div class="qic-set-hero">
				<span class="qic-set-hero-ico"><?php echo $this->set_icon( 'bolt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h1><?php esc_html_e( 'Quick Instant Checkout Settings', 'quick-instant-checkout' ); ?></h1>
					<p><?php esc_html_e( 'Tiered widget · order portal · manual crypto verification · theming', 'quick-instant-checkout' ); ?></p>
				</div>
				<span class="qic-set-ver">v<?php echo esc_html( QIC_VERSION ); ?></span>
			</div>
			<form method="post" action="">
				<?php wp_nonce_field( 'qic_save_settings', 'qic_nonce' ); ?>

				<div class="qic-set-card">
					<h2 class="qic-set-h"><span class="qic-set-ico" style="background:#e0f2fe;color:#0284c7;"><?php echo $this->set_icon( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Widget & store text', 'quick-instant-checkout' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="qic_dispatch_label"><?php esc_html_e( 'Default dispatch label', 'quick-instant-checkout' ); ?></label></th>
							<td><input type="text" class="regular-text" id="qic_dispatch_label" name="qic_dispatch_label" value="<?php echo esc_attr( $s['dispatch_label'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="qic_warranty_label"><?php esc_html_e( 'Default warranty label', 'quick-instant-checkout' ); ?></label></th>
							<td><input type="text" class="regular-text" id="qic_warranty_label" name="qic_warranty_label" value="<?php echo esc_attr( $s['warranty_label'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="qic_button_template"><?php esc_html_e( 'Button label template', 'quick-instant-checkout' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="qic_button_template" name="qic_button_template" value="<?php echo esc_attr( $s['button_template'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Use {price} as the live price token, e.g. Proceed to Payment ({price})', 'quick-instant-checkout' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qic_note_text"><?php esc_html_e( 'Footnote under button', 'quick-instant-checkout' ); ?></label></th>
							<td><input type="text" class="large-text" id="qic_note_text" name="qic_note_text" value="<?php echo esc_attr( $s['note_text'] ); ?>" /></td>
						</tr>
					</table>
				</div>

				<div class="qic-set-card">
					<h2 class="qic-set-h"><span class="qic-set-ico" style="background:#fdf2f8;color:#db2777;"><?php echo $this->set_icon( 'palette' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Appearance', 'quick-instant-checkout' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Google Fonts', 'quick-instant-checkout' ); ?></th>
							<td>
								<label><input type="checkbox" name="qic_load_fonts" value="1" <?php checked( 'yes', $s['load_fonts'] ); ?> /> <?php esc_html_e( 'Load Plus Jakarta Sans + Inter for the widget & portal', 'quick-instant-checkout' ); ?></label>
								<p class="description"><?php esc_html_e( 'Loaded only on pages that render the widget, with preconnect + swap. Disable for a fully self-hosted/GDPR-strict setup (system fonts are used as fallback).', 'quick-instant-checkout' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qic_theme_preset"><?php esc_html_e( 'Color theme', 'quick-instant-checkout' ); ?></label></th>
							<td>
								<select id="qic_theme_preset" name="qic_theme_preset">
									<?php foreach ( qic_theme_presets() as $pid => $hex ) : ?>
										<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $s['theme_preset'], $pid ); ?>>
											<?php echo esc_html( ucfirst( $pid ) . ' (' . $hex . ')' ); ?>
										</option>
									<?php endforeach; ?>
									<option value="custom" <?php selected( $s['theme_preset'], 'custom' ); ?>><?php esc_html_e( 'Custom (color below)', 'quick-instant-checkout' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Recolors the whole plugin: widget cards, selection states, prices, portal header accents, tiles, buttons and badges.', 'quick-instant-checkout' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qic_accent_color"><?php esc_html_e( 'Custom accent color', 'quick-instant-checkout' ); ?></label></th>
							<td>
								<input type="color" id="qic_accent_color" name="qic_accent_color" value="<?php echo esc_attr( $s['accent_color'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Used only when Color theme = Custom.', 'quick-instant-checkout' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="qic-set-card">
					<h2 class="qic-set-h"><span class="qic-set-ico" style="background:#eef2ff;color:#4f46e5;"><?php echo $this->set_icon( 'portal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Portal & policy', 'quick-instant-checkout' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="qic_portal_subtitle"><?php esc_html_e( 'Portal header subtitle', 'quick-instant-checkout' ); ?></label></th>
							<td><input type="text" class="large-text" id="qic_portal_subtitle" name="qic_portal_subtitle" value="<?php echo esc_attr( $s['portal_subtitle'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="qic_terms_text"><?php esc_html_e( 'Terms checkbox text (Verify step)', 'quick-instant-checkout' ); ?></label></th>
							<td>
								<input type="text" class="large-text" id="qic_terms_text" name="qic_terms_text" value="<?php echo esc_attr( $s['terms_text'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Leave empty to hide the checkbox.', 'quick-instant-checkout' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="qic-set-card">
					<h2 class="qic-set-h"><span class="qic-set-ico" style="background:#fdf3e3;color:#b45309;"><?php echo $this->set_icon( 'coin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Crypto payments (manual)', 'quick-instant-checkout' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="qic_crypto_ids"><?php esc_html_e( 'Crypto gateway IDs', 'quick-instant-checkout' ); ?></label></th>
							<td>
								<input type="text" class="large-text" id="qic_crypto_ids" name="qic_crypto_ids" value="<?php echo esc_attr( $s['crypto_ids'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Comma-separated WooCommerce gateway IDs counted as crypto payments. Titles containing crypto/BTC/USDT/etc. are auto-detected too.', 'quick-instant-checkout' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qic_crypto_order_status"><?php esc_html_e( 'Crypto order status on submit', 'quick-instant-checkout' ); ?></label></th>
							<td>
								<select id="qic_crypto_order_status" name="qic_crypto_order_status">
									<option value="on-hold" <?php selected( $s['crypto_order_status'], 'on-hold' ); ?>><?php esc_html_e( 'On hold (awaiting verification) — recommended', 'quick-instant-checkout' ); ?></option>
									<option value="processing" <?php selected( $s['crypto_order_status'], 'processing' ); ?>><?php esc_html_e( 'Processing (you confirmed the payment manually)', 'quick-instant-checkout' ); ?></option>
									<option value="pending" <?php selected( $s['crypto_order_status'], 'pending' ); ?>><?php esc_html_e( 'Pending payment', 'quick-instant-checkout' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="qic-set-card">
					<h2 class="qic-set-h"><span class="qic-set-ico" style="background:#e8f9ee;color:#1fa855;"><?php echo $this->set_icon( 'wallet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Crypto Wallet Protocols (payment step)', 'quick-instant-checkout' ); ?></h2>
					<div class="qic-set-proto-wrap">
					<table class="widefat" id="qic-proto-table">
						<thead>
							<tr>
								<th style="width:36px"><?php esc_html_e( 'On', 'quick-instant-checkout' ); ?></th>
								<th style="width:80px"><?php esc_html_e( 'Code', 'quick-instant-checkout' ); ?></th>
								<th style="width:200px"><?php esc_html_e( 'Name', 'quick-instant-checkout' ); ?></th>
								<th><?php esc_html_e( 'Deposit address', 'quick-instant-checkout' ); ?></th>
								<th style="width:150px"><?php esc_html_e( 'Badge', 'quick-instant-checkout' ); ?></th>
								<th style="width:36px"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( qic_protocols() as $i => $pr ) : ?>
								<tr>
									<td>
										<input type="hidden" name="qic_proto_enabled[<?php echo (int) $i; ?>]" value="0" />
										<input type="checkbox" name="qic_proto_enabled[<?php echo (int) $i; ?>]" value="1" <?php checked( ! empty( $pr['enabled'] ) ); ?> />
									</td>
									<td><input type="text" name="qic_proto_code[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $pr['code'] ); ?>" style="width:70px" /></td>
									<td><input type="text" name="qic_proto_name[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $pr['name'] ); ?>" style="width:100%" /></td>
									<td><input type="text" class="code" name="qic_proto_address[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $pr['address'] ); ?>" style="width:100%" placeholder="bc1q…" /></td>
									<td><input type="text" name="qic_proto_badge[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $pr['badge'] ); ?>" style="width:100%" /></td>
									<td><button type="button" class="button qic-proto-remove">&times;</button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button" id="qic-proto-add">+ <?php esc_html_e( 'Add protocol', 'quick-instant-checkout' ); ?></button></p>
					<script>
						document.addEventListener('click', function (e) {
							if (e.target.id === 'qic-proto-add') {
								var tbody = document.querySelector('#qic-proto-table tbody');
								var i = tbody.rows.length;
								var row = tbody.rows[0].cloneNode(true);
								row.querySelectorAll('input').forEach(function (inp) {
									if (inp.type === 'checkbox') { inp.checked = true; }
									else if (inp.type === 'hidden') { inp.value = '0'; }
									else { inp.value = ''; }
									inp.name = inp.name.replace(/\[\d+\]/, '[' + i + ']');
								});
								tbody.appendChild(row);
							}
							if (e.target.classList.contains('qic-proto-remove')) {
								var tb = e.target.closest('tbody');
								if (tb && tb.rows.length > 1) { e.target.closest('tr').remove(); }
							}
						});
					</script>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		</div>
		<?php
	}

	/**
	 * Inline SVG icon for the settings cards.
	 *
	 * @param string $key Icon key.
	 * @return string
	 */
	private function set_icon( $key ) {
		$svg = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
		switch ( $key ) {
			case 'bolt':
				$svg .= '<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8z"/>';
				break;
			case 'edit':
				$svg .= '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>';
				break;
			case 'palette':
				$svg .= '<path d="M12 2a10 10 0 0 0 0 20 2.5 2.5 0 0 0 2.5-2.5c0-.6-.2-1.1-.6-1.5-.4-.4-.6-.9-.6-1.5a2.5 2.5 0 0 1 2.5-2.5H18a4 4 0 0 0 4-4c0-5-4.5-8-10-8z"/><circle cx="7.5" cy="11.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="10.5" cy="7.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="7.5" r="1.1" fill="currentColor" stroke="none"/>';
				break;
			case 'portal':
				$svg .= '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><circle cx="6.5" cy="6.5" r=".7" fill="currentColor" stroke="none"/><circle cx="9" cy="6.5" r=".7" fill="currentColor" stroke="none"/>';
				break;
			case 'coin':
				$svg .= '<circle cx="12" cy="12" r="9"/><path d="M12 7v10"/><path d="M15 9.5c-.8-.8-1.9-1.2-3-1.2-1.7 0-3 .8-3 1.9s1.3 1.6 3 1.9 3 .8 3 1.9-1.3 1.9-3 1.9c-1.1 0-2.2-.4-3-1.2"/>';
				break;
			default: // wallet
				$svg .= '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>';
		}
		return $svg . '</svg>';
	}

	/**
	 * Validate + persist settings.
	 */
	public function save_settings() {
		if ( ! isset( $_POST['qic_nonce'] ) || ! check_admin_referer( 'qic_save_settings', 'qic_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$accent = sanitize_hex_color( wp_unslash( $_POST['qic_accent_color'] ?? '#4285f4' ) );
		$clean  = array(
			'dispatch_label'  => sanitize_text_field( wp_unslash( $_POST['qic_dispatch_label'] ?? '' ) ),
			'warranty_label'  => sanitize_text_field( wp_unslash( $_POST['qic_warranty_label'] ?? '' ) ),
			'button_template' => sanitize_text_field( wp_unslash( $_POST['qic_button_template'] ?? '' ) ),
			'note_text'       => sanitize_text_field( wp_unslash( $_POST['qic_note_text'] ?? '' ) ),
			'accent_color'    => $accent ? $accent : '#4285f4',
			'theme_preset'    => sanitize_key( wp_unslash( $_POST['qic_theme_preset'] ?? 'red' ) ),
			'load_fonts'      => isset( $_POST['qic_load_fonts'] ) ? 'yes' : 'no',
			'portal_subtitle' => sanitize_text_field( wp_unslash( $_POST['qic_portal_subtitle'] ?? '' ) ),
			'crypto_ids'      => sanitize_text_field( wp_unslash( $_POST['qic_crypto_ids'] ?? '' ) ),
			'terms_text'      => sanitize_text_field( wp_unslash( $_POST['qic_terms_text'] ?? '' ) ),
			'crypto_order_status' => in_array( wp_unslash( $_POST['qic_crypto_order_status'] ?? 'on-hold' ), array( 'on-hold', 'processing', 'pending' ), true ) ? wp_unslash( $_POST['qic_crypto_order_status'] ) : 'on-hold',
		);
		if ( '' === $clean['button_template'] ) {
			$clean['button_template'] = 'Proceed to Payment ({price})';
		}
		update_option( 'qic_settings', $clean );

		// Crypto protocols repeater.
		$codes    = isset( $_POST['qic_proto_code'] ) ? (array) wp_unslash( $_POST['qic_proto_code'] ) : array();
		$protos   = array();
		foreach ( $codes as $idx => $code ) {
			$code = sanitize_text_field( $code );
			if ( '' === $code ) {
				continue;
			}
			$protos[] = array(
				'enabled' => (int) ( isset( $_POST['qic_proto_enabled'][ $idx ] ) ? $_POST['qic_proto_enabled'][ $idx ] : 0 ),
				'code'    => $code,
				'name'    => sanitize_text_field( wp_unslash( $_POST['qic_proto_name'][ $idx ] ?? '' ) ),
				'address' => sanitize_text_field( wp_unslash( $_POST['qic_proto_address'][ $idx ] ?? '' ) ),
				'badge'   => sanitize_text_field( wp_unslash( $_POST['qic_proto_badge'][ $idx ] ?? '' ) ),
			);
		}
		update_option( 'qic_protocols', $protos );
	}
}
