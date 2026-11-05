<?php
/** Supplementary real-WordPress contract checks for an installed candidate ZIP. */
declare(strict_types=1);

$_SERVER['HTTP_HOST']   = '127.0.0.1:' . getenv('DIGICYFR_TEST_PORT');
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function dccf_check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$plugin = 'digicyfr-company-checkout-fields-pl/digicyfr-company-checkout-fields-pl.php';
dccf_check(is_plugin_active($plugin) && DCCF_VERSION === '0.1.0', 'candidate active');
update_option(DCCF_Data::OPTION, '1', false);
unload_textdomain('digicyfr-company-checkout-fields-pl', true);
$polish_locale = static fn(): string => 'pl_PL';
add_filter('locale', $polish_locale);
dccf_check(__('Company checkout fields', 'digicyfr-company-checkout-fields-pl') === 'Pola firmowe w zamówieniu', 'bundled Polish catalog loads automatically');
remove_filter('locale', $polish_locale);
unload_textdomain('digicyfr-company-checkout-fields-pl', true);
dccf_check(DCCF_Data::meta_key(DCCF_Data::INTENT_ID) === '_wc_other/digicyfr-company/intent', 'canonical intent key');
dccf_check(DCCF_Data::meta_key(DCCF_Data::NIP_ID) === '_wc_other/digicyfr-company/nip', 'canonical NIP key');
foreach (array('0123456789', '012-345-6789', '012 345 6789') as $valid) {
    dccf_check(DCCF_Data::nip($valid) === '0123456789', 'valid leading-zero NIP');
}
foreach (array('abc', 'PL0123456789', '0000000000', '1111111111', '0000000030', '0123456788', "\t0123456789", '0123456789-' , '-0123456789', '01234_56789', array('0123456789')) as $invalid) {
    dccf_check(is_wp_error(DCCF_Data::nip($invalid)), 'reject malformed NIP');
}
dccf_check(DCCF_Data::nip(null) === '' && DCCF_Data::nip('') === '', 'optional blank NIP');
dccf_check(DCCF_Checkout::block_sanitize_nip(array('0123456789')) === 'invalid-nip', 'non-scalar Store API sentinel');
dccf_check(is_wp_error(DCCF_Checkout::block_validate_nip('invalid-nip')), 'Store API rejects non-scalar sentinel');
dccf_check(DCCF_Data::pair(false, '') === array('intent' => '0', 'nip' => ''), 'personal pair');
dccf_check(DCCF_Data::pair(true, '') === array('intent' => '1', 'nip' => ''), 'company without NIP');
dccf_check(is_wp_error(DCCF_Data::pair(false, '0123456789')), 'NIP requires intent');
$errors = new WP_Error();
do_action('woocommerce_blocks_validate_location_order_fields', $errors, array(DCCF_Data::INTENT_ID => false, DCCF_Data::NIP_ID => '0123456789'), 'other');
dccf_check($errors->has_errors() && $errors->get_error_code() === 'digicyfr_intent_required', 'actual Block location hook rejects NIP without intent');

$package = 'Automattic\\WooCommerce\\Blocks\\Package';
$service = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';
$helper = $package::container()->get($service);
$fields = $helper->get_fields_for_location('order');
dccf_check(isset($fields[DCCF_Data::INTENT_ID], $fields[DCCF_Data::NIP_ID]), 'both Block fields registered in order location');
dccf_check($fields[DCCF_Data::INTENT_ID]['type'] === 'checkbox' && $fields[DCCF_Data::NIP_ID]['type'] === 'text', 'Block field types');
$classic = DCCF_Checkout::classic_fields(array('billing' => array('billing_company' => array('label' => 'Company')), 'order' => array('third_party_nip' => array('label' => 'Existing NIP'))));
dccf_check(isset($classic['order']['digicyfr_company_intent'], $classic['order']['digicyfr_company_nip']), 'classic order fields');
dccf_check(isset($classic['billing']['billing_company'], $classic['order']['third_party_nip']), 'native company and third-party field retained');

