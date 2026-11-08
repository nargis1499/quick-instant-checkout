<?php
/**
 * Quick Instant Checkout widget template.
 *
 * Available vars: $product (WC_Product), $tiers (array), $dispatch (string),
 * $warranty (string), $settings (array).
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

$default = null;
foreach ( $tiers as $tier ) {
	if ( $tier['is_default'] ) {
		$default = $tier;
		break;
	}
}
if ( ! $default ) {
	$default = $tiers[0];
}

$qic_symbol   = get_woocommerce_currency_symbol();
$qic_code     = get_woocommerce_currency();
$qic_total_tx = sprintf( '%s%s %s', $qic_symbol, number_format_i18n( $default['price'], 2 ), $qic_code );
?>
<div class="qic-widget"
	id="qic-widget-<?php echo esc_attr( $product->get_id() ); ?>"
	data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
	data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
	style="--qic-accent:<?php echo esc_attr( qic_accent_color() ); ?>;">
	<div class="qic-card">

		<div class="qic-head">
			<h3 class="qic-title"><?php esc_html_e( 'Quick Instant Checkout', 'quick-instant-checkout' ); ?></h3>
			<span class="qic-ssl">
				<svg width="12" height="14" viewBox="0 0 12 14" fill="none" aria-hidden="true"><path d="M2.5 6V4.5a3.5 3.5 0 1 1 7 0V6" stroke="currentColor" stroke-width="1.6"/><rect x="1" y="6" width="10" height="7" rx="1.8" fill="currentColor"/></svg>
				<?php esc_html_e( '256-BIT SSL', 'quick-instant-checkout' ); ?>
			</span>
		</div>

		<div class="qic-label"><?php esc_html_e( 'Select Package Quantity:', 'quick-instant-checkout' ); ?></div>

		<div class="qic-tiers" role="radiogroup" aria-label="<?php esc_attr_e( 'Package quantity', 'quick-instant-checkout' ); ?>">
			<?php foreach ( $tiers as $tier ) : ?>
				<label class="qic-tier<?php echo $tier['is_default'] ? ' is-selected' : ''; echo $tier['in_stock'] ? '' : ' is-disabled'; ?>">
					<input type="radio"
						class="qic-tier-radio"
						name="qic_tier_<?php echo esc_attr( $product->get_id() ); ?>"
						value="<?php echo esc_attr( $tier['id'] ); ?>"
						<?php checked( $tier['is_default'] ); ?>
						<?php disabled( ! $tier['in_stock'] ); ?>
						data-label="<?php echo esc_attr( $tier['label'] ); ?>"
						data-accounts="<?php echo esc_attr( $tier['accounts'] ); ?>"
						data-price="<?php echo esc_attr( $tier['price'] ); ?>"
						data-attributes='<?php echo wp_json_encode( $tier['attributes'] ); ?>' />
					<span class="qic-radio" aria-hidden="true"></span>
					<span class="qic-tier-info">
						<strong><?php echo esc_html( $tier['title'] ); ?></strong>
						<small><?php echo wp_kses_post( wc_price( $tier['per_account'] ) ); ?> <?php esc_html_e( 'per account', 'quick-instant-checkout' ); ?></small>
					</span>
						<span class="qic-tier-right">
							<span class="qic-price"><?php if ( $tier['save'] > 0 && $tier['regular'] > $tier['price'] ) : ?><s class="qic-reg"><?php echo wp_kses_post( wc_price( $tier['regular'] ) ); ?></s><?php endif; ?><?php echo wp_kses_post( wc_price( $tier['price'] ) ); ?></span>
						<?php if ( '' !== $tier['badge'] ) : ?>
							<span class="qic-badge"><?php echo esc_html( $tier['badge'] ); ?></span>
						<?php elseif ( $tier['save'] > 0 ) : ?>
							<span class="qic-badge"><?php echo esc_html( sprintf( __( 'Save %s', 'quick-instant-checkout' ), $qic_symbol . number_format_i18n( $tier['save'], 2 ) ) ); ?></span>
						<?php endif; ?>
						<?php if ( ! $tier['in_stock'] ) : ?>
							<span class="qic-badge qic-badge-soldout"><?php esc_html_e( 'Sold out', 'quick-instant-checkout' ); ?></span>
						<?php endif; ?>
					</span>
				</label>
			<?php endforeach; ?>
		</div>

		<div class="qic-summary">
			<div class="qic-row">
				<span><?php esc_html_e( 'Selected Tier:', 'quick-instant-checkout' ); ?></span>
				<strong class="qic-summary-tier"><?php echo esc_html( sprintf( __( '%1$d x %2$s', 'quick-instant-checkout' ), $default['accounts'], $product->get_name() ) ); ?></strong>
			</div>
			<div class="qic-row">
				<span><?php esc_html_e( 'Automated Dispatch:', 'quick-instant-checkout' ); ?></span>
				<strong class="qic-dispatch">&#9889; <?php echo esc_html( $dispatch ); ?></strong>
			</div>
			<div class="qic-row">
				<span><?php esc_html_e( 'Replacement Warranty:', 'quick-instant-checkout' ); ?></span>
				<strong><?php echo esc_html( $warranty ); ?></strong>
			</div>
			<div class="qic-row qic-total">
				<span><?php esc_html_e( 'Total Amount:', 'quick-instant-checkout' ); ?></span>
				<strong class="qic-summary-total"><?php echo esc_html( $qic_total_tx ); ?></strong>
			</div>
		</div>

		<button type="button" class="qic-cta" data-template="<?php echo esc_attr( $settings['button_template'] ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 8h14l-1.2 12H6.2L5 8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 8V6a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="2"/></svg>
			<span class="qic-cta-label"><?php echo esc_html( str_replace( '{price}', $qic_symbol . ' ' . number_format_i18n( $default['price'], 2 ), $settings['button_template'] ) ); ?></span>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12h15m0 0-6-6m6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</button>

		<p class="qic-note"><?php echo esc_html( $settings['note_text'] ); ?></p>
	</div>
</div>
