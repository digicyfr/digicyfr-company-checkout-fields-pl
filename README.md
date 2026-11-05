# Digicyfr Company Checkout Fields PL

Free GPL WooCommerce component for two optional order-specific fields: company intent and Polish NIP. It works with classic checkout and the Checkout Block's **Order information** inner block. It does not change WooCommerce's native company name, issue an invoice, check a tax registry, or change tax calculations.

**Po polsku:** Dla polskich sklepów WooCommerce: dodaj opcjonalne oznaczenie zakupu firmowego i NIP do zamówienia w kasie klasycznej lub blokowej. Wtyczka nie wystawia faktur ani nie sprawdza statusu VAT.

## Website guides and stable release

- [Company Checkout Fields PL: English overview and installation](https://www.digicyfr.com/en/wordpress-plugins/digicyfr-company-checkout-fields-pl)
- [Pola firmowe w kasie PL: opis i instalacja po polsku](https://www.digicyfr.com/pl/wordpress-plugins/digicyfr-company-checkout-fields-pl)
- [Stable v0.1.0 release, ZIP and checksum](https://github.com/digicyfr/digicyfr-company-checkout-fields-pl/releases/tag/v0.1.0)

<a href="https://www.digicyfr.com/pl/wordpress-plugins/digicyfr-company-checkout-fields-pl"><img src="https://www.digicyfr.com/images/wordpress-plugins/digicyfr-company-checkout-fields-pl.png" alt="WooCommerce Checkout Block receipt with example company details" width="520"></a>

_Example screenshot from a disposable test site._

## Use

Install the versioned ZIP while WooCommerce 8.9.0 or later is active. Tested WordPress, PHP, and WooCommerce versions are in `docs/QA-0.1.0.md`. Open **WooCommerce → Company checkout fields**. Capture starts enabled; a merchant with another NIP plugin can turn off Digicyfr capture. That removes only this plugin's checkout controls and preserves historical order values. Two active NIP capture plugins can otherwise show duplicate controls. There is no automatic migration of third-party NIP data.

Shoppers may leave both fields blank for a personal order. Company intent may be checked with a blank NIP. Entering a NIP requires company intent. Valid input has exactly ten ASCII digits, optionally separated by spaces or hyphens, and passes the local Polish check digit. The stored NIP is ten digits, including leading zeroes. This does not verify that the number belongs to a real business. The `PL` prefix and other VAT IDs are outside this field's scope.

The Checkout Block must contain its **Order information** inner block, `woocommerce/checkout-additional-information-block`; if a merchant removes it, these controls will not appear. Classic checkout appends controls to WooCommerce's `order` fieldset. The plugin uses WooCommerce's order CRUD and the canonical `_wc_other/digicyfr-company/intent` and `_wc_other/digicyfr-company/nip` meta keys. It does not write `_billing_nip`, customer meta, or billing/shipping addresses.

Order staff see a read-only company details section. Customer order details and HTML/plain emails show values once, using WooCommerce's native Additional Checkout Fields display when available and a fallback for classic orders on versions that omit it. WooCommerce's normal order permissions govern access. The plugin adds values to order personal-data export, including explicit intent `0`, and removes them when WooCommerce anonymizes that order under store policy. Deactivation and uninstall **do not erase order meta**; uninstall removes only the plugin setting. No remote request, telemetry, cookie, or NIP log is added.

Manual ZIP updates are used. The unique Digicyfr `Update URI` prevents an unrelated directory update; no automatic update endpoint exists.

The [public source repository](https://github.com/digicyfr/digicyfr-company-checkout-fields-pl) accepts bug reports and contributions. Report security issues privately using [SECURITY.md](https://github.com/digicyfr/digicyfr-company-checkout-fields-pl/blob/main/SECURITY.md). Do not include real orders or customer data in reports.

## Development

```bash
find . -type f -name '*.php' -not -path './dist/*' -print0 | xargs -0 -n 1 php -l
python3 tools/release.py build
python3 tools/release.py verify
bash tests/run-wordpress-contract.sh dist/digicyfr-company-checkout-fields-pl-0.1.0.zip
```

License: GPL-2.0-or-later; see [LICENSE](LICENSE).
See the [release process](https://github.com/digicyfr/digicyfr-company-checkout-fields-pl/blob/main/RELEASE.md) for the release gates.
