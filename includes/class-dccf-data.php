<?php
/**
 * Shared checkout values, validation, and order CRUD.
 *
 * @package DigicyfrCompanyCheckoutFieldsPL
 */

defined( 'ABSPATH' ) || exit;

/** Canonical data contract for both checkout implementations. */
final class DCCF_Data {
	public const INTENT_ID = 'digicyfr-company/intent';
	public const NIP_ID    = 'digicyfr-company/nip';
	public const OPTION    = 'dccf_capture_enabled';

	/** Capture defaults on; historic order data survives disabling it. */
	public static function enabled(): bool {
		return '0' !== (string) get_option( self::OPTION, '1' );
	}

	/**
	 * Prefix from Woo's Additional Checkout Fields contract.
	 *
	 * @param string $id Field ID.
	 */
	public static function meta_key( string $id ): string {
		$service = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';
		$prefix  = class_exists( $service ) ? $service::OTHER_FIELDS_PREFIX : '_wc_other/';
		return $prefix . $id;
	}

	/**
	 * The checkbox is deliberately unchecked unless the shopper selects it.
	 *
	 * @param mixed $value Checkbox value.
	 */
	public static function intent( mixed $value ): string {
		return in_array( $value, array( true, 1, '1', 'on', 'yes' ), true ) ? '1' : '0';
	}

	/**
	 * Validate format/check digit only; no tax registry is queried.
	 *
	 * @param mixed $value Shopper-provided NIP.
	 */
	public static function nip( mixed $value ): string|WP_Error {
		if ( null === $value ) {
			return '';
		}
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return self::nip_error();
		}
		$raw = trim( (string) $value, ' ' );
		if ( '' === $raw ) {
			return '';
		}
		if ( 1 !== preg_match( '/^[0-9](?:[ -]*[0-9]){9}$/D', $raw ) ) {
			return self::nip_error();
		}
		$compact = str_replace( array( ' ', '-' ), '', $raw );
		if ( 1 === preg_match( '/^([0-9])\1{9}$/D', $compact ) ) {
			return self::nip_error();
		}
		$weights = array( 6, 5, 7, 2, 3, 4, 5, 6, 7 );
		$sum     = 0;
		foreach ( $weights as $index => $weight ) {
			$sum += (int) $compact[ $index ] * $weight;
		}
		$check = $sum % 11;
		if ( 10 === $check || $check !== (int) $compact[9] ) {
			return self::nip_error();
		}
		return $compact;
	}

	/** Field-specific error; NIP status remains unverified. */
	public static function nip_error(): WP_Error {
		return new WP_Error( 'digicyfr_invalid_nip', __( 'Enter a Polish NIP with 10 digits and a valid check digit. Only spaces and hyphens may separate digits; PL and other VAT prefixes are not accepted.', 'digicyfr-company-checkout-fields-pl' ) );
	}

	/**
	 * Validate the relationship after individual field validation.
	 *
	 * @param mixed $intent Company intent.
	 * @param mixed $nip Shopper-provided NIP.
	 */
	public static function pair( mixed $intent, mixed $nip ): WP_Error|array {
		$normalized_nip = self::nip( $nip );
		if ( is_wp_error( $normalized_nip ) ) {
			return $normalized_nip;
		}
		$normalized_intent = self::intent( $intent );
		if ( '' !== $normalized_nip && '1' !== $normalized_intent ) {
			return new WP_Error( 'digicyfr_intent_required', __( 'Select company intent if you enter a Polish NIP.', 'digicyfr-company-checkout-fields-pl' ) );
		}
		return array(
			'intent' => $normalized_intent,
			'nip'    => $normalized_nip,
		);
	}

	/**
	 * The helper reads registered fields; exact canonical meta retains explicit "0".
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function values( WC_Order $order ): array {
		$intent = (string) $order->get_meta( self::meta_key( self::INTENT_ID ), true, 'edit' );
		$nip    = (string) $order->get_meta( self::meta_key( self::NIP_ID ), true, 'edit' );
		if ( self::enabled() ) {
			$package = 'Automattic\\WooCommerce\\Blocks\\Package';
			$service = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';
			if ( class_exists( $package ) && class_exists( $service ) ) {
				$helper   = $package::container()->get( $service );
				$read_nip = $helper->get_field_from_object( self::NIP_ID, $order, 'other' );
				if ( is_string( $read_nip ) && '' !== $read_nip ) {
					$nip = $read_nip;
				}
			}
		}
		return array(
			'intent' => $intent,
			'nip'    => $nip,
		);
	}

	/**
	 * Persist on an unsaved order for classic checkout.
	 *
	 * @param WC_Order $order Unsaved order.
	 * @param array    $pair Validated values.
	 */
	public static function write( WC_Order $order, array $pair ): void {
		$order->update_meta_data( self::meta_key( self::INTENT_ID ), $pair['intent'] );
		$order->update_meta_data( self::meta_key( self::NIP_ID ), $pair['nip'] );
	}
}
