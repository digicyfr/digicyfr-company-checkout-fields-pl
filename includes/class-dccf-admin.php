<?php
/**
 * Merchant setting.
 *
 * @package DigicyfrCompanyCheckoutFieldsPL
 */

defined( 'ABSPATH' ) || exit;

/** Single opt-out setting for this plugin's capture. */
final class DCCF_Admin {
	/** Hooks. */
	public static function hooks(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_dccf_save_capture', array( __CLASS__, 'save' ) );
	}

	/** WooCommerce submenu. */
	public static function menu(): void {
		// WooCommerce registers manage_woocommerce for its merchant roles.
		add_submenu_page( // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			'woocommerce',
			__( 'Company checkout fields', 'digicyfr-company-checkout-fields-pl' ),
			__( 'Company checkout fields', 'digicyfr-company-checkout-fields-pl' ),
			'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			'dccf-settings',
			array( __CLASS__, 'screen' )
		);
	}

	/** Render labelled, nonce-protected setting. */
	public static function screen(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			wp_die( esc_html__( 'You cannot manage company checkout capture.', 'digicyfr-company-checkout-fields-pl' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Company checkout fields', 'digicyfr-company-checkout-fields-pl' ) . '</h1>';
		if ( isset( $_GET['dccf_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['dccf_saved'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect-only notice, no data change.
			echo '<div class="notice notice-success" role="status"><p>' . esc_html__( 'Capture setting saved.', 'digicyfr-company-checkout-fields-pl' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Collect company intent and an optional Polish NIP at classic and block checkout. The native WooCommerce company name field is unchanged. The NIP check covers format and check digit only; it does not confirm a business, issue invoices, or affect taxes.', 'digicyfr-company-checkout-fields-pl' ) . '</p>';
		echo '<p>' . esc_html__( 'If another plugin already adds a NIP field, disable this capture to avoid duplicate shopper controls. Historical order values remain visible and subject to WooCommerce privacy handling.', 'digicyfr-company-checkout-fields-pl' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dccf_save_capture">';
		wp_nonce_field( 'dccf_save_capture' );
		echo '<label><input type="checkbox" name="dccf_capture_enabled" value="1" ' . checked( DCCF_Data::enabled(), true, false ) . '> ' . esc_html__( 'Enable Digicyfr checkout capture', 'digicyfr-company-checkout-fields-pl' ) . '</label>';
		submit_button( __( 'Save setting', 'digicyfr-company-checkout-fields-pl' ) );
		echo '</form></div>';
	}

	/** Save only with POST, merchant capability and a valid nonce. */
	public static function save(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			wp_die( esc_html__( 'You cannot manage company checkout capture.', 'digicyfr-company-checkout-fields-pl' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'dccf_save_capture' );
		$submitted = isset( $_POST['dccf_capture_enabled'] ) ? wp_unslash( $_POST['dccf_capture_enabled'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact allowlist below.
		if ( ! in_array( $submitted, array( '', '1' ), true ) ) {
			wp_die( esc_html__( 'Invalid capture setting.', 'digicyfr-company-checkout-fields-pl' ), '', array( 'response' => 400 ) );
		}
		$target = '1' === $submitted ? '1' : '0';
		update_option( DCCF_Data::OPTION, $target, false );
		if ( (string) get_option( DCCF_Data::OPTION, '1' ) !== $target ) {
			wp_die( esc_html__( 'Could not save the capture setting.', 'digicyfr-company-checkout-fields-pl' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dccf-settings&dccf_saved=1' ) );
		exit;
	}
}
