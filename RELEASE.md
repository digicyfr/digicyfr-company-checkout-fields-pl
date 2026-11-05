# Release process

Version `0.1.0` is a local release candidate. Work on `main`; freeze a `release/0.1.0` branch for the reviewed package. Tag `v0.1.0` from that branch only after all gates below pass. Publish the matching ZIP and `.zip.sha256` as release assets and on Digicyfr's download page. Do not generate a second ZIP by zipping the repository tree.

## Checks

1. Run PHP lint and `python3 tools/release.py build && python3 tools/release.py verify` (CI checks these).
2. Run `bash tests/run-wordpress-contract.sh dist/digicyfr-company-checkout-fields-pl-0.1.0.zip` for a fresh WooCommerce 11.2.2 Block/HPOS contract check. Review the broader eight-cell matrix in `docs/QA-0.1.0.md` whenever checkout behavior changes.
3. Perform a merchant acceptance check on the target theme, checkout type, payment methods, and order storage mode using non-charging test orders. Confirm one company/NIP pair on orders, emails, and receipts; test invalid NIP and capture-off behavior. Complete a manual screen-reader pass of both classic and Block checkout.
4. Confirm the public source link, release asset, Digicyfr page, changelog, license, support/security path, and manual update instructions. Download the public ZIP and compare its SHA-256 with the release asset before a clean install test.

The NIP check covers format and check digit only. This plugin does not establish business identity, tax status, or invoice eligibility. Its `Update URI` does not supply automatic updates. WordPress.org submission needs a separate directory-specific review.
