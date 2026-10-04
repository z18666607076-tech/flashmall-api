# Deploying FlashMall on CentOS

This guide is for a manual deploy on the owner's server: CentOS 7, or CentOS Stream / Rocky Linux 8 or 9, in mainland China, 4 vCPU and 4 GB RAM, with a domain name. You clone the repository on the server and start it yourself. There is no GitHub Actions SSH deploy.

The production stack is PHP-FPM behind Caddy, plus MySQL 8.4, Redis 7, a Horizon worker, and a scheduler. PHP-FPM is a better fit than Laravel Octane or FrankenPHP worker mode on 4 GB of RAM: each request is a short-lived worker capped at 8 processes, so MySQL can keep a 512 MB buffer pool without the framework staying resident in several long-lived workers. Caddy terminates TLS and renews certificates for the domain in `SITE_ADDRESS`.

Live WeChat Pay and Stripe credentials are not part of this demo. `DEMO_MODE=true` forces the fake drivers.

## What you do on the server

1. Finish ICP filing for the domain, or the cloud provider will block public ports 80 and 443.
2. Point the domain's A record at this server.
3. Install Docker Engine and the Compose plugin. On CentOS 7, use the last Engine build that still publishes `el7` packages.
4. Configure a Docker registry mirror, and Composer / apt mirrors if image builds are slow.
5. Open ports 80 and 443 in firewalld. Do not open 3306 or 6379.
6. Clone the repo, copy `.env.prod.example` to `.env.prod`, and set `APP_KEY`, both database passwords, `APP_DOMAIN`, `SITE_ADDRESS`, and `ACME_EMAIL`.
7. Run `docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build`.
8. Open `https://<your-domain>/api/v1/health`.

Details for each step are below.

## ICP filing

A website served from a mainland China server on ports 80 or 443 needs an ICP filing (ICP 备案) with the MIIT, usually submitted through the cloud vendor (Alibaba Cloud, Tencent Cloud, Huawei Cloud, and so on). Until that filing is approved and bound to this server's IP, the vendor commonly drops inbound 80/443 even when firewalld is open. Filing needs a domain that can be filed and the documents the vendor asks for. It takes days to weeks. The containers can still answer on localhost before the filing is approved; the public domain will not.

## Install Docker

The commands use `yum` on CentOS 7 and `dnf` on Stream 8/9 and Rocky 8/9. Run them as root.

### CentOS 7

CentOS 7 is past end of life (June 2024). Prefer Rocky Linux 8 or 9, or CentOS Stream 9, when you can reinstall. If this machine must stay on 7:

- Base packages come from the vault, for example `https://mirrors.aliyun.com/centos-vault/7.9.2009/`, because `mirror.centos.org` no longer serves them.
- Docker Engine 27 and newer do not publish `el7` packages. Install the newest `docker-ce` 26.x (or 25.x) that the mirror still lists.

```bash
yum install -y yum-utils device-mapper-persistent-data lvm2
yum-config-manager --add-repo https://mirrors.aliyun.com/docker-ce/linux/centos/docker-ce.repo
yum makecache
yum list docker-ce --showduplicates | sort -r
yum install -y docker-ce-26.1.4 docker-ce-cli-26.1.4 containerd.io docker-compose-plugin
```

If `26.1.4` is not in the list, install the highest `26.*` or `25.*` version the mirror shows. Do not install a version the repo does not have.

If `docker-compose-plugin` is missing for el7, download a Compose v2 binary from a mirror you trust and install it as `/usr/local/lib/docker/cli-plugins/docker-compose`, mode `0755`.

### CentOS Stream 8/9 and Rocky Linux 8/9

```bash
dnf install -y dnf-plugins-core device-mapper-persistent-data lvm2
dnf config-manager --add-repo https://mirrors.aliyun.com/docker-ce/linux/centos/docker-ce.repo
dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

Rocky may need the repo's `$releasever` to match a CentOS version the Docker repo still builds (often `8` or `9`). If `dnf` cannot find the packages, set `releasever` in that repo file to `9` (or `8`) and try again.

### Start Docker

```bash
systemctl enable --now docker
docker compose version
```

## Mirrors

Public Docker Hub, Debian, and Packagist are often slow or blocked from mainland China. Set these before the first build.

### Docker registry

Edit `/etc/docker/daemon.json`. Use the accelerator URL from your cloud account when you have one. `https://docker.m.daocloud.io` is a public fallback and public mirror hostnames change, so replace it when your vendor gives you a stable accelerator.