$admin = get_user_by('login', 'digicyfr_test_admin');
dccf_check($admin instanceof WP_User, 'test admin exists');
wp_set_current_user($admin->ID);
$classic_order = wc_create_order();
$classic_order->set_created_via('checkout');
$classic_order->set_billing_company('Synthetic Sp. z o.o.');
DCCF_Data::write($classic_order, array('intent' => '1', 'nip' => '0123456789'));
$classic_order->save();
$classic_order = wc_get_order($classic_order->get_id());
dccf_check($classic_order->get_meta('_wc_other/digicyfr-company/intent', true, 'edit') === '1', 'CRUD intent reload');
dccf_check($classic_order->get_meta('_wc_other/digicyfr-company/nip', true, 'edit') === '0123456789', 'CRUD NIP reload');
dccf_check($classic_order->get_billing_company() === 'Synthetic Sp. z o.o.', 'native company unchanged');
dccf_check($helper->get_field_from_object(DCCF_Data::NIP_ID, $classic_order, 'other') === '0123456789', 'Woo helper reads classic key');
$post = new WP_REST_Request('POST', '/wc/store/v1/checkout');
$post->set_param('additional_fields', array());
DCCF_Checkout::block_write($classic_order, $post);
dccf_check($classic_order->get_meta('_wc_other/digicyfr-company/intent', true, 'edit') === '0' && $classic_order->get_meta('_wc_other/digicyfr-company/nip', true, 'edit') === '', 'final POST clears stale company draft values');
$classic_order->save();
$classic_order = wc_get_order($classic_order->get_id());
dccf_check($classic_order->get_meta('_wc_other/digicyfr-company/intent', true, 'edit') === '0', 'explicit false persists after save');
$intent_rows = array_values(array_filter($classic_order->get_meta_data(), static fn($meta): bool => $meta->key === '_wc_other/digicyfr-company/intent'));
dccf_check(count($intent_rows) === 1, 'single canonical intent row after transition');
$classic_order->update_meta_data('_wc_other/digicyfr-company/intent', '1');
$classic_order->save();
$patch = new WP_REST_Request('PATCH', '/wc/store/v1/checkout');
$patch->set_param('additional_fields', array());
DCCF_Checkout::block_write($classic_order, $patch);
dccf_check($classic_order->get_meta('_wc_other/digicyfr-company/intent', true, 'edit') === '1', 'unrelated PATCH does not clear intent');
$post->set_param('additional_fields', array(DCCF_Data::INTENT_ID => true, DCCF_Data::NIP_ID => '012-345-6789'));
DCCF_Checkout::block_write($classic_order, $post);
$classic_order->save();
$classic_order = wc_get_order($classic_order->get_id());
dccf_check($classic_order->get_meta('_wc_other/digicyfr-company/intent', true, 'edit') === '1' && $classic_order->get_meta('_wc_other/digicyfr-company/nip', true, 'edit') === '0123456789', 'Store API final POST stores normalized company pair');
$native = DCCF_Display::native_fields($classic_order);
$expected_classic_native = version_compare(WC_VERSION, '11.2.2', '<');
dccf_check(isset($native[DCCF_Data::NIP_ID]) === $expected_classic_native, 'version-sensitive native classic reader');
$admin_fields = apply_filters('woocommerce_admin_shipping_fields', array(), $classic_order, 'edit');
dccf_check(!isset($admin_fields[DCCF_Data::INTENT_ID], $admin_fields[DCCF_Data::NIP_ID]), 'native editable entries removed');
ob_start();
DCCF_Display::admin_details($classic_order);
$staff = (string)ob_get_clean();
dccf_check(substr_count($staff, 'Company details') === 1 && substr_count($staff, '0123456789') === 1, 'one read-only staff section');
ob_start();
do_action('woocommerce_order_details_after_customer_details', $classic_order);
$customer = (string)ob_get_clean();
dccf_check(substr_count($customer, '0123456789') === 1, 'one customer classic display across native/fallback');
WC()->mailer();
foreach (array(false, true) as $plain) {
    ob_start();
    do_action('woocommerce_email_order_meta', $classic_order, false, $plain);
    do_action('woocommerce_email_customer_details', $classic_order, false, $plain);
    $email = (string)ob_get_clean();
    dccf_check(substr_count($email, '0123456789') === 1, $plain ? 'one plain email NIP' : 'one HTML email NIP');
}

