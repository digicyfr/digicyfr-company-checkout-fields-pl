<?php
/**
 * Erase only plugin setting; WooCommerce orders retain their own data.
 *
 * @package DigicyfrCompanyCheckoutFieldsPL
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dccf_capture_enabled' );
