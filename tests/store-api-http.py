#!/usr/bin/env python3
"""Real HTTP Store API checkout checks for the disposable Woo 8.9 Block cell."""
import json
import sys
import http.cookiejar
import urllib.error
import urllib.request
from pathlib import Path

manifest = json.loads(Path(sys.argv[1]).read_text())
origin = manifest['site_url'].rstrip('/') + '/'
product_id = manifest['product_id']
opener = None


def request(route, method='GET', data=None, token=None, nonce=None):
    url = origin + '?rest_route=%2Fwc%2Fstore%2Fv1%2F' + route.replace('/', '%2F')
    headers = {'Accept': 'application/json'}
    if token:
        headers['Cart-Token'] = token
    if nonce:
        headers['Nonce'] = nonce
    if data is not None:
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(
        url,
        data=None if data is None else json.dumps(data).encode(),
        headers=headers,
        method=method,
    )
    try:
        with opener.open(req, timeout=30) as response:
            return response.status, dict(response.headers), json.loads(response.read())
    except urllib.error.HTTPError as error:
        return error.code, dict(error.headers), json.loads(error.read())


def check(condition, message):
    if not condition:
        raise AssertionError(message)


outcomes = []
for case in ('personal', 'company', 'bad_checksum', 'nip_without_intent'):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    status, headers, cart = request('cart')
    token = headers.get('Cart-Token')
    nonce = headers.get('Nonce')
    check(status == 200 and token and nonce and cart.get('items') == [], 'fresh authenticated cart token and nonce')
    status, headers, cart = request('cart/add-item', 'POST', {'id': product_id, 'quantity': 1}, token, nonce)
    check(status in (200, 201) and len(cart.get('items', [])) == 1, f'add synthetic product: {status} {cart}')
    token = headers.get('Cart-Token', token)
    nonce = headers.get('Nonce', nonce)

    tag = case.replace('_', '-')
    billing = {
        'first_name': 'Synthetic',
        'last_name': tag,
        'company': 'Firma Testowa' if case == 'company' else '',
        'address_1': 'Testowa 1',
        'city': 'Warszawa',
        'postcode': '00-001',
        'country': 'PL',
        'email': f'store-api-{tag}@example.invalid',
    }
    intent = case in ('company', 'bad_checksum')
    nip = {
        'personal': '',
        'company': '012-345-6789',
        'bad_checksum': '0123456788',
        'nip_without_intent': '0123456789',
    }[case]
    payload = {
        'billing_address': billing,
        'shipping_address': billing,
        'payment_method': 'bacs',
        'additional_fields': {
            'digicyfr-company/intent': intent,
            'digicyfr-company/nip': nip,
        },
    }
    status, _, result = request('checkout', 'POST', payload, token, nonce)
    success = case in ('personal', 'company')
    if success:
        check(status == 200 and result.get('order_id', 0) > 0, f'{case} checkout: {status} {result}')
        check(result.get('status') not in ('checkout-draft', 'auto-draft'), f'{case} finalized')
    else:
        code = 'digicyfr_invalid_nip' if case == 'bad_checksum' else 'digicyfr_intent_required'
        check(status == 400 and code in json.dumps(result), f'{case} rejection: {status} {result}')
    outcomes.append({
        'case': case,
        'status': status,
        'order_id': result.get('order_id') if success else None,
        'error_code': result.get('code') if not success else None,
    })

print(json.dumps({'woo': manifest['woocommerce_version'], 'wordpress': manifest['wordpress_version'],
                  'mode': manifest['checkout_mode'], 'storage': manifest['order_storage'],
                  'outcomes': outcomes}))