$personal = wc_create_order();
$personal->set_created_via('checkout');
DCCF_Data::write($personal, array('intent' => '0', 'nip' => ''));
$personal->save();
$personal = wc_get_order($personal->get_id());
ob_start();
DCCF_Display::admin_details($personal);
$personal_staff = (string)ob_get_clean();
dccf_check(substr_count($personal_staff, '<dd>No</dd>') === 1, 'one read-only staff No');
ob_start();
do_action('woocommerce_order_details_after_customer_details', $personal);
$personal_customer = (string)ob_get_clean();
dccf_check(substr_count($personal_customer, 'No') === 1, 'one customer No');
WC()->mailer();
foreach (array(false, true) as $plain) {
    ob_start();
    do_action('woocommerce_email_order_meta', $personal, false, $plain);
    do_action('woocommerce_email_customer_details', $personal, false, $plain);
    $email = (string)ob_get_clean();
    dccf_check(substr_count($email, 'No') === 1, $plain ? 'one plain email No' : 'one HTML email No');
}
$guest_receipt = wc_create_order();
$guest_receipt->set_created_via('checkout');
DCCF_Data::write($guest_receipt, array('intent' => '1', 'nip' => '0123456789'));
$guest_receipt->save();
wp_set_current_user(0);
$_GET['key'] = $guest_receipt->get_order_key();
ob_start();
do_action('woocommerce_thankyou', $guest_receipt->get_id());
$guest_html = (string)ob_get_clean();
dccf_check(substr_count($guest_html, '0123456789') === 1, 'guest receipt shows company NIP exactly once with capture on');
$block_receipt = wc_create_order();
$block_receipt->set_created_via('store-api');
DCCF_Data::write($block_receipt, array('intent' => '1', 'nip' => '0123456789'));
$block_receipt->save();
$_GET['key'] = $block_receipt->get_order_key();
remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
ob_start();
do_action('woocommerce_thankyou', $block_receipt->get_id());
$block_hook_html = (string)ob_get_clean();
add_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
$block_native = DCCF_Display::native_fields($block_receipt);
$expected_hook_nip = isset($block_native[DCCF_Data::NIP_ID]) ? 0 : 1;
dccf_check(substr_count($block_hook_html, '0123456789') === $expected_hook_nip, 'Block confirmation thankyou hook leaves native fields to Woo');
unset($_GET['key']);
$owner = get_user_by('login', 'digicyfr_test_customer');
dccf_check($owner instanceof WP_User, 'test customer exists');
$owned_order = wc_create_order();
$owned_order->set_customer_id($owner->ID);
$owned_order->set_created_via('checkout');
DCCF_Data::write($owned_order, array('intent' => '1', 'nip' => '0123456789'));
$owned_order->save();
wp_set_current_user($owner->ID);
ob_start();
do_action('woocommerce_view_order', $owned_order->get_id());
$owner_html = (string)ob_get_clean();
dccf_check(substr_count($owner_html, '0123456789') === 1, 'logged-in owner view shows NIP exactly once');
$owner_export = apply_filters('woocommerce_privacy_export_order_personal_data', array(), $owned_order);
$owner_nips = array_values(array_filter($owner_export, static fn($row): bool => ($row['name'] ?? '') === 'Polish NIP'));
dccf_check(count($owner_nips) === 1 && $owner_nips[0]['value'] === '0123456789', 'registered order privacy export includes NIP');
DCCF_Display::privacy_erase($owned_order);
$owned_order->save();
$owned_order = wc_get_order($owned_order->get_id());
dccf_check($owned_order->get_meta('_wc_other/digicyfr-company/intent', true, 'edit') === '' && $owned_order->get_meta('_wc_other/digicyfr-company/nip', true, 'edit') === '', 'registered order privacy erase removes both keys');
wp_set_current_user($admin->ID);
$export = apply_filters('woocommerce_privacy_export_order_personal_data', array(), $personal);
$intents = array_values(array_filter($export, static fn($row): bool => ($row['name'] ?? '') === 'Company intent'));
dccf_check(count($intents) === 1 && $intents[0]['value'] === 'No', 'privacy export retains explicit false');
DCCF_Display::privacy_erase($personal);
$personal->save();
$personal = wc_get_order($personal->get_id());
dccf_check($personal->get_meta('_wc_other/digicyfr-company/intent', true, 'edit') === '', 'privacy erase removes canonical intent');
$prior_capture = get_option(DCCF_Data::OPTION, false);
update_option(DCCF_Data::OPTION, '0', false);
$imported = new WC_Order();
$imported->update_meta_data('_wc_other/digicyfr-company/intent', '1');
$imported->update_meta_data('_wc_other/digicyfr-company/nip', '<img src=x onerror=alert(1)>');
$mail_fields = DCCF_Display::email_fallback(array(), false, $imported);
if (false === $prior_capture) { delete_option(DCCF_Data::OPTION); } else { update_option(DCCF_Data::OPTION, $prior_capture, false); }
$encoded_mail = json_encode($mail_fields);
dccf_check(!str_contains($encoded_mail, '<img') && str_contains($encoded_mail, '&lt;img'), 'historical email fallback escapes imported meta');

