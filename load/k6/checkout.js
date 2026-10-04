import http from 'k6/http';
import { check } from 'k6';
import { baseUrl, jsonHeaders, record } from './lib.js';

export const options = {
  scenarios: {
    checkout: {
      executor: 'shared-iterations',
      vus: 8,
      iterations: 40,
      maxDuration: '3m',
    },
  },
};

export function setup() {
  const response = http.get(`${baseUrl()}/api/v1/products?per_page=100`, jsonHeaders());
  const products = response.json('data') || [];
  const product = products.find((item) => item.slug === 'usb-c-cable');

  if (!product || !product.skus || !product.skus[0]) {
    throw new Error('Checkout setup could not find the USB-C cable SKU');
  }

  return { skuId: product.skus[0].id };
}

export default function (data) {
  const login = http.post(
    `${baseUrl()}/api/v1/auth/wechat`,
    JSON.stringify({ code: `k6-checkout-${__VU}-${__ITER}`, name: 'Load Test' }),
    jsonHeaders(),
  );
  record(login);

  const token = login.json('token');
  const cart = http.post(
    `${baseUrl()}/api/v1/cart/items`,
    JSON.stringify({ sku_id: data.skuId, quantity: 1 }),
    jsonHeaders(token),
  );
  record(cart);

  const order = http.post(`${baseUrl()}/api/v1/orders`, null, jsonHeaders(token));
  record(order);

  const paid = http.post(
    `${baseUrl()}/api/v1/orders/${order.json('data.id')}/pay`,
    JSON.stringify({ channel: 'stripe' }),
    jsonHeaders(token),
  );
  record(paid);

  check(order, { 'checkout is 201': (response) => response.status === 201 });
  check(paid, { 'fake pay is 200': (response) => response.status === 200 });
}

export function handleSummary(data) {
  return {
    'load/results/checkout.json': JSON.stringify(data),
  };
}
