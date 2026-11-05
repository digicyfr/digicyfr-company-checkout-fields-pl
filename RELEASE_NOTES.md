# Company Checkout Fields PL 0.1.0 release candidate

SHA-256 of `digicyfr-company-checkout-fields-pl-0.1.0.zip`: `33da10e89d0dc9d00a0c1a30236e1ccd45e91a8b57ba1a53facedc67c70e0379`.

This first version adds optional company intent and Polish NIP to WooCommerce classic and Block checkout, keeping the values on the order. It supports WooCommerce HPOS, historical read-only display, capture disablement, and order privacy export/erasure. English and Polish strings are bundled. Uninstall keeps existing order values under the store's retention policy.

Verified on WordPress 7.1/PHP 8.3.33 with WooCommerce 8.9.0 and 11.2.2 in an eight-cell checkout/storage matrix on a runtime-identical ZIP. The final exact ZIP passed a fresh WooCommerce 11.2.2 Checkout Block/HPOS contract test, including activation/deactivation/reactivation, validation, CRUD, display, privacy and authorization checks. Only bundled README files changed after the broader matrix.

Before public release, run merchant acceptance tests with the target theme, checkout and payment configuration, plus a manual screen-reader check of classic and Block checkout. Intervening or newer WooCommerce versions were not tested. NIP validation checks only format and check digit; it does not verify business identity or tax status. No invoices or tax changes are provided. Updates are manual ZIP installs. See `docs/QA-0.1.0.md` for full evidence.