function dccf_test_die($message): void {
    throw new RuntimeException(wp_strip_all_tags((string)$message));
}
$die_filter = static fn(): string => 'dccf_test_die';
add_filter('wp_die_handler', $die_filter);
$before_option = get_option(DCCF_Data::OPTION, '1');
$existing_editor = get_user_by('login', 'dccf_test_editor');
$editor_id = $existing_editor instanceof WP_User ? $existing_editor->ID : wp_create_user('dccf_test_editor', 'test-only-password', 'dccf-editor@example.invalid');
(new WP_User($editor_id))->set_role('editor');
wp_set_current_user($editor_id);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array('action' => 'dccf_save_capture', 'dccf_capture_enabled' => '1', '_wpnonce' => wp_create_nonce('dccf_save_capture'));
$denied = false;
try { DCCF_Admin::save(); } catch (RuntimeException $error) { $denied = true; }
dccf_check($denied, 'editor denied even with valid nonce');
wp_set_current_user($admin->ID);
$_POST['_wpnonce'] = 'invalid';
$denied = false;
try { DCCF_Admin::save(); } catch (RuntimeException $error) { $denied = true; }
dccf_check($denied, 'manager denied with invalid nonce');
$_POST['_wpnonce'] = wp_create_nonce('dccf_save_capture');
$_SERVER['REQUEST_METHOD'] = 'GET';
$denied = false;
try { DCCF_Admin::save(); } catch (RuntimeException $error) { $denied = true; }
dccf_check($denied, 'GET denied despite valid nonce');
dccf_check(get_option(DCCF_Data::OPTION, '1') === $before_option, 'denied settings requests leave option unchanged');
remove_filter('wp_die_handler', $die_filter);
$_POST = array();
$_SERVER['REQUEST_METHOD'] = 'GET';

echo 'PASS supplemental WP contract on Woo ' . WC_VERSION . ', WP ' . get_bloginfo('version') . ', PHP ' . PHP_VERSION . "\n";
