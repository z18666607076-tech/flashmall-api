# Load test

These numbers were measured. They are not estimates, and they were not taken on the owner's CentOS server.

## Machine

Collected at the start of `./load/run.sh` on 2026-10-04T09:30:01Z.

| Item | Value |
| --- | --- |
| Kernel | Linux 6.12.94+ SMP PREEMPT_DYNAMIC, x86_64 |
| CPU | `Intel(R) Xeon(R) Processor`, 4 cores (`nproc` = 4). `/proc/cpuinfo` does not name a more specific model. |
| RAM | `MemTotal` 16,398,384 kB. At the start of the run, `MemAvailable` was 4,845,864 kB. |
| k6 | 1.4.2 (commit 5b725e8a6a) |
| Target | `http://127.0.0.1:8088` |

The host has about 16 GB of RAM, not 4 GB. The production compose limits were still applied: MySQL 1100 MB, php-fpm 800 MB, Horizon 400 MB, Redis 320 MB, scheduler 180 MB, Caddy 128 MB. The local development compose project was stopped before the run so it would not share the CPU. Other processes on this VM were still using memory; available RAM at the start was about 4.6 GB.

Every service reported healthy before the scenarios: PHP-FPM, Caddy, Horizon, the scheduler, MySQL 8.4, and Redis 7. `GET /api/v1/health` returned `{"status":"ok","app":"FlashMall"}`. `GET /horizon` returned 404.

## How the stack differed from the public demo

The same `docker-compose.prod.yml` was used, with `DEMO_MODE=true`, fake WeChat login, and the fake payment driver. Automatic HTTPS was off (`CADDY_AUTO_HTTPS=off`, `SITE_ADDRESS=:80`) because this VM has no public domain.

Rate limits were raised so the run would measure PHP, MySQL, and Redis rather than the per-IP cap. The public demo defaults were not in effect:

| Limit | Public demo default | This run |
| --- | --- | --- |
| API | 60 / minute | 100000 / minute |
| WeChat login | 10 / minute | 100000 / minute |
| Checkout | 20 / minute | 100000 / minute |
| Flash-sale purchase | 30 / minute | 100000 / minute |

A guest on the public demo is still limited to 60 API requests per minute per IP. Do not read the catalog rate below as the public demo's capacity.

`http_req_duration` below is the k6 metric in milliseconds for every request in that scenario, including setup only where the script recorded it. k6's summary export for this version included p90 and p95, not p99. Values are rounded to 2 decimal places from `load/results/*.json`.

## Catalog read

Script: `load/k6/catalog.js`. 10 constant VUs for 30 seconds. Each iteration calls `GET /api/v1/products?per_page=100` and `GET /api/v1/products/{id}` for the USB-C cable. One setup request is included in the request count.

| Metric | Measured |
| --- | --- |
| Iterations | 4719 (156.58 / s) |
| HTTP requests | 9439 (313.20 / s) |
| HTTP 200 | 9439 |
| Checks | 9438 passed, 0 failed |
| `http_req_failed` | 0 |
| Duration min / median / avg | 4.37 ms / 6.12 ms / 6.44 ms |
| Duration p90 / p95 / max | 7.96 ms / 9.26 ms / 78.74 ms |

## Checkout

Script: `load/k6/checkout.js`. 40 iterations shared across 8 VUs. Each iteration logs in with a fresh fake WeChat code, adds one USB-C cable to the cart, checks out, and pays with the fake Stripe driver. k6 reported the scenario complete at 0.8 s.

| Metric | Measured |
| --- | --- |
| Iterations | 40 (51.53 / s) |
| HTTP requests | 161 (207.41 / s), including one uncounted setup `GET /products` |
| HTTP 200 | 120 (login, add to cart, pay) |
| HTTP 201 | 40 (checkout) |
| Checks | 80 passed, 0 failed (`checkout is 201`, `fake pay is 200`) |
| `http_req_failed` | 0 |
| Duration min / median / avg | 8.71 ms / 34.53 ms / 36.01 ms |
| Duration p90 / p95 / max | 49.67 ms / 58.47 ms / 74.89 ms |

The seeded cable stock is 200. Forty orders did not exhaust it.

## Flash-sale purchase

Script: `load/k6/flash-sale.js`. 24 iterations, one per VU, started together. Each VU logs in as a new user and sends `POST /api/v1/flash-sales/{id}/purchase` with quantity 1. The seeded sale holds 10 units and allows one unit per user. k6 reported the scenario complete at 0.2 s.

| Metric | Measured |
| --- | --- |
| Iterations | 24 |
| HTTP requests | 49 (223.21 / s), including one setup `GET /flash-sales` |
| HTTP 200 | 24 (logins) |
| HTTP 202 | 10 (purchase accepted) |
| HTTP 409 | 14 (sold out) |
| Checks | 24 passed, 0 failed (`accepted or sold out`) |
| `http_req_failed` | 0.2857 |

k6 counts HTTP 409 as a failed request, so `http_req_failed` is 14/49. Those 14 responses are the sold-out path. Ten accepts and fourteen rejections equal the 24 buyers, and the ten accepts equal `total_stock`.

Duration for every request in the scenario:

| | min | median | avg | p90 | p95 | max |
| --- | --- | --- | --- | --- | --- | --- |
| All requests | 10.33 ms | 83.02 ms | 80.91 ms | 121.80 ms | 123.06 ms | 132.47 ms |
| Responses k6 treated as expected (not 409) | 10.33 ms | 67.20 ms | 65.57 ms | 112.56 ms | 121.67 ms | 132.47 ms |

## Run it again

```bash
./load/run.sh
```

The script writes `/tmp/flashmall-load.env`, builds `docker-compose.prod.yml` as project `flashmall-load`, waits for health, then runs the three scripts. Summary JSON is written to `load/results/`. Re-running the flash-sale script against the same database returns sold out for every buyer until `php artisan demo:reset --force` restores the 10 units.
