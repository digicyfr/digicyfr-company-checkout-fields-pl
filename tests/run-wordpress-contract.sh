#!/usr/bin/env bash
# Fresh, non-charging WordPress/WooCommerce contract check of an exact release ZIP.
set -euo pipefail

if [[ $# -ne 1 ]]; then
  printf 'Usage: %s <built-plugin.zip>\n' "$0" >&2
  exit 2
fi

plugin_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
archive="$(realpath "$1")"
test_dir="$plugin_dir/tests/wordpress-test"
compose="$test_dir/compose.yaml"
project="digicyfr-company-contract-$$"
port="$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1]); s.close()')"
woocommerce_version="11.2.2"
woocommerce_sha="a4746183efd951de5837396aad119f7cf508f06cb699ccb590a01cada4c44932"
temp_dir="$(mktemp -d)"

cleanup() {
  sudo -n env "DIGICYFR_TEST_PORT=$port" docker compose -p "$project" -f "$compose" down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$temp_dir"
}
trap cleanup EXIT

python3 "$plugin_dir/tools/release.py" verify --archive "$archive"
curl -fLsS "https://downloads.wordpress.org/plugin/woocommerce.${woocommerce_version}.zip" -o "$temp_dir/woocommerce.zip"
printf '%s  %s\n' "$woocommerce_sha" "$temp_dir/woocommerce.zip" | sha256sum --check

sudo -n env "DIGICYFR_TEST_PORT=$port" docker compose -p "$project" -f "$compose" up -d --wait
container="$(sudo -n env "DIGICYFR_TEST_PORT=$port" docker compose -p "$project" -f "$compose" ps -q wordpress)"
test -n "$container"
for _ in {1..90}; do
  if sudo -n docker exec "$container" test -f /var/www/html/wp-load.php; then break; fi
  sleep 1
done
sudo -n docker exec "$container" test -f /var/www/html/wp-load.php
sudo -n docker cp "$test_dir/install-site.php" "$container:/tmp/digicyfr-install-site.php"
sudo -n docker cp "$test_dir/smoke-zip.php" "$container:/tmp/digicyfr-smoke.php"
sudo -n docker cp "$test_dir/company-checkout-site.php" "$container:/tmp/digicyfr-company-checkout-site.php"
sudo -n docker cp "$plugin_dir/tests/wp-contract.php" "$container:/tmp/digicyfr-wp-contract.php"
sudo -n docker exec -e "DIGICYFR_TEST_PORT=$port" "$container" php /tmp/digicyfr-install-site.php
sudo -n docker cp "$temp_dir/woocommerce.zip" "$container:/tmp/digicyfr-plugin.zip"
sudo -n docker exec -e "DIGICYFR_TEST_PORT=$port" -e DIGICYFR_PLUGIN_SLUG=woocommerce "$container" php /tmp/digicyfr-smoke.php
sudo -n docker exec -e "DIGICYFR_TEST_PORT=$port" -e DIGICYFR_CHECKOUT_MODE=block -e DIGICYFR_STORAGE_MODE=hpos "$container" php /tmp/digicyfr-company-checkout-site.php seed
sudo -n docker cp "$archive" "$container:/tmp/digicyfr-plugin.zip"
sudo -n docker exec -e "DIGICYFR_TEST_PORT=$port" -e DIGICYFR_PLUGIN_SLUG=digicyfr-company-checkout-fields-pl "$container" php /tmp/digicyfr-smoke.php
sudo -n docker exec -e "DIGICYFR_TEST_PORT=$port" "$container" php /tmp/digicyfr-wp-contract.php
