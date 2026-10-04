import { Counter } from 'k6/metrics';

const known = [200, 201, 202, 401, 403, 409, 422, 429, 500, 502, 503];
const counters = {};

for (const status of known) {
  counters[status] = new Counter(`status_${status}`);
}

const other = new Counter('status_other');

export function record(response) {
  const counter = counters[response.status];

  if (counter) {
    counter.add(1);
  } else {
    other.add(1);
  }
}

export function jsonHeaders(token) {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  return { headers };
}

export function baseUrl() {
  const url = __ENV.BASE_URL;

  if (!url) {
    throw new Error('BASE_URL is required');
  }

  return url.replace(/\/$/, '');
}
