<?php
/**
 * Order display and WooCommerce privacy lifecycle.
 *
 * @package DigicyfrCompanyCheckoutFieldsPL
 */

defined( 'ABSPATH' ) || exit;

/** Display historical values exactly once on supported Woo surfaces. */
final class DCCF_Display {
	/**
	 * Order IDs whose customer-details hook ran in this request.
	 *
	 * @var array<int, bool>
	 */
	private static array $customer_details_seen = array();

	/** Install only Woo-native hooks, with no custom public endpoint. */
	public static function hooks(): void {
		add_filter( 'woocommerce_admin_shipping_fields', array( __CLASS__, 'remove_native_admin_editors' ), 20 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_details' ) );
		add_action( 'woocommerce_order_details_after_customer_details', array( __CLASS__, 'customer_fallback' ), 20 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'receipt_fallback' ), 20 );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'receipt_fallback' ), 20 );
		add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'email_fallback' ), 20, 3 );
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( __CLASS__, 'privacy_export' ), 10, 2 );
		add_action( 'woocommerce_privacy_before_remove_order_personal_data', array( __CLASS__, 'privacy_erase' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_text' ) );
	}

	/**
	 * Inspect Woo's registered-field reader for this specific order.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function native_fields( WC_Order $order ): array {
		if ( ! DCCF_Data::enabled() ) {
			return array();
		}
		$package = 'Automattic\\WooCommerce\\Blocks\\Package';
		$service = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';
		if ( ! class_exists( $package ) || ! class_exists( $service ) ) {
			return array();
		}
		$helper = $package::container()->get( $service );
		$fields = $helper->get_order_additional_fields_with_values( $order, 'order', 'other', 'view' );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Woo 8.9 may add editable entries; replace only our two with read-only details.
	 *
	 * @param array $fields Woo admin fields.
	 */
	public static function remove_native_admin_editors( array $fields ): array {
		unset( $fields[ DCCF_Data::INTENT_ID ], $fields[ DCCF_Data::NIP_ID ] );
		return $fields;
	}

	/**
	 * Values include explicit intent "0" for personal orders.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $include_intent Whether to include intent.
	 * @param bool     $include_nip Whether to include NIP.
	 */
	private static function labelled( WC_Order $order, bool $include_intent = true, bool $include_nip = true ): array {
		$values = DCCF_Data::values( $order );
		$rows   = array();
		if ( $include_intent && '' !== $values['intent'] ) {
			$rows[] = array(
				'label' => __( 'Company intent', 'digicyfr-company-checkout-fields-pl' ),
				'value' => '1' === $values['intent'] ? __( 'Yes', 'digicyfr-company-checkout-fields-pl' ) : __( 'No', 'digicyfr-company-checkout-fields-pl' ),
			);
		}
		if ( $include_nip && '' !== $values['nip'] ) {
			$rows[] = array(
				'label' => __( 'Polish NIP', 'digicyfr-company-checkout-fields-pl' ),
				'value' => $values['nip'],
			);
		}
		return $rows;
	}

	/**
	 * Shared escaped formatter for admin and order details.
	 *
	 * @param array $rows Labelled values.
	 */
	private static function html( array $rows ): string {
		if ( array() === $rows ) {
			return '';
		}
		$html = '<section class="dccf-order-details"><h3>' . esc_html__( 'Company details', 'digicyfr-company-checkout-fields-pl' ) . '</h3><dl>';
		foreach ( $rows as $row ) {
			$html .= '<dt>' . esc_html( $row['label'] ) . '</dt><dd>' . esc_html( $row['value'] ) . '</dd>';
		}
		return $html . '</dl></section>';
	}

	/**
	 * One read-only staff display, regardless of Woo version or capture switch.
	 *
	 * @param mixed $order Order object.
	 */
	public static function admin_details( mixed $order ): void {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			return;
		}
		echo self::html( self::labelled( $order ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every label/value is escaped in html().
	}

	/**
	 * Woo 8.9 and 11.2 differ for classic orders; inspect native output per order.
	 *
	 * @param mixed $order Order object.
	 */
	public static function customer_fallback( mixed $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		self::$customer_details_seen[ $order->get_id() ] = true;
		$native = self::native_fields( $order );
		$rows   = self::labelled( $order, ! isset( $native[ DCCF_Data::INTENT_ID ] ), ! isset( $native[ DCCF_Data::NIP_ID ] ) );
		echo self::html( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every label/value is escaped in html().
	}

	/**
	 * Guest shortcode receipts can omit Woo's customer-details template. The
	 * confirmation Block fires this action without Woo's core order-table hook;
	 * its registered fields are rendered by the Additional Fields block.
	 *
	 * @param int $order_id Woo order ID.
	 */
	public static function receipt_fallback( int $order_id ): void {
		if ( isset( self::$customer_details_seen[ $order_id ] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( 'woocommerce_thankyou' === current_filter() ) {
			$key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Woo receipt order key is the access token and wc_clean() sanitizes it.
			if ( '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
				return;
			}
		} elseif ( 'woocommerce_view_order' === current_filter() && ( 0 === get_current_user_id() || $order->get_user_id() !== get_current_user_id() ) ) {
			return;
		}
		$is_block_confirmation = 'woocommerce_thankyou' === current_filter() && false === has_action( 'woocommerce_thankyou', 'woocommerce_order_details_table' );
		$native                = $is_block_confirmation ? self::native_fields( $order ) : array();
		$rows                  = self::labelled( $order, ! isset( $native[ DCCF_Data::INTENT_ID ] ), ! isset( $native[ DCCF_Data::NIP_ID ] ) );
		echo self::html( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every label/value is escaped in html().
	}

	/**
	 * Escape fallback values; Woo's email renderer handles HTML/plain text layout.
	 *
	 * @param array $fields Woo email fields.
	 * @param bool  $sent_to_admin Whether email is for staff.
	 * @param mixed $order Order object.
	 */
	public static function email_fallback( array $fields, bool $sent_to_admin, mixed $order ): array {
		if ( ! $order instanceof WC_Order ) {
			return $fields;
		}
		$native = self::native_fields( $order );
		foreach ( self::labelled( $order, ! isset( $native[ DCCF_Data::INTENT_ID ] ), ! isset( $native[ DCCF_Data::NIP_ID ] ) ) as $row ) {
			$key = 'dccf_' . sanitize_key( $row['label'] );
			// Woo 8.9's email meta renderer prints these fields without escaping.
			$fields[ $key ] = array(
				'label' => esc_html( $row['label'] ),
				'value' => esc_html( $row['value'] ),
			);
		}
		return $fields;
	}

	/**
	 * Include explicit "0" rather than using Woo's falsey meta-value exporter.
	 *
	 * @param array $personal_data Woo personal data pairs.
	 * @param mixed $order Order object.
	 */
	public static function privacy_export( array $personal_data, mixed $order ): array {
		if ( ! $order instanceof WC_Order ) {
			return $personal_data;
		}
		foreach ( self::labelled( $order ) as $row ) {
			$personal_data[] = array(
				'name'  => $row['label'],
				'value' => $row['value'],
			);
		}
		return $personal_data;
	}

	/**
	 * WooCommerce saves the order after this action; no second save is needed.
	 *
	 * @param mixed $order Order object.
	 */
	public static function privacy_erase( mixed $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$order->delete_meta_data( DCCF_Data::meta_key( DCCF_Data::INTENT_ID ) );
		$order->delete_meta_data( DCCF_Data::meta_key( DCCF_Data::NIP_ID ) );
	}

	/** Merchant-facing privacy policy suggested text. */
	public static function privacy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			__( 'Digicyfr Company Checkout Fields PL', 'digicyfr-company-checkout-fields-pl' ),
			__( 'If enabled, checkout asks whether an order is for a company and optionally collects a Polish NIP. These values are saved on the order, shown to authorized staff and on normal WooCommerce order confirmations and emails, and retained under the store’s order retention policy. A NIP is checked locally for format and check digit only; no registry lookup or external request occurs. WooCommerce order privacy export and erasure include these values when configured. Disabling or uninstalling this plugin does not erase existing order values; the store controls its order records and privacy requests.', 'digicyfr-company-checkout-fields-pl' )
		);
	}
}
