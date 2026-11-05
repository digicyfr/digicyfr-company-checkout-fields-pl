# Release QA — 0.1.0

This record covers the stable self-hosted GPL ZIP. It is not a WordPress.org submission, tax validation, or invoice feature.

## Exact artifact

- ZIP: `dist/digicyfr-company-checkout-fields-pl-0.1.0.zip`, byte-identical to the public GitHub `v0.1.0` release asset downloaded and verified with its `.sha256` sidecar on 2026-11-05.
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
- The new package differs from the matrix-tested ZIP only in bundled `README.md` and `readme.txt`; PHP, languages and other runtime bytes are unchanged. The full eight-cell browser matrix above was not repeated on the documentation-only rebuild. Two cells were repeated on the exact public artifact below.

## Post-tag browser check on the exact public ZIP — 2026-11-05

The downloaded public release ZIP and sidecar verified at SHA-256 `33da10e89d0dc9d00a0c1a30236e1ccd45e91a8b57ba1a53facedc67c70e0379`. Two fresh disposable WordPress 7.1 / PHP 8.3.33 / MariaDB 11.4.13 stores each installed, activated, deactivated, and reactivated that ZIP beside pinned WooCommerce 11.2.2. Both used a synthetic PLN 1.00 virtual product and the noncharging BACS fixture. The Block store used HPOS authoritative with compatibility sync off; the Classic store used legacy order storage.

| Checkout/storage | Guest and customer browser cases | Finalized order IDs | Rejected input | Stored data |
| --- | --- | --- | --- | --- |
| Block / HPOS | Personal and company accepted for each shopper | 13–16, plus keyboard order 17 | Wrong-checksum NIP and NIP without company intent returned HTTP 400 with visible field errors | Personal intent `0` and empty NIP; company intent `1`, normalized `0123456789`, native `billing_company` retained; one meta row per canonical key, no postmeta mirror |
| Classic / legacy | Personal and company accepted for each shopper | 13–16 | The same two invalid cases displayed WooCommerce errors; the checkout AJAX response was HTTP 200 but no order was completed | The same canonical values and one meta row per key; native `billing_company` retained |

The two modes and two shopper identities covered 16 scripted browser scenarios. The order snapshot contained only the successful on-hold orders (plus Block order 17 after the mobile check). Fresh guest and signed-in customer company receipts showed one intent label and one NIP in both modes, with capture enabled and disabled. The administrator order page showed one read-only Company details section and one NIP in each mode. On the Block store, a 390px keyboard-only company checkout toggled intent with Space, reached NIP and Place Order with Tab, submitted with Enter, and had no document overflow (390px document). WooCommerce's customer on-hold email class was triggered with a synthetic company order; captured HTML and rendered plain-text content each contained one intent label and one NIP in both modes. This checks the email template path; automatic email delivery after checkout was not established by the disposable fixture.

Screenshots: [Block company receipt](evidence/0.1.0/block-company-receipt.png), [bad checksum](evidence/0.1.0/block-bad-checksum.png), [NIP without intent](evidence/0.1.0/block-nip-without-intent.png), [390px keyboard receipt](evidence/0.1.0/block-390px-keyboard-receipt.png), [admin order](evidence/0.1.0/block-admin-order.png), [historic receipt with capture off](evidence/0.1.0/block-historic-receipt-capture-off.png); [Classic company receipt](evidence/0.1.0/classic-company-receipt.png), [bad checksum](evidence/0.1.0/classic-bad-checksum.png), [admin order](evidence/0.1.0/classic-admin-order.png), and [historic receipt with capture off](evidence/0.1.0/classic-historic-receipt-capture-off.png). All names, addresses, emails, NIP, and orders were synthetic; both disposable stores were removed.

## Limits

- No manual screen-reader or live merchant test has been performed. NIP validation checks only local syntax and check digit; no registry or tax status was verified.
- Compatibility is evidenced only for the pinned WooCommerce 8.9.0 and 11.2.2 releases, WordPress 7.1, and PHP 8.3.33 in this local matrix. Intervening or newer versions require their own tests.

## Automated accessibility audit on the public ZIP — 2026-11-05

The exact public ZIP and SHA sidecar above were verified again and installed on a fresh disposable WordPress 7.1 / PHP 8.3.33 / WooCommerce 11.2.2 site with synthetic products and noncharging BACS. Chromium 156.0.8078.4 and axe-core 4.10.3 ran WCAG 2/2.1/2.2 A/AA rule tags on the merchant setting, settled Block and Classic checkouts, and both visible wrong-checksum NIP error states. Every tested state had **zero axe violations**. Both checkout modes were also run with this plugin deactivated as WooCommerce baselines; both baselines had zero violations. At 390px the settings and both checkouts had document width 390px. Chromium's accessibility tree exposed a named company checkbox and NIP textbox in both modes; Space toggled the checkbox and Tab moved to NIP. The settings checkbox and Save button were also named and sequentially reachable by Tab. See [machine-readable audit evidence](evidence/0.1.0/axe-2026-11-05.json).

Classic checkout had one inconclusive contrast check on WooCommerce's native order-notes textarea with the plugin both active and inactive. An early Block loading-skeleton snapshot briefly raised an axe ARIA finding on WooCommerce's skeleton markup; it disappeared after the cart finished loading. These are not plugin-owned defects. The automated check did not require a change to the `v0.1.0` ZIP, tag, or release branch. A human screen-reader pass and merchant-configured checkout, payment and email acceptance remain open.

## Stable self-hosted release decision — 2026-11-05

The exact public ZIP passed the published SHA-256 sidecar check (`33da10e89d0dc9d00a0c1a30236e1ccd45e91a8b57ba1a53facedc67c70e0379`) and source-byte comparison. The protected `release/0.1.0` branch and annotated `v0.1.0` tag point to the same frozen release commit, and their CI passed. Main-branch CI and the exact-ZIP checkout and axe checks passed before promotion. This supports a stable self-hosted GitHub release. The ZIP, tag, release branch and attached assets were preserved. Human screen-reader testing, WooCommerce-native contrast resolution, and target merchant theme, payment, checkout and email acceptance remain unperformed; axe and browser checks do not establish WCAG conformance.

## Live Digicyfr website delivery — 5 November 2026

- The live [English](https://www.digicyfr.com/en/wordpress-plugins/digicyfr-company-checkout-fields-pl) and [Polish](https://www.digicyfr.com/pl/wordpress-plugins/digicyfr-company-checkout-fields-pl) pages both returned HTTP 200, with the current stable v0.1.0 ZIP link, matching SHA-256, screenshot and canonical metadata; neither carried `noindex`. Both URLs appeared in the live sitemap.
- The live screenshot URL `/images/wordpress-plugins/digicyfr-company-checkout-fields-pl.png` returned a PNG. `https://digicyfr.com/plugins/digicyfr-company-checkout-fields-pl/` followed the `Update URI` redirect to the English detail page.
- Following the ZIP and checksum links from the live pages returned the unchanged public GitHub release asset and sidecar. ZIP SHA-256: `33da10e89d0dc9d00a0c1a30236e1ccd45e91a8b57ba1a53facedc67c70e0379`. The same public ZIP hash had already passed the clean install and plugin-specific checks in this QA record; no new clean install was run for this site-delivery audit.
- Website production commit: [`9dfb9728`](https://github.com/digicyfr/cyfr/commit/9dfb9728c92e7d4437cfe2f8368b54ce917c0008). Distribution remains a manual ZIP install/update; this is not a WordPress.org or automatic-update claim. Human screen-reader and representative-host checks remain follow-up work for broader claims.
