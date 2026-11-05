# Contributing

Bug reports and pull requests are welcome at [GitHub](https://github.com/digicyfr/digicyfr-company-checkout-fields-pl). Use synthetic orders and NIP values in issues and tests. Send security concerns privately as described in [SECURITY.md](SECURITY.md).

Target `main` for pull requests. Explain the user-visible change, update `CHANGELOG.md` when behavior changes, and keep all contributed code and assets compatible with GPL-2.0-or-later. Do not add telemetry or remote requests without explicit discussion and documentation.

Before opening a pull request, lint all PHP files with `php -l`, run `python3 tools/release.py build` and `python3 tools/release.py verify`, then run `bash tests/run-wordpress-contract.sh dist/digicyfr-company-checkout-fields-pl-0.1.0.zip` when Docker is available. The release manifest controls which files enter the ZIP. Repository CI runs lint and package checks. The WooCommerce compatibility matrix and remaining manual checks are in `docs/QA-0.1.0.md`.
