<?php
/** Seed or verify one disposable Company Checkout Fields PL matrix site. */
declare(strict_types=1);

$ccf_action  = $argv[1] ?? '';
$ccf_port    = getenv('DIGICYFR_TEST_PORT');
$ccf_mode    = getenv('DIGICYFR_CHECKOUT_MODE');
$ccf_storage = getenv('DIGICYFR_STORAGE_MODE');
if (!in_array($ccf_action, array('seed', 'verify'), true)
    || !in_array($ccf_mode, array('classic', 'block'), true)
    || !in_array($ccf_storage, array('legacy', 'hpos'), true)
    || !is_string($ccf_port)
    || !preg_match('/^[1-9][0-9]{0,4}$/', $ccf_port)
    || (int) $ccf_port > 65535) {
    throw new RuntimeException('Invalid matrix action, mode, storage, or port');
}

$_SERVER['HTTP_HOST']   = '127.0.0.1:' . $ccf_port;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PORT'] = $ccf_port;
$_SERVER['HTTPS']      = 'off';
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}
require '/var/www/html/wp-load.php';

if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
    throw new RuntimeException('WooCommerce must be active before seeding');
}

$ccf_version = defined('WC_VERSION') ? WC_VERSION : '';
$ccf_manifest = get_option('digicyfr_ccf_matrix_manifest');
if ('seed' === $ccf_action && !is_array($ccf_manifest)) {
    wp_set_current_user(1);

    // Set order storage before any test orders exist. Verify in another PHP process.
    update_option('woocommerce_custom_orders_table_enabled', 'hpos' === $ccf_storage ? 'yes' : 'no');
    update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
    update_option('woocommerce_currency', 'PLN');
    update_option('woocommerce_default_country', 'PL');
    update_option('woocommerce_enable_guest_checkout', 'yes');
    // Woo 9.3+ installs new stores in Coming Soon mode by default; customer
    // sessions cannot reach checkout until the disposable store is public.
    update_option('woocommerce_coming_soon', 'no');
    update_option('woocommerce_store_pages_only', 'no');
    // Woo's first-run Block migration updates all three visibility options at once.
    // Set them together so a later page load cannot reset company to hidden.
    update_option('woocommerce_checkout_phone_field', 'optional');
    update_option('woocommerce_checkout_company_field', 'optional');
    update_option('woocommerce_checkout_address_2_field', 'optional');
    update_option('woocommerce_calc_taxes', 'no');
    $ccf_bacs = get_option('woocommerce_bacs_settings', array());
    if (!is_array($ccf_bacs)) {
        $ccf_bacs = array();
    }
    update_option('woocommerce_bacs_settings', array_merge($ccf_bacs, array(
        'enabled' => 'yes',
        'title' => 'Synthetic bank transfer',
        'description' => 'Disposable local test payment; no real transfer.',
    )));

    $ccf_checkout_id = (int) wc_get_page_id('checkout');
    if ('classic' === $ccf_mode) {
        $ccf_checkout_id = wp_insert_post(array(
            'post_title'   => 'Classic Checkout Test',
            'post_name'    => 'classic-checkout-test',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[woocommerce_checkout]',
        ), true);
        if (is_wp_error($ccf_checkout_id) || !$ccf_checkout_id) {
            throw new RuntimeException('Could not create classic checkout page');
        }
        $ccf_checkout_id = (int) $ccf_checkout_id;
        update_option('woocommerce_checkout_page_id', $ccf_checkout_id);
    } else {
        $ccf_page = get_post($ccf_checkout_id);
        if (!$ccf_page || !has_block('woocommerce/checkout', $ccf_page)
            || !has_block('woocommerce/checkout-additional-information-block', $ccf_page)) {
            throw new RuntimeException('WooCommerce default Checkout Block or Order information inner block is absent');
        }
        $ccf_blocks = parse_blocks($ccf_page->post_content);
        foreach ($ccf_blocks as &$ccf_block) {
            if (($ccf_block['blockName'] ?? '') === 'woocommerce/checkout') {
                $ccf_block['attrs']['showCompanyField'] = true;
                $ccf_block['attrs']['requireCompanyField'] = false;
            }
        }
        unset($ccf_block);
        wp_update_post(array('ID' => $ccf_checkout_id, 'post_content' => serialize_blocks($ccf_blocks)));
    }

    $ccf_product = new WC_Product_Simple();
    $ccf_product->set_name('Synthetic virtual checkout item');
    $ccf_product->set_status('publish');
    $ccf_product->set_virtual(true);
    $ccf_product->set_regular_price('1.00');
    $ccf_product->set_price('1.00');
    $ccf_product->set_sku('DIGICYFR-CCF-MATRIX');
    $ccf_product_id = (int) $ccf_product->save();
    if (!$ccf_product_id) {
        throw new RuntimeException('Could not create virtual product');
    }

    $ccf_login = 'digicyfr_test_customer';
    $ccf_customer_id = username_exists($ccf_login);
    if (!$ccf_customer_id) {
        $ccf_customer_id = wp_create_user($ccf_login, 'test-customer-password', 'test-customer@example.invalid');
    }
    if (is_wp_error($ccf_customer_id) || !$ccf_customer_id) {
        throw new RuntimeException('Could not create test customer');
    }
    $ccf_customer = new WP_User((int) $ccf_customer_id);
    $ccf_customer->set_role('customer');
    update_user_meta((int) $ccf_customer_id, 'billing_first_name', 'Test');
    update_user_meta((int) $ccf_customer_id, 'billing_last_name', 'Customer');
    update_user_meta((int) $ccf_customer_id, 'billing_email', 'test-customer@example.invalid');
    update_user_meta((int) $ccf_customer_id, 'billing_country', 'PL');
    update_user_meta((int) $ccf_customer_id, 'billing_address_1', 'Testowa 1');
    update_user_meta((int) $ccf_customer_id, 'billing_city', 'Warszawa');
    update_user_meta((int) $ccf_customer_id, 'billing_postcode', '00-001');

    $ccf_manifest = array(
        'woocommerce_version' => $ccf_version,
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'checkout_mode' => $ccf_mode,
        'order_storage' => $ccf_storage,
        'port' => (int) $ccf_port,
        'checkout_page_id' => $ccf_checkout_id,
        'product_id' => $ccf_product_id,
        'customer_id' => (int) $ccf_customer_id,
        'customer_login' => $ccf_login,
        'customer_password' => 'test-customer-password',
        'admin_login' => 'digicyfr_test_admin',
        'admin_password' => 'test-only-password',
        'canonical_intent_key' => '_wc_other/digicyfr-company/intent',
        'canonical_nip_key' => '_wc_other/digicyfr-company/nip',
    );
    update_option('digicyfr_ccf_matrix_manifest', $ccf_manifest, false);
}