```json
{
  "registry-mirrors": ["https://docker.m.daocloud.io"],
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" }
}
```

```bash
systemctl restart docker
```

Image names in the compose file stay `mysql:8.4`, `redis:7-alpine`, and so on. The daemon pulls them through the mirror. You can still override an image with `MYSQL_IMAGE` or `PHP_IMAGE` in `.env.prod` if a mirror only works as a registry prefix.

### Composer and apt

In `.env.prod`:

```bash
APT_MIRROR=mirrors.aliyun.com
COMPOSER_MIRROR=https://mirrors.aliyun.com/composer/
```

`APT_MIRROR` rewrites `deb.debian.org` and `security.debian.org` inside the PHP image build. `COMPOSER_MIRROR` is passed to Composer as the Packagist repository. Other mirrors that work the same way: `mirrors.cloud.tencent.com`, `mirrors.huaweicloud.com`, and `https://mirrors.cloud.tencent.com/composer/`.

### npm

The production image does not run npm. Horizon's UI is served from the PHP package. If you later build frontend assets on this server:

```bash
npm config set registry https://registry.npmmirror.com
```

## firewalld

```bash
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload
systemctl restart docker
```

Restart Docker after a firewall reload. firewalld rebuilds iptables and can drop the rules Docker added for published ports.

Do not open MySQL or Redis. The compose file does not publish 3306 or 6379.

Confirm:

```bash
firewall-cmd --list-services
```

## SELinux

Stream and Rocky default to enforcing. CentOS 7 often does too. Leave it enforcing.

```bash
getenforce
```

This stack keeps the application code inside the image and stores MySQL, Redis, Caddy certificates, and Laravel `storage/` on named volumes. Docker labels those volumes itself, so a normal start does not need a policy change.

If you bind-mount a file from the host (a custom Caddyfile, or the repo into the container), add the shared label:

```yaml
volumes:
  - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile:ro,z
```

If PHP cannot write `storage/` or Caddy cannot read a bind-mounted file, check the denial before disabling SELinux:

```bash
ausearch -m avc -ts recent
```

`setsebool -P container_manage_cgroup on` is only for containers that run systemd. This stack does not.

## First boot

```bash
git clone https://github.com/z18666607076-tech/flashmall-api.git
cd flashmall-api
cp .env.prod.example .env.prod
chmod 600 .env.prod
```

Edit `.env.prod`:

- `APP_KEY`: 32 random bytes, base64-encoded, prefixed with `base64:`. From any machine with PHP 8.4: `php artisan key:generate --show`. Or: `docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'`
- `DB_PASSWORD` and `DB_ROOT_PASSWORD`: long random strings. They are not the local demo password.
- `APP_URL`, `APP_DOMAIN`, and `SITE_ADDRESS`: the public hostname, with `https://` only on `APP_URL`.
- `ACME_EMAIL`: a mailbox you read. Let's Encrypt uses it for expiry notices.

