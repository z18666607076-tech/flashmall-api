import http from 'k6/http';
import { check } from 'k6';
import { baseUrl, jsonHeaders, record } from './lib.js';

export const options = {
  scenarios: {
    flash: {
      executor: 'shared-iterations',
      vus: 24,
      iterations: 24,
      maxDuration: '2m',
    },
  },
};

export function setup() {
  const response = http.get(`${baseUrl()}/api/v1/flash-sales`, jsonHeaders());
  const sales = response.json('data') || [];

  if (!sales[0]) {
    throw new Error('Flash-sale setup found no open sale');
  }

  return { saleId: sales[0].id };
}

export default function (data) {
  const login = http.post(
    `${baseUrl()}/api/v1/auth/wechat`,
    JSON.stringify({ code: `k6-flash-${__VU}-${__ITER}`, name: 'Load Test' }),
    jsonHeaders(),
  );
  record(login);

  const purchase = http.post(
    `${baseUrl()}/api/v1/flash-sales/${data.saleId}/purchase`,
    JSON.stringify({ quantity: 1 }),
    jsonHeaders(login.json('token')),
  );
  record(purchase);

  check(purchase, {
    'purchase is accepted or sold out': (response) => response.status === 202 || response.status === 409,
  });
}

export function handleSummary(data) {
  return {
    'load/results/flash-sale.json': JSON.stringify(data),
  };
}
