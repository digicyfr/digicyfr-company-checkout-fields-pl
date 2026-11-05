=== Digicyfr Company Checkout Fields PL ===
Contributors: digicyfr
Tags: woocommerce, checkout, nip, poland
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.3
Requires Plugins: woocommerce
WC requires at least: 8.9.0
WC tested up to: 11.2.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Optional company intent and local Polish NIP check for WooCommerce classic and block checkout.

== Description ==

Adds two optional order-only fields. Stores the same canonical WooCommerce meta from classic and block checkout, shows read-only historic order values, and supports WooCommerce order privacy export and erasure. Capture can be disabled without deleting order data. No invoices, registry lookup, or tax logic.

Source code and issue tracker: https://github.com/digicyfr/digicyfr-company-checkout-fields-pl

== Installation ==

1. Activate WooCommerce 8.9.0 or newer.
2. Upload this ZIP in Plugins > Add New > Upload Plugin.
3. Ensure Checkout Block contains its Order information inner block if using block checkout.
4. Use WooCommerce > Company checkout fields to enable or disable capture.

== Frequently Asked Questions ==

= Does this verify the business or issue an invoice? =

No. The NIP check covers local format and check digit only. The plugin does not issue invoices or change taxes.

= What happens on uninstall? =

The plugin setting is removed. Existing order values remain in WooCommerce orders and follow the store's order/privacy retention controls.

== Changelog ==

= 0.1.0 =

Initial GPL release candidate.
