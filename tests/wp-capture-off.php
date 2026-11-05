<?php
/** New-request verification after merchant disables Digicyfr capture. */
declare(strict_types=1);

$_SERVER['HTTP_HOST']   = '127.0.0.1:' . getenv('DIGICYFR_TEST_PORT');
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';

function dccf_off_check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$order_id = (int)getenv('DCCF_ORDER_ID');
$order = wc_get_order($order_id);
dccf_off_check($order instanceof WC_Order && !DCCF_Data::enabled(), 'capture off and historical order exists');
dccf_off_check($order->get_user_id() === 0, 'historical receipt fixture is a guest order');
$package = 'Automattic\\WooCommerce\\Blocks\\Package';
$service = 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';
$fields = $package::container()->get($service)->get_fields_for_location('order');
dccf_off_check(!isset($fields[DCCF_Data::INTENT_ID]) && !isset($fields[DCCF_Data::NIP_ID]), 'no Digicyfr Block registration after restart');
$classic = DCCF_Checkout::classic_fields(array('billing' => array('billing_company' => array('label' => 'Company')), 'order' => array('third_party_nip' => array('label' => 'Existing NIP'))));
dccf_off_check(!isset($classic['order']['digicyfr_company_intent']) && !isset($classic['order']['digicyfr_company_nip']), 'no Digicyfr classic fields');
dccf_off_check(isset($classic['billing']['billing_company'], $classic['order']['third_party_nip']), 'native and third-party fields retained');
$values = DCCF_Data::values($order);
dccf_off_check($values['intent'] === '1' && $values['nip'] === '0123456789', 'historical canonical values retained');
wp_set_current_user(0);
$_GET['key'] = $order->get_order_key();
ob_start(); do_action('woocommerce_thankyou', $order_id); $guest_receipt = (string)ob_get_clean();
dccf_off_check(substr_count($guest_receipt, '0123456789') === 1, 'one historical NIP on guest receipt');
unset($_GET['key']);
$admin = get_user_by('login', 'digicyfr_test_admin');
wp_set_current_user($admin->ID);
ob_start(); DCCF_Display::admin_details($order); $staff = (string)ob_get_clean();
dccf_off_check(substr_count($staff, '0123456789') === 1, 'one staff historical display');
ob_start(); do_action('woocommerce_order_details_after_customer_details', $order); $customer = (string)ob_get_clean();
dccf_off_check(substr_count($customer, '0123456789') === 1, 'one customer historical display');
WC()->mailer();
foreach (array(false, true) as $plain) {
    ob_start();
    do_action('woocommerce_email_order_meta', $order, false, $plain);
    do_action('woocommerce_email_customer_details', $order, false, $plain);
    $email = (string)ob_get_clean();
    dccf_off_check(substr_count($email, '0123456789') === 1, 'one historical email display');
}
echo 'PASS capture-off historical data on Woo ' . WC_VERSION . "\n";
