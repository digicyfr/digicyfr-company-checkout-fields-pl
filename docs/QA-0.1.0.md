# Local candidate QA — 0.1.0

This record covers the self-hosted, GPL candidate ZIP only. It is not a WordPress.org submission, tax validation, invoice feature, or public release approval.

## Exact artifact

- ZIP: `dist/digicyfr-company-checkout-fields-pl-0.1.0.zip`
- SHA-256: `33da10e89d0dc9d00a0c1a30236e1ccd45e91a8b57ba1a53facedc67c70e0379`
- Standalone `tools/release.py build` and `verify` passed: all 12 allowlisted files match source bytes; no development docs or tests are bundled. Compared with the prior `d21c0418` candidate, only bundled `README.md` and `readme.txt` changed; runtime files are byte-identical.
- PHP lint passed for all plugin and test PHP files. WordPress Coding Standards (`phpcs --standard=WordPress`) passed for runtime PHP.

## Disposable WordPress evidence

Environment: WordPress 7.1, PHP 8.3.33, pinned WooCommerce 8.9.0 and 11.2.2. Legacy order storage and HPOS with custom order tables authoritative and compatibility sync off are tested separately. Checkout is a non-charging synthetic BACS fixture; test orders use synthetic names, emails, and leading-zero NIP `0123456789`.

The runtime-identical prior `d21c0418` ZIP browser matrix covers guest and logged-in shoppers in each cell. The current ZIP was rebuilt after documentation/source-link updates, and its fresh WooCommerce 11.2.2 Block/HPOS contract test is recorded below. P = successful personal order with explicit canonical intent `"0"` and blank NIP; C = successful company order with intent `"1"`, normalized NIP, and native `billing_company`; E = wrong-checksum NIP and NIP without company intent, each rejected visibly without a completed order. Each successful order is reloaded through `WC_Order` and checked for one row per canonical key. Fresh-session receipt checks count one company-intent label and one NIP with capture on and off.

| WooCommerce | Checkout | Storage | Guest P/C/E | Customer P/C/E | Receipt on/off | Result |
| --- | --- | --- | --- | --- | --- | --- |
| 8.9.0 | Classic | Legacy | Pass | Pass | Pass | Pass; final clean-site orders 13/14 and 15/16 on port 18110 |
| 8.9.0 | Classic | HPOS | Pass | Pass | Pass | Pass; orders 13/14 and 15/16 on port 18111 |
| 8.9.0 | Block | Legacy | Pass | Pass | Pass | Pass; browser orders 22/23 and 26/27 on port 18112; direct HTTP orders 20/21 |
| 8.9.0 | Block | HPOS | Pass | Pass | Pass | Pass; orders 13/14 and 17/18 on port 18113 |
| 11.2.2 | Classic | Legacy | Pass | Pass | Pass | Pass; orders 13/14 and 15/16 on port 18114 |
| 11.2.2 | Classic | HPOS | Pass | Pass | Pass | Pass; orders 13/14 and 16/17 on port 18115 |
| 11.2.2 | Block | Legacy | Pass | Pass | Pass | Pass; orders 13/14 and 16/17 on port 18116 |
| 11.2.2 | Block | HPOS | Pass | Pass | Pass | Pass; final clean-site orders 13/14 and 15/16 on port 18117; 390px keyboard order 17 |

On port 18115, the HPOS admin order page showed one read-only company section without a native editable duplicate, and a real customer on-hold HTML email contained one intent/NIP pair. Port 18114's legacy admin page likewise showed one read-only section. On ports 18116 (Woo 11.2.2/legacy) and 18111 (Woo 8.9.0/HPOS), the exact-ZIP `tests/wp-contract.php` passed: Polish catalog auto-loading; NIP format/checksum and malicious input; Block/classic hook registration and validation; canonical Store API POST persistence, stale company-to-personal clearing, PATCH non-clearing; native-versus-fallback display; guest/owner receipt hooks; HTML/plain email once; guest and registered privacy export/erase; direct settings-handler permission, method, and nonce denials. A separate fresh-request `tests/wp-capture-off.php` passed on 18111; changing the option inside a running request does not remove fields already registered at boot. `tests/wp-uninstall.php` passed on both: the plugin setting was removed and WooCommerce order meta remained.