if (!is_array($ccf_manifest)
    || ($ccf_manifest['woocommerce_version'] ?? '') !== $ccf_version
    || ($ccf_manifest['checkout_mode'] ?? '') !== $ccf_mode
    || ($ccf_manifest['order_storage'] ?? '') !== $ccf_storage
    || (int) ($ccf_manifest['port'] ?? 0) !== (int) $ccf_port) {
    throw new RuntimeException('Fixture manifest does not match this cell');
}

$ccf_product = wc_get_product((int) $ccf_manifest['product_id']);
$ccf_page = get_post((int) $ccf_manifest['checkout_page_id']);
$ccf_customer = get_user_by('id', (int) $ccf_manifest['customer_id']);
$ccf_hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
if (!$ccf_product || !$ccf_product->is_virtual() || $ccf_product->get_price() !== '1.00'
    || !$ccf_page || !$ccf_customer || (int) wc_get_page_id('checkout') !== (int) $ccf_page->ID
    || (bool) $ccf_hpos_enabled !== ('hpos' === $ccf_storage)
    || get_option('woocommerce_custom_orders_table_data_sync_enabled') !== 'no'
    || get_option('woocommerce_enable_guest_checkout') !== 'yes'
    || get_option('woocommerce_coming_soon') !== 'no'
    || get_option('woocommerce_store_pages_only') !== 'no'
    || get_option('woocommerce_checkout_phone_field') !== 'optional'
    || get_option('woocommerce_checkout_company_field') !== 'optional'
    || get_option('woocommerce_checkout_address_2_field') !== 'optional'
    || (get_option('woocommerce_bacs_settings', array())['enabled'] ?? '') !== 'yes') {
    throw new RuntimeException('Fixture verification failed');
}
if ('classic' === $ccf_mode && trim($ccf_page->post_content) !== '[woocommerce_checkout]') {
    throw new RuntimeException('Classic checkout page is not the expected shortcode');
}
if ('block' === $ccf_mode && (!has_block('woocommerce/checkout', $ccf_page)
    || !has_block('woocommerce/checkout-additional-information-block', $ccf_page))) {
    throw new RuntimeException('Checkout Block page is missing required inner block');
}
if ('block' === $ccf_mode) {
    $ccf_checkout_blocks = array_filter(parse_blocks($ccf_page->post_content), static function ($ccf_block) {
        return ($ccf_block['blockName'] ?? '') === 'woocommerce/checkout';
    });
    $ccf_checkout_block = reset($ccf_checkout_blocks);
    if (!$ccf_checkout_block || ($ccf_checkout_block['attrs']['showCompanyField'] ?? false) !== true) {
        throw new RuntimeException('Native company field is not enabled in the Checkout Block');
    }
}

$ccf_manifest['site_url'] = home_url('/');
$ccf_manifest['checkout_url'] = get_permalink($ccf_page);
$ccf_manifest['add_to_cart_url'] = add_query_arg('add-to-cart', (int) $ccf_manifest['product_id'], home_url('/'));
$ccf_manifest['hpos_authoritative'] = (bool) $ccf_hpos_enabled;
$ccf_manifest['compatibility_sync_enabled'] = false;
echo wp_json_encode($ccf_manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
