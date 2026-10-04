#!/bin/sh
# Run the three k6 scenarios against a prod-like stack.
# Usage: ./load/run.sh
# Override the target with BASE_URL=http://127.0.0.1:8088

set -eu

cd "$(dirname "$0")/.."

if ! command -v k6 >/dev/null 2>&1; then
    echo "k6 is not installed. See docs/LOAD_TEST.md." >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "docker is not installed." >&2
    exit 1
fi

BASE_URL="${BASE_URL:-http://127.0.0.1:8088}"
ENV_FILE="${ENV_FILE:-/tmp/flashmall-load.env}"
PROJECT="${COMPOSE_PROJECT_NAME:-flashmall-load}"
HTTP_PORT="${HTTP_PORT:-8088}"

mkdir -p load/results

{
    echo "date_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "uname=$(uname -a)"
    echo "nproc=$(nproc)"
    echo "meminfo:"
    sed -n '1,5p' /proc/meminfo
    echo "cpu:"
    grep -m1 'model name' /proc/cpuinfo || true
    grep -m1 'cpu cores' /proc/cpuinfo || true
    echo "k6=$(k6 version)"
} > load/results/hardware.txt

if [ ! -f "$ENV_FILE" ]; then
    cat > "$ENV_FILE" <<EOF
APP_NAME=FlashMall
APP_ENV=production
APP_KEY=base64:AXi+IBFffA0Fn1oNU1TQHTkEA9B1Rag+sMjInD17x8I=
APP_DEBUG=false
APP_URL=${BASE_URL}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_DATABASE=flashmall
DB_USERNAME=flashmall
DB_PASSWORD=loadtest-db-secret
DB_ROOT_PASSWORD=loadtest-root-secret
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_PASSWORD=null
WECHAT_DRIVER=fake
PAYMENTS_DRIVER=fake
DEMO_MODE=true
DEMO_LOGIN_CODE=demo
DEMO_RESET_AT=03:00
DEMO_RESET_TIMEZONE=Asia/Shanghai
TRUST_PROXIES=true
API_RATE_LIMIT_PER_MINUTE=100000
WECHAT_LOGIN_RATE_LIMIT_PER_MINUTE=100000
CHECKOUT_RATE_LIMIT_PER_MINUTE=100000
FLASH_PURCHASE_RATE_LIMIT_PER_MINUTE=100000
HORIZON_MAX_PROCESSES=2
ORDER_UNPAID_TTL_SECONDS=900
CATALOG_CACHE_TTL=600
SITE_ADDRESS=:80
CADDY_AUTO_HTTPS=off
ACME_EMAIL=loadtest@example.com
HTTP_PORT=${HTTP_PORT}
HTTPS_PORT=8443
EOF
fi

echo "Starting the prod-like stack (project ${PROJECT})..."
export ENV_FILE
docker compose --env-file "$ENV_FILE" -p "$PROJECT" -f docker-compose.prod.yml up -d --build

echo "Waiting for ${BASE_URL}/api/v1/health ..."
ready=0
i=0
while [ "$i" -lt 60 ]; do
    if curl -fsS "${BASE_URL}/api/v1/health" >/dev/null 2>&1; then
        ready=1
        break
    fi
    i=$((i + 1))
    sleep 2
done

if [ "$ready" -ne 1 ]; then
    echo "The stack did not become healthy." >&2
    docker compose --env-file "$ENV_FILE" -p "$PROJECT" -f docker-compose.prod.yml ps >&2 || true
    docker compose --env-file "$ENV_FILE" -p "$PROJECT" -f docker-compose.prod.yml logs --tail 80 >&2 || true
    exit 1
fi

export BASE_URL

echo "Catalog read"
k6 run load/k6/catalog.js

echo "Checkout"
k6 run load/k6/checkout.js

echo "Flash sale"
k6 run load/k6/flash-sale.js

echo "Summaries are in load/results/. Hardware notes are in load/results/hardware.txt."
