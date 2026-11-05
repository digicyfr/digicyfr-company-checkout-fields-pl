<?php
/**
 * Plugin Name: Digicyfr Company Checkout Fields PL
 * Description: Optional company intent and locally checked Polish NIP for WooCommerce checkout.
 * Version: 0.1.0
 * Requires at least: 7.1
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * WC requires at least: 8.9.0
 * WC tested up to: 11.2.2
 * Author: Digicyfr
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: digicyfr-company-checkout-fields-pl
 * Domain Path: /languages
 * Update URI: https://digicyfr.com/plugins/digicyfr-company-checkout-fields-pl/
 *
 * @package DigicyfrCompanyCheckoutFieldsPL
 */

defined( 'ABSPATH' ) || exit;

define( 'DCCF_VERSION', '0.1.0' );
define( 'DCCF_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-dccf-data.php';
require_once __DIR__ . '/includes/class-dccf-checkout.php';
require_once __DIR__ . '/includes/class-dccf-display.php';
require_once __DIR__ . '/includes/class-dccf-admin.php';

add_action(
	'init',
	static function (): void {
		// The manual self-hosted ZIP bundles its own Polish catalog.
		load_plugin_textdomain( 'digicyfr-company-checkout-fields-pl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	0
);

add_action(
	'before_woocommerce_init',
	static function (): void {
		$features = '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil';
		if ( class_exists( $features ) ) {
			$features::declare_compatibility( 'custom_order_tables', __FILE__, true );
			$features::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '8.9.0', '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-warning"><p>' . esc_html__( 'Digicyfr Company Checkout Fields PL requires active WooCommerce 8.9.0 or newer. Checkout capture is unavailable.', 'digicyfr-company-checkout-fields-pl' ) . '</p></div>';
					}
				}
			);
			return;
		}

		DCCF_Admin::hooks();
		DCCF_Checkout::hooks();
		DCCF_Display::hooks();
	},
	20
);
