import http from 'k6/http';
import { check, sleep } from 'k6';
import { baseUrl, jsonHeaders, record } from './lib.js';

export const options = {
  scenarios: {
    catalog: {
      executor: 'constant-vus',
      vus: 10,
      duration: '30s',
    },
  },
};

export function setup() {
  const response = http.get(`${baseUrl()}/api/v1/products?per_page=100`, jsonHeaders());
  record(response);

  const products = response.json('data') || [];
  const product = products.find((item) => item.slug === 'usb-c-cable') || products[0];

  if (!product) {
    throw new Error('Catalog setup found no products');
  }

  return { productId: product.id };
}

export default function (data) {
  const list = http.get(`${baseUrl()}/api/v1/products?per_page=100`, jsonHeaders());
  record(list);
  check(list, { 'product list is 200': (response) => response.status === 200 });

  const show = http.get(`${baseUrl()}/api/v1/products/${data.productId}`, jsonHeaders());
  record(show);
  check(show, { 'product show is 200': (response) => response.status === 200 });

  sleep(0.05);
}

export function handleSummary(data) {
  return {
    'load/results/catalog.json': JSON.stringify(data),
  };
}
