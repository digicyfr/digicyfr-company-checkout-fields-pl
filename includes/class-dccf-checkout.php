<?php
/**
 * Classic and Checkout Block capture.
 *
 * @package DigicyfrCompanyCheckoutFieldsPL
 */

defined( 'ABSPATH' ) || exit;

/** Adds two optional controls without altering WooCommerce's billing fields. */
final class DCCF_Checkout {
	/** Register both checkout lifecycles. */
	public static function hooks(): void {
		add_action( 'woocommerce_init', array( __CLASS__, 'register_block' ) );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'classic_fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic_validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'classic_write' ), 10, 2 );
		add_action( 'woocommerce_blocks_validate_location_order_fields', array( __CLASS__, 'block_validate_pair' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'block_write' ), 20, 2 );
	}

	/** Register order-only Additional Checkout Fields when capture is enabled. */
	public static function register_block(): void {
		if ( ! DCCF_Data::enabled() || ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => DCCF_Data::INTENT_ID,
				'label'    => __( 'I am ordering for a company', 'digicyfr-company-checkout-fields-pl' ),
				'location' => 'order',
				'type'     => 'checkbox',
				'required' => false,
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => DCCF_Data::NIP_ID,
				'label'             => __( 'Polish NIP', 'digicyfr-company-checkout-fields-pl' ),
				'optionalLabel'     => __( 'Polish NIP (optional)', 'digicyfr-company-checkout-fields-pl' ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => false,
				'attributes'        => array( 'autocomplete' => 'off' ),
				'sanitize_callback' => array( __CLASS__, 'block_sanitize_nip' ),
				'validate_callback' => array( __CLASS__, 'block_validate_nip' ),
			)
		);
	}

	/**
	 * Normalize only a valid NIP. Keep invalid content for the validation error.
	 *
	 * @param mixed $value Shopper-provided NIP.
	 */
	public static function block_sanitize_nip( mixed $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return 'invalid-nip';
		}
		$valid = DCCF_Data::nip( $value );
		return is_wp_error( $valid ) ? (string) $value : $valid;
	}

	/**
	 * Per-field format/checksum validation for the Store API.
	 *
	 * @param mixed $value Shopper-provided NIP.
	 */
	public static function block_validate_nip( mixed $value ): WP_Error|null {
		$result = DCCF_Data::nip( $value );
		return is_wp_error( $result ) ? $result : null;
	}

	/**
	 * Store API cross-field validation; the suffix is location "order".
	 *
	 * @param WP_Error $errors Woo validation errors.
	 * @param mixed    $fields Order-location values.
	 * @param string   $group Woo storage group.
	 */
	public static function block_validate_pair( WP_Error $errors, mixed $fields, string $group ): void {
		if ( ! DCCF_Data::enabled() || 'other' !== $group || ! is_array( $fields ) ) {
			return;
		}
		$intent = $fields[ DCCF_Data::INTENT_ID ] ?? false;
		$nip    = $fields[ DCCF_Data::NIP_ID ] ?? '';
		$result = DCCF_Data::pair( $intent, $nip );
		if ( is_wp_error( $result ) && 'digicyfr_intent_required' === $result->get_error_code() ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}
	}

	/**
	 * Add controls only to Woo's classic order fieldset.
	 *
	 * @param array $fields Woo classic checkout fields.
	 */
	public static function classic_fields( array $fields ): array {
		if ( ! DCCF_Data::enabled() ) {
			return $fields;
		}
		$fields['order']['digicyfr_company_intent'] = array(
			'type'     => 'checkbox',
			'label'    => __( 'I am ordering for a company', 'digicyfr-company-checkout-fields-pl' ),
			'required' => false,
			'priority' => 110,
		);
		$fields['order']['digicyfr_company_nip']    = array(
			'type'              => 'text',
			'label'             => __( 'Polish NIP', 'digicyfr-company-checkout-fields-pl' ),
			'required'          => false,
			'priority'          => 120,
			'custom_attributes' => array(
				'inputmode'    => 'numeric',
				'autocomplete' => 'off',
			),
		);
		return $fields;
	}

	/**
	 * Validate Woo-posted classic checkout data.
	 *
	 * @param array    $data Woo-posted checkout data.
	 * @param WP_Error $errors Woo checkout errors.
	 */
	public static function classic_validate( array $data, WP_Error $errors ): void {
		if ( ! DCCF_Data::enabled() ) {
			return;
		}
		$result = DCCF_Data::pair( $data['digicyfr_company_intent'] ?? '', $data['digicyfr_company_nip'] ?? '' );
		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}
	}

	/**
	 * WooCommerce saves this order after the hook; do not call save here.
	 *
	 * @param WC_Order $order Unsaved order.
	 * @param array    $data Woo-posted checkout data.
	 */
	public static function classic_write( WC_Order $order, array $data ): void {
		if ( ! DCCF_Data::enabled() ) {
			return;
		}
		$result = DCCF_Data::pair( $data['digicyfr_company_intent'] ?? '', $data['digicyfr_company_nip'] ?? '' );
		if ( ! is_wp_error( $result ) ) {
			DCCF_Data::write( $order, $result );
		}
	}

	/**
	 * Persist both explicit values after Woo's Additional Checkout Fields processing.
	 * Woo saves the order after this hook in both supported versions. Deriving the
	 * current request also clears a stale checked value when a draft is retried.
	 *
	 * @param WC_Order        $order Unsaved Store API order.
	 * @param WP_REST_Request $request Current checkout request.
	 */
	public static function block_write( WC_Order $order, WP_REST_Request $request ): void {
		if ( ! DCCF_Data::enabled() || 'POST' !== $request->get_method() ) {
			return;
		}
		$fields = $request->get_param( 'additional_fields' );
		$fields = is_array( $fields ) ? $fields : array();
		$pair   = DCCF_Data::pair( $fields[ DCCF_Data::INTENT_ID ] ?? false, $fields[ DCCF_Data::NIP_ID ] ?? '' );
		if ( ! is_wp_error( $pair ) ) {
			DCCF_Data::write( $order, $pair );
		}
	}
}
