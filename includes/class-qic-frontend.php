<?php
/**
 * Frontend: shortcode, product-page injection, assets, widget rendering.
 *
 * @package quick-instant-checkout
 */

defined( 'ABSPATH' ) || exit;

class QIC_Frontend {

	/** Singleton. */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'qic_checkout', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'maybe_render' ), 29 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'wp_resource_hints', array( $this, 'font_preconnect' ), 10, 2 );
	}

	/**
	 * Preconnect to the Google Fonts origins (only meaningful when fonts load).
	 *
	 * @param array  $urls     URLs.
	 * @param string $relation Relation type.
	 * @return array
	 */
	public function font_preconnect( $urls, $relation ) {
		if ( 'preconnect' === $relation && 'yes' === qic_settings()['load_fonts'] ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		}
		return $urls;
	}

	/**
	 * Enqueue on single product pages where the widget is active.
	 */
	public function enqueue() {
		if ( is_product() && QIC_Tiers::is_enabled( wc_get_product( get_the_ID() ) ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Enqueue widget CSS/JS + localized config (idempotent).
	 */
	public static function enqueue_assets() {
		if ( wp_script_is( 'qic-script', 'enqueued' ) ) {
			return;
		}
		$settings = qic_settings();
		if ( 'yes' === $settings['load_fonts'] ) {
			wp_enqueue_style(
				'qic-google-fonts',
				'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
				array(),
				null
			);
		}
		wp_enqueue_style( 'qic-style', QIC_URL . 'assets/css/qic.css', array(), QIC_VERSION );
		wp_enqueue_style( 'qic-portal-style', QIC_URL . 'assets/css/qic-portal.css', array(), QIC_VERSION );
		wp_enqueue_script( 'qic-script', QIC_URL . 'assets/js/qic.js', array(), QIC_VERSION, true );
		wp_enqueue_script( 'qic-portal-script', QIC_URL . 'assets/js/qic-portal.js', array(), QIC_VERSION, true );
		wp_localize_script(
			'qic-portal-script',
			'qicPortalData',
			array(
				'nonceUrl'       => add_query_arg( 'wc-ajax', 'qic_nonce', home_url( '/' ) ),
				'placeUrl'       => add_query_arg( 'wc-ajax', 'qic_place_order', home_url( '/' ) ),
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'currencyCode'   => get_woocommerce_currency(),
				'accountWord'    => __( 'account', 'quick-instant-checkout' ),
				'cryptoPct'      => 10,
				'qrTemplate'     => qic_qr_template(),
				'i18n'           => array(
					'invalidEmail'  => __( 'Please enter a valid email address.', 'quick-instant-checkout' ),
					'requiredName'  => __( 'Please enter your full name.', 'quick-instant-checkout' ),
					'selectGateway' => __( 'Please select a payment method or crypto protocol.', 'quick-instant-checkout' ),
					'acceptTerms'   => __( 'Please accept the terms to continue.', 'quick-instant-checkout' ),
					'invalidTxid'   => __( 'Please paste your crypto transaction hash / TXID.', 'quick-instant-checkout' ),
					'fileBadType'   => __( 'Only PNG, JPG or GIF screenshots are allowed.', 'quick-instant-checkout' ),
					'fileTooBig'    => __( 'The screenshot must be 5MB or smaller.', 'quick-instant-checkout' ),
					'placeOrder'    => __( 'Place Order', 'quick-instant-checkout' ),
					'submitOrder'   => __( 'Submit Order & Get Accounts', 'quick-instant-checkout' ),
					'sentPayment'   => __( 'I Have Sent Payment', 'quick-instant-checkout' ),
					'uploadHash'    => __( 'Upload Hash', 'quick-instant-checkout' ),
					'continueVerify' => __( 'Continue to Verification', 'quick-instant-checkout' ),
					'uploadCta'     => __( 'Click to upload image', 'quick-instant-checkout' ),
					'orderReceived' => __( 'Order {number} has been submitted successfully.', 'quick-instant-checkout' ),
					'verifyNote'    => __( 'We have received your transaction hash. Our verification gateway is checking the blockchain now — once confirmed, your accounts spreadsheet will be emailed to {email} automatically.', 'quick-instant-checkout' ),
					'genericError'  => __( 'Something went wrong. Please try again.', 'quick-instant-checkout' ),
				),
			)
		);
		wp_localize_script(
			'qic-script',
			'qicData',
			array(
				'addToCartUrl'   => add_query_arg( 'wc-ajax', 'qic_add_to_cart', home_url( '/' ) ),
				'checkoutUrl'    => wc_get_checkout_url(),
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'currencyCode'   => get_woocommerce_currency(),
				'i18n'           => array(
					'processing' => __( 'Redirecting to checkout…', 'quick-instant-checkout' ),
					'error'      => __( 'Could not add this package to the cart. Please try again.', 'quick-instant-checkout' ),
					'console'    => __( '(Open the browser console → Network tab → qic_add_to_cart for technical details.)', 'quick-instant-checkout' ),
				),
			)
		);
	}

	/**
	 * Render the widget above the (hidden) native add-to-cart form.
	 */
	public function maybe_render() {
		$product = wc_get_product( get_the_ID() );
		if ( QIC_Tiers::is_enabled( $product ) ) {
			self::render_widget( $product );
		}
	}

	/**
	 * Shortcode: [qic_checkout id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'qic_checkout' );
		$product = wc_get_product( (int) $atts['id'] );
		if ( ! QIC_Tiers::is_enabled( $product ) ) {
			return '';
		}
		self::enqueue_assets();
		ob_start();
		self::render_widget( $product );
		return (string) ob_get_clean();
	}

	/**
	 * Output the widget markup.
	 *
	 * @param WC_Product $product Product to render tiers for.
	 */
	public static function render_widget( WC_Product $product ) {
		$tiers = QIC_Tiers::get_tiers( $product );
		if ( ! $tiers ) {
			return;
		}
		$settings = qic_settings();
		$dispatch = $product->get_meta( '_qic_dispatch' );
		$dispatch = '' !== $dispatch ? $dispatch : $settings['dispatch_label'];
		$warranty = $product->get_meta( '_qic_warranty' );
		$warranty = '' !== $warranty ? $warranty : $settings['warranty_label'];

		include QIC_PATH . 'templates/qic-widget.php';

		// Print the Order Portal modal once per page.
		if ( ! QIC_Portal::has_rendered() ) {
			QIC_Portal::mark_rendered();
			include QIC_PATH . 'templates/qic-portal.php';
		}
	}

	/**
	 * Body class used to hide the native add-to-cart form via CSS.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		if ( is_product() && QIC_Tiers::is_enabled( wc_get_product( get_the_ID() ) ) ) {
			$classes[] = 'qic-active';
		}
		return $classes;
	}
}
