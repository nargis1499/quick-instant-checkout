<?php
/**
 * Order Portal modal template (multistep checkout).
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

$qic_catalog         = QIC_Portal::catalog( isset( $product ) ? $product->get_id() : 0 );
$qic_gateways        = QIC_Portal::gateways();
$qic_protocols       = QIC_Portal::protocols();
$qic_settings_portal = qic_settings();
$qic_symbol          = get_woocommerce_currency_symbol();
$qic_code            = get_woocommerce_currency();
$qic_countries       = ( WC()->countries ) ? WC()->countries->get_countries() : array();

if ( ! $qic_catalog ) {
	return;
}
if ( ! $qic_countries ) {
	$qic_countries = array(
		'US' => __( 'United States', 'quick-instant-checkout' ),
		'GB' => __( 'United Kingdom', 'quick-instant-checkout' ),
		'CA' => __( 'Canada', 'quick-instant-checkout' ),
	);
}
?>
<div class="qic-portal" data-qic-portal style="--qic-accent:<?php echo esc_attr( qic_accent_color() ); ?>;" hidden>
	<div class="qic-portal-overlay" data-qic-close></div>
	<div class="qic-portal-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Order portal', 'quick-instant-checkout' ); ?>">

		<header class="qic-portal-head">
			<div class="qic-portal-logo" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M5 8h14l-1.2 12H6.2L5 8Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><path d="M9 8V6a3 3 0 0 1 6 0v2" stroke="#fff" stroke-width="2"/></svg>
			</div>
			<div class="qic-portal-headtext">
				<h4><?php echo esc_html( get_bloginfo( 'name' ) ); ?> <?php esc_html_e( 'Order Portal', 'quick-instant-checkout' ); ?></h4>
				<p><?php echo esc_html( $qic_settings_portal['portal_subtitle'] ); ?></p>
			</div>
			<span class="qic-portal-ssl">
				<svg width="11" height="13" viewBox="0 0 12 14" fill="none" aria-hidden="true"><path d="M2.5 6V4.5a3.5 3.5 0 1 1 7 0V6" stroke="currentColor" stroke-width="1.6"/><rect x="1" y="6" width="10" height="7" rx="1.8" fill="currentColor"/></svg>
				<?php esc_html_e( 'SSL Verified', 'quick-instant-checkout' ); ?>
			</span>
			<button type="button" class="qic-portal-close" data-qic-close aria-label="<?php esc_attr_e( 'Close', 'quick-instant-checkout' ); ?>">&times;</button>
		</header>

		<nav class="qic-portal-steps" aria-label="<?php esc_attr_e( 'Checkout steps', 'quick-instant-checkout' ); ?>">
			<button type="button" class="qic-step is-active" data-step="1"><span class="qic-step-dot">1</span> <?php esc_html_e( '1. Package', 'quick-instant-checkout' ); ?></button>
			<button type="button" class="qic-step" data-step="2"><span class="qic-step-dot">2</span> <?php esc_html_e( '2. Contact', 'quick-instant-checkout' ); ?></button>
			<button type="button" class="qic-step" data-step="3"><span class="qic-step-dot">3</span> <?php esc_html_e( '3. Payment', 'quick-instant-checkout' ); ?></button>
			<button type="button" class="qic-step" data-step="4"><span class="qic-step-dot">4</span> <?php esc_html_e( '4. Verify', 'quick-instant-checkout' ); ?></button>
		</nav>

		<div class="qic-portal-body">

			<!-- STEP 1: PACKAGE -->
			<section class="qic-panel" data-panel="1">
				<div class="qic-portal-label"><?php esc_html_e( 'Select Verified Service Category', 'quick-instant-checkout' ); ?></div>
				<div class="qic-cat-grid<?php echo count( $qic_catalog ) === 1 ? ' is-single' : ''; ?>">
					<?php foreach ( $qic_catalog as $cat ) : ?>
						<button type="button" class="qic-cat" data-cat="<?php echo esc_attr( $cat['id'] ); ?>">
							<span class="qic-cat-info">
								<strong><?php echo esc_html( trim( $cat['emoji'] . ' ' . $cat['name'] ) ); ?></strong>
								<?php if ( '' !== $cat['tagline'] ) : ?>
									<small><?php echo esc_html( $cat['tagline'] ); ?></small>
								<?php endif; ?>
							</span>
							<span class="qic-cat-from"><?php esc_html_e( 'From', 'quick-instant-checkout' ); ?> <?php echo esc_html( $qic_symbol . number_format_i18n( $cat['from'], 2 ) ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="qic-tier-head">
					<div class="qic-portal-label"><?php esc_html_e( 'Select Quantity Tier', 'quick-instant-checkout' ); ?> (<span class="qic-tier-cat-name"></span>)</div>
				</div>

				<?php foreach ( $qic_catalog as $cat ) : ?>
					<div class="qic-tier-tiles" data-cat="<?php echo esc_attr( $cat['id'] ); ?>" hidden>
						<?php foreach ( $cat['tiers'] as $tier ) : ?>
							<button type="button"
								class="qic-tile<?php echo $tier['in_stock'] ? '' : ' is-disabled'; ?>"
								data-cat="<?php echo esc_attr( $cat['id'] ); ?>"
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
									<span class="qic-tile-save"><?php echo esc_html( sprintf( __( 'Save %s', 'quick-instant-checkout' ), $qic_symbol . number_format_i18n( $tier['save'], 2 ) ) ); ?></span>
								<?php endif; ?>
								<span class="qic-tile-price"><?php echo esc_html( $qic_symbol . number_format_i18n( $tier['price'], 2 ) ); ?></span>
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
				<?php endforeach; ?>

				<div class="qic-portal-summary">
					<div class="qic-ps-left">
						<span><?php esc_html_e( 'Selected Package:', 'quick-instant-checkout' ); ?></span>
						<strong class="qic-ps-package">&mdash;</strong>
						<small class="qic-ps-unit"></small>
					</div>
					<div class="qic-ps-right">
						<span><?php esc_html_e( 'Total Due:', 'quick-instant-checkout' ); ?></span>
						<strong class="qic-ps-total">&mdash;</strong>
					</div>
				</div>

				<div class="qic-portal-actions is-end">
					<button type="button" class="qic-btn-primary" data-goto="2"><?php esc_html_e( 'Continue to Contact Details', 'quick-instant-checkout' ); ?> &rarr;</button>
				</div>
			</section>

			<!-- STEP 2: CONTACT -->
			<section class="qic-panel" data-panel="2" hidden>
				<div class="qic-info-banner">
					<svg width="17" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<span><?php esc_html_e( 'Your contact details are strictly used for instant recovery spreadsheet delivery and order notifications.', 'quick-instant-checkout' ); ?></span>
				</div>
				<div class="qic-fields-grid">
					<div class="qic-field">
						<label for="qic-f-name"><?php esc_html_e( 'Full Name *', 'quick-instant-checkout' ); ?></label>
						<input type="text" id="qic-f-name" class="qic-input" placeholder="<?php esc_attr_e( 'e.g. Alexander Vance', 'quick-instant-checkout' ); ?>" autocomplete="name" />
					</div>
					<div class="qic-field">
						<label for="qic-f-email"><?php esc_html_e( 'Email Address * (for delivery)', 'quick-instant-checkout' ); ?></label>
						<input type="email" id="qic-f-email" class="qic-input" placeholder="<?php esc_attr_e( 'e.g. alex@scaleagency.io', 'quick-instant-checkout' ); ?>" autocomplete="email" />
					</div>
					<div class="qic-field">
						<label for="qic-f-telegram"><?php esc_html_e( 'Telegram Username (optional)', 'quick-instant-checkout' ); ?></label>
						<input type="text" id="qic-f-telegram" class="qic-input" placeholder="<?php esc_attr_e( 'e.g. @vance_scale', 'quick-instant-checkout' ); ?>" />
					</div>
					<div class="qic-field">
						<label for="qic-f-whatsapp"><?php esc_html_e( 'WhatsApp Number (optional)', 'quick-instant-checkout' ); ?></label>
						<input type="text" id="qic-f-whatsapp" class="qic-input" placeholder="<?php esc_attr_e( 'e.g. +1 (415) 892-3341', 'quick-instant-checkout' ); ?>" />
					</div>
					<div class="qic-field">
						<label for="qic-f-country"><?php esc_html_e( 'Country / Jurisdiction', 'quick-instant-checkout' ); ?></label>
						<select id="qic-f-country" class="qic-input">
							<?php foreach ( $qic_countries as $cc => $cname ) : ?>
								<option value="<?php echo esc_attr( $cc ); ?>" <?php selected( 'US', $cc ); ?>><?php echo esc_html( $cname ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="qic-field">
						<label for="qic-f-notes"><?php esc_html_e( 'Order Notes / Custom Requests (optional)', 'quick-instant-checkout' ); ?></label>
						<input type="text" id="qic-f-notes" class="qic-input" placeholder="<?php esc_attr_e( 'e.g. Need NY or TX state IP profiles only', 'quick-instant-checkout' ); ?>" />
					</div>
				</div>
				<div class="qic-portal-actions">
					<button type="button" class="qic-btn-ghost" data-goto="1">&larr; <?php esc_html_e( 'Back', 'quick-instant-checkout' ); ?></button>
					<button type="button" class="qic-btn-primary" data-goto="3"><?php esc_html_e( 'Select Payment Method', 'quick-instant-checkout' ); ?> &rarr;</button>
				</div>
			</section>

			<!-- STEP 3: PAYMENT -->
			<section class="qic-panel" data-panel="3" hidden>
				<div class="qic-pay-head">
					<div class="qic-portal-label"><?php esc_html_e( 'Select Crypto Wallet Protocol', 'quick-instant-checkout' ); ?></div>
					<div class="qic-pay-total"><?php esc_html_e( 'Total Due:', 'quick-instant-checkout' ); ?> <strong class="qic-pay-total-val">&mdash;</strong></div>
				</div>

				<?php if ( $qic_protocols ) : ?>
					<div class="qic-proto-grid">
						<?php foreach ( $qic_protocols as $proto ) : ?>
							<button type="button"
								class="qic-proto"
								data-code="<?php echo esc_attr( $proto['code'] ); ?>"
								data-name="<?php echo esc_attr( $proto['name'] ); ?>"
								data-address="<?php echo esc_attr( $proto['address'] ); ?>"
								data-badge="<?php echo esc_attr( $proto['badge'] ); ?>">
								<strong><?php echo esc_html( $proto['code'] ); ?></strong>
								<small><?php echo esc_html( $proto['name'] ); ?></small>
							</button>
						<?php endforeach; ?>
					</div>

					<div class="qic-deposit" hidden>
						<div class="qic-deposit-head">
							<div>
								<strong class="qic-dep-title">&mdash;</strong><span class="qic-dep-badge" hidden></span>
							</div>
							<div class="qic-dep-amount">
								<span><?php esc_html_e( 'Amount to Send:', 'quick-instant-checkout' ); ?></span>
								<strong class="qic-dep-amount-val">&mdash;</strong>
							</div>
						</div>
						<div class="qic-portal-label qic-addr-label"><?php echo esc_html( strtoupper( get_bloginfo( 'name' ) ) ); ?> <span class="qic-addr-code"></span> <?php esc_html_e( 'deposit address:', 'quick-instant-checkout' ); ?></div>
						<div class="qic-addr-row">
							<input type="text" class="qic-input qic-addr" readonly value="" />
							<button type="button" class="qic-copy-btn">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" stroke-width="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9" stroke="currentColor" stroke-width="2"/></svg>
								<?php esc_html_e( 'Copy', 'quick-instant-checkout' ); ?>
							</button>
						</div>
						<div class="qic-deposit-low">
							<div class="qic-qr-box">
								<img class="qic-qr" alt="<?php esc_attr_e( 'Scan to deposit', 'quick-instant-checkout' ); ?>" src="" />
								<span><?php esc_html_e( 'SCAN TO DEPOSIT', 'quick-instant-checkout' ); ?></span>
							</div>
							<div class="qic-scan-note">
								<strong>&#9888; <?php esc_html_e( 'LIVE SCANNER READY:', 'quick-instant-checkout' ); ?></strong>
								<p><?php esc_html_e( 'Point your crypto wallet app camera (Binance, Trust Wallet, MetaMask, Coinbase) at this QR code to automatically scan and deposit', 'quick-instant-checkout' ); ?> <b class="qic-dep-code2"></b>.</p>
								<p class="qic-scan-warn">&#9888; <?php esc_html_e( 'Always verify the first 4 and last 4 characters of the scanned address before sending funds.', 'quick-instant-checkout' ); ?></p>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $qic_gateways ) : ?>
					<div class="qic-or-divider"><span><?php esc_html_e( 'or pay with a classic method', 'quick-instant-checkout' ); ?></span></div>
					<div class="qic-gw-list">
						<?php foreach ( $qic_gateways as $gw_id => $gw ) : ?>
							<label class="qic-gw" data-crypto="0">
								<input type="radio" name="qic_gateway" value="<?php echo esc_attr( $gw_id ); ?>" />
								<span class="qic-gw-info">
									<strong><?php echo esc_html( $gw['title'] ); ?></strong>
									<?php if ( '' !== $gw['desc'] ) : ?>
										<small><?php echo esc_html( $gw['desc'] ); ?></small>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="qic-portal-actions">
					<button type="button" class="qic-btn-ghost" data-goto="2">&larr; <?php esc_html_e( 'Back', 'quick-instant-checkout' ); ?></button>
					<button type="button" class="qic-btn-primary qic-step3-continue" data-goto="4"><?php esc_html_e( 'Continue to Verification', 'quick-instant-checkout' ); ?> &rarr;</button>
				</div>
			</section>

			<!-- STEP 4: VERIFY -->
			<section class="qic-panel" data-panel="4" hidden>
				<div class="qic-banner-amber qic-crypto-only">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="flex:0 0 auto;margin-top:2px"><path d="M12 3 2 20h20L12 3Z" stroke="#f59e0b" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4m0 3v.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/></svg>
					<div>
						<strong><?php esc_html_e( 'Almost done! Submit your Transaction ID / Hash for automated verification.', 'quick-instant-checkout' ); ?></strong>
						<p><?php esc_html_e( 'Our blockchain gateway checks incoming hashes every 60 seconds. Once confirmed, your spreadsheet will be emailed automatically.', 'quick-instant-checkout' ); ?></p>
					</div>
				</div>

				<div class="qic-crypto-only">
					<div class="qic-field">
						<label for="qic-f-txid"><?php esc_html_e( 'Crypto Transaction Hash / TXID * (required)', 'quick-instant-checkout' ); ?></label>
						<input type="text" id="qic-f-txid" class="qic-input" placeholder="<?php esc_attr_e( 'e.g. 0x89f4b32a9c118e27a6d8924b1088c4b11f32a79c or TFshfiXsqxNxeTa…', 'quick-instant-checkout' ); ?>" />
					</div>
					<div class="qic-field">
						<label><?php esc_html_e( 'Upload Payment Screenshot (optional for faster verification)', 'quick-instant-checkout' ); ?></label>
						<div class="qic-dropzone" id="qic-dropzone" role="button" tabindex="0">
							<span class="qic-dz-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 16V4m0 0 4 4m-4-4-4 4" stroke="#667085" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" stroke="#667085" stroke-width="2" stroke-linecap="round"/></svg>
							</span>
							<b><?php esc_html_e( 'Click to upload image', 'quick-instant-checkout' ); ?></b>
							<small><?php esc_html_e( 'PNG, JPG, or GIF up to 5MB', 'quick-instant-checkout' ); ?></small>
							<img class="qic-dz-preview" alt="" hidden />
						</div>
						<input type="file" id="qic-f-proof" accept="image/png,image/jpeg,image/gif" hidden />
					</div>
				</div>

				<div class="qic-review">
					<div class="qic-row"><span><?php esc_html_e( 'Order Summary:', 'quick-instant-checkout' ); ?></span><strong class="qic-rv-package">&mdash;</strong></div>
					<div class="qic-row"><span><?php esc_html_e( 'Payment Protocol:', 'quick-instant-checkout' ); ?></span><strong class="qic-rv-gateway">&mdash;</strong></div>
					<div class="qic-row"><span><?php esc_html_e( 'Delivery email:', 'quick-instant-checkout' ); ?></span><strong class="qic-rv-email">&mdash;</strong></div>
					<div class="qic-row qic-total"><span><?php esc_html_e( 'Total Paid:', 'quick-instant-checkout' ); ?></span><strong class="qic-rv-total">&mdash;</strong></div>
				</div>

				<?php if ( '' !== $qic_settings_portal['terms_text'] ) : ?>
					<label class="qic-terms">
						<input type="checkbox" id="qic-f-terms" />
						<span><?php echo esc_html( $qic_settings_portal['terms_text'] ); ?></span>
					</label>
				<?php endif; ?>
				<p class="qic-portal-error" hidden></p>
				<div class="qic-portal-actions">
					<button type="button" class="qic-btn-ghost" data-goto="3">&larr; <?php esc_html_e( 'Back', 'quick-instant-checkout' ); ?></button>
					<button type="button" class="qic-btn-primary qic-place-order"><?php esc_html_e( 'Place Order', 'quick-instant-checkout' ); ?></button>
				</div>
			</section>

			<!-- STEP 5: SUCCESS -->
			<section class="qic-panel" data-panel="5" hidden>
				<div class="qic-success">
					<span class="qic-success-icon">
						<svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 13 4 4L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</span>
					<h3><?php esc_html_e( 'Congratulations! Your order is submitted.', 'quick-instant-checkout' ); ?> 🎉</h3>
					<p class="qic-success-order"></p>
					<p class="qic-success-text"></p>
					<button type="button" class="qic-btn-primary" data-qic-close><?php esc_html_e( 'Done', 'quick-instant-checkout' ); ?></button>
				</div>
			</section>

		</div>

		<!-- Processing overlay (blurred spinner while the order is created) -->
		<div class="qic-loading" hidden>
			<div class="qic-loading-box">
				<span class="qic-spinner" aria-hidden="true"></span>
				<p><?php esc_html_e( 'Processing your order…', 'quick-instant-checkout' ); ?></p>
			</div>
		</div>
	</div>
</div>