Then:

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.prod -f docker-compose.prod.yml ps
```

The containers read `ENV_FILE` when it is set, and `.env.prod` otherwise. Leave `ENV_FILE` unset on the server. `--env-file .env.prod` is what fills the MySQL passwords in the compose file.

The app container migrates and seeds on start. With `DEMO_MODE=true` the seed is the English catalog, one open flash sale (10 units of Wireless Earbuds), and a demo shopper. Log in with `POST /api/v1/auth/wechat` and `{"code":"demo","name":"Demo Shopper"}`. The code is `DEMO_LOGIN_CODE`. The demo user is not an admin.

Catalog writes, flash-sale management, and order ship / complete / refund return 403 while demo mode is on. Cart, checkout, fake pay, and flash-sale purchase stay open, with the rate limits in `.env.prod`.

Every day at `DEMO_RESET_AT` in `DEMO_RESET_TIMEZONE` (default 03:00 Asia/Shanghai) the scheduler runs `php artisan demo:reset --force`. That deletes demo orders, carts, tokens, and non-admin users, then reseeds the catalog and flash sale. Admin users are kept. The command refuses to run when `DEMO_MODE` is false, so a later real store is not wiped by the schedule.

Horizon listens on the Docker network only. Caddy answers `/horizon` with 404. MySQL and Redis are not published.

## Memory

Container limits, sized so the 4 GB machine still has room for the OS:

| Service | Limit | Why |
| --- | --- | --- |
| MySQL | 1100 MB | `innodb_buffer_pool_size=512M`, performance schema off, 80 connections |
| php-fpm | 800 MB | `memory_limit=128M`, at most 8 workers |
| Horizon | 400 MB | `HORIZON_MAX_PROCESSES=2`, 128 MB per worker |
| Redis | 320 MB | `maxmemory 256mb`, `noeviction` so flash-sale keys are not dropped |
| scheduler | 180 MB | `schedule:work` |
| Caddy | 128 MB | TLS and the reverse proxy |

Opcache is on and `validate_timestamps=0`. A code change is picked up by rebuilding the image, not by editing files on the host.

## HTTPS from mainland China

Caddy uses the HTTP-01 challenge on port 80. That requires the ICP path to be open and `SITE_ADDRESS` to resolve to this server. Let's Encrypt's API (`acme-v02.api.letsencrypt.org`, outbound 443) is usually reachable from China and sometimes slow. If issuance fails, read the Caddy log and confirm outbound 443 and inbound 80. Certificates are stored in the `caddy_data` volume and renew on their own.

To bring the stack up before DNS exists, set `CADDY_AUTO_HTTPS=off` and `SITE_ADDRESS=:80`, and call `http://127.0.0.1/api/v1/health` on the server. Turn automatic HTTPS back on before the public launch.

## Update

```bash
cd /path/to/flashmall-api
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T mysql \
  mysqldump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction flashmall \
  | gzip > "/root/flashmall-$(date +%F).sql.gz"
git pull
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

Take the dump before `git pull` when the database name or root password differs from the example. Read `DB_DATABASE` and `DB_ROOT_PASSWORD` from `.env.prod`. The app container runs `php artisan migrate --force` on the next start.

## Backup

The dump above is the backup that matters. Also keep the named volumes `flashmall_mysql_data` and `flashmall_caddy_data` (certificates). Redis holds flash-sale stock and the queue; MySQL is the source of truth after `flash-sales:reconcile`, which the scheduler runs every minute.

A host cron is enough:

```bash
0 4 * * * cd /path/to/flashmall-api && docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T mysql mysqldump -uroot -p"$(grep '^DB_ROOT_PASSWORD=' .env.prod | cut -d= -f2-)" --single-transaction "$(grep '^DB_DATABASE=' .env.prod | cut -d= -f2-)" | gzip > /root/flashmall-$(date +\%F).sql.gz
```

Copy those files off the server. A dump that only exists on the same disk is not a backup.

## Rollback

1. Stop and restore the dump taken before the update.
2. Check out the previous git revision and build again.

```bash
gunzip -c /root/flashmall-YYYY-MM-DD.sql.gz | docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T mysql \
  mysql -uroot -p"$DB_ROOT_PASSWORD" flashmall
git checkout <previous-revision>
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

Migrations in this repository are forward-only. Restoring the dump is the rollback. Do not run `migrate:rollback` against a production database you have not dumped.

## Useful commands

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f app caddy
docker compose --env-file .env.prod -f docker-compose.prod.yml exec app php artisan demo:reset --force
docker compose --env-file .env.prod -f docker-compose.prod.yml down
```

`down` keeps the volumes. `down -v` deletes the database and certificates.

## What this guide does not do

Deploy stays on the server: `git pull` and Compose. No SSH key, host, or user is stored in GitHub, and there is no deploy workflow to enable.
