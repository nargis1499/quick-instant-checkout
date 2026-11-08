<?php
/**
 * Uninstall cleanup.
 *
 * @package quick-instant-checkout
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'qic_settings' );
delete_option( 'qic_protocols' );