On port 18113, WooCommerce 8.9.0 Block/HPOS completed guest and registered browser P/C/E: company and personal orders stored one canonical intent/NIP row, with HPOS authoritative and no postmeta mirror; both invalid requests returned HTTP 400 and created no finalized order. Real guest and customer company receipts showed one intent/NIP pair with capture on and off. Capture was restored on after the checks.

The reserved clean sites completed the matrix: WooCommerce 8.9.0 Classic/legacy on port 18110 and WooCommerce 11.2.2 Block/HPOS on port 18117 each passed guest and registered P/C/E, canonical CRUD counts, and fresh capture-on/off receipts. Port 18117 also passed a 390px keyboard-only company checkout: no horizontal overflow (document width 390px); Space toggled intent, Tab reached NIP and Place Order, and Enter completed order 17. The full eight-cell evidence and per-site order IDs are in `tools/wordpress-test/company-checkout-matrix-evidence.md`.

Both reserved sites installed the frozen exact ZIP through shared `smoke-zip.php`, which activated, deactivated, and reactivated version 0.1.0 successfully before browser tests. They were fresh WordPress sites, not upgrades from provisional plugin bytes.

Root independently opened the final clean-site admin order pages: order 14 on both 18110 and 18117 showed exactly one read-only Company details section, one NIP, and no editable native Digicyfr field. Independent review cleared the exact d21 source and ZIP with no remaining blocker.

The WooCommerce 11.2.2 real Store API personal checkout stored `_wc_other/digicyfr-company/intent` as explicit `"0"` with exactly one meta row; Woo's privacy exporter returned `Company intent: No`. Company checkout stored `"1"` and `0123456789` once each. The browser showed field-specific errors for checksum failure and NIP without intent. Capture-off historical company data remained visible once on real receipts.

WooCommerce 8.9.0 Block/legacy on WordPress 7.1 also passed a direct authenticated HTTP Store API sequence using Woo's cart token and nonce. Personal and company requests returned 200/on-hold, while the two invalid requests returned 400 with Digicyfr field errors. Reloaded `WC_Order` records 20/21 had one canonical meta row per key, normalized NIP, and unchanged native company name; no invalid request created a finalized order. Fresh guest receipts for both orders displayed intent once with capture on and off, and company NIP once. The initial browser runner clicked before Woo's billing validation settled and reproduced the stall with Digicyfr deactivated. After it blurred the last field and waited for checkout readiness, the exact ZIP passed guest and registered browser P/C/E flows. Orders 22/23 and 26/27 each had one canonical row per key; full-page guest and customer company receipts showed one intent/NIP pair with capture on and off. A privacy check passed.

## Plugin Check

Plugin Check ran on the installed runtime-identical prior ZIP on WooCommerce 11.2.2. It reported an `ERROR` for the intentional unique Digicyfr `Update URI` (`plugin_updater_detected`) and a `WARNING` for loading the bundled Polish textdomain (`load_plugin_textdomainFound`). Those checks target WordPress.org distribution; this is a self-hosted manual-update ZIP. No other Plugin Check finding was reported. The tool process exited 0, but this is **not** a clean Plugin Check result.

## Current ZIP retest — 2026-11-05

- Fresh self-contained `tests/run-wordpress-contract.sh` passed for SHA-256 `33da10e89d0dc9d00a0c1a30236e1ccd45e91a8b57ba1a53facedc67c70e0379`: pinned WooCommerce 11.2.2 ZIP checksum, WordPress 7.1/PHP 8.3.33, Checkout Block, HPOS authoritative with compatibility sync off. WooCommerce and plugin ZIP installation, activation, deactivation and reactivation passed; `tests/wp-contract.php` passed all registration, validation, CRUD, display, privacy and admin authorization checks. The site used synthetic data and was removed after testing.
- The new package differs from the matrix-tested ZIP only in bundled `README.md` and `readme.txt`; PHP, languages and other runtime bytes are unchanged. The full browser matrix above was not repeated on the documentation-only rebuild.

## Limits

- No manual screen-reader or live merchant test has been performed. NIP validation checks only local syntax and check digit; no registry or tax status was verified.
- Compatibility is evidenced only for the pinned WooCommerce 8.9.0 and 11.2.2 releases, WordPress 7.1, and PHP 8.3.33 in this local matrix. Intervening or newer versions require their own tests.
