<?php
/** Confirm uninstall removes settings while retaining WooCommerce order records. */
declare(strict_types=1);

$_SERVER['HTTP_HOST']   = '127.0.0.1:' . getenv('DIGICYFR_TEST_PORT');
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';

$order = wc_create_order();
DCCF_Data::write($order, array('intent' => '1', 'nip' => '0123456789'));
$order->save();
update_option(DCCF_Data::OPTION, '0', false);
define('WP_UNINSTALL_PLUGIN', 'digicyfr-company-checkout-fields-pl/digicyfr-company-checkout-fields-pl.php');
require WP_PLUGIN_DIR . '/digicyfr-company-checkout-fields-pl/uninstall.php';
$reloaded = wc_get_order($order->get_id());
if (false !== get_option(DCCF_Data::OPTION, false)
    || !$reloaded instanceof WC_Order
    || '1' !== $reloaded->get_meta(DCCF_Data::meta_key(DCCF_Data::INTENT_ID), true, 'edit')
    || '0123456789' !== $reloaded->get_meta(DCCF_Data::meta_key(DCCF_Data::NIP_ID), true, 'edit')) {
    fwrite(STDERR, "FAIL: uninstall setting/order retention\n");
    exit(1);
}
echo 'PASS uninstall retains Woo order data on ' . WC_VERSION . "\n";
