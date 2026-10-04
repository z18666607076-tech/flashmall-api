# 在 CentOS 上部署 FlashMall

这份说明面向手动部署：CentOS 7，或 CentOS Stream / Rocky Linux 8、9，机器在中国大陆，4 核 4 GB 内存，已有域名。在服务器上克隆仓库后自己启动。仓库里没有 GitHub Actions SSH 自动部署。

生产组合是 PHP-FPM、Caddy、MySQL 8.4、Redis 7、Horizon 和定时任务。4 GB 内存上用 PHP-FPM，而不是 Octane 或 FrankenPHP 常驻 Worker：请求由最多 8 个短生命周期进程处理，MySQL 可以保留 512 MB 缓冲池。Caddy 为 `SITE_ADDRESS` 申请并续期证书。

公开演示不使用真实的微信支付或 Stripe。`DEMO_MODE=true` 会强制走伪造驱动。

## 你需要在服务器上做的事

1. 先完成域名 ICP 备案。备案通过前，云厂商通常会拦截公网 80 和 443。
2. 把域名 A 记录指到这台机器。
3. 安装 Docker Engine 和 Compose 插件。CentOS 7 只能用仍提供 `el7` 包的最后一批 Engine。
4. 配置 Docker 镜像加速，以及 Composer、apt 镜像。
5. 用 firewalld 放行 80 和 443。不要放行 3306 和 6379。
6. 克隆仓库，把 `.env.prod.example` 复制为 `.env.prod`，填好 `APP_KEY`、两个数据库密码、`APP_DOMAIN`、`SITE_ADDRESS` 和 `ACME_EMAIL`。
7. 执行 `docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build`。
8. 打开 `https://<你的域名>/api/v1/health`。

## 备案

在中国大陆服务器上用 80/443 对外提供网站，需要通过云厂商向工信部做 ICP 备案，并把备案指向这台机器的 IP。备案完成前，即使 firewalld 已放行，公网端口仍可能不通。容器可以先在本机用 `curl http://127.0.0.1/api/v1/health` 自测。备案通常需要数天到数周。

## 安装 Docker

下面的命令用 root 执行。CentOS 7 用 `yum`，Stream 8/9 和 Rocky 8/9 用 `dnf`。

### CentOS 7

CentOS 7 已于 2024 年 6 月停止维护。能重装的话，用 Rocky Linux 8/9 或 CentOS Stream 9。必须留在 7 上时：

- 系统源改到 vault，例如 `https://mirrors.aliyun.com/centos-vault/7.9.2009/`。`mirror.centos.org` 已经不再提供这些包。
- Docker Engine 27 及以上没有 `el7` 包。安装镜像里还能看到的最高 `26.x`（或 `25.x`）。

```bash
yum install -y yum-utils device-mapper-persistent-data lvm2
yum-config-manager --add-repo https://mirrors.aliyun.com/docker-ce/linux/centos/docker-ce.repo
yum makecache
yum list docker-ce --showduplicates | sort -r
yum install -y docker-ce-26.1.4 docker-ce-cli-26.1.4 containerd.io docker-compose-plugin
```

列表里没有 `26.1.4` 时，装仓库里实际存在的最高 `26.*` 或 `25.*`。`docker-compose-plugin` 在 el7 上缺失时，把 Compose v2 二进制放到 `/usr/local/lib/docker/cli-plugins/docker-compose` 并 `chmod 0755`。

### CentOS Stream 8/9 与 Rocky Linux 8/9

```bash
dnf install -y dnf-plugins-core device-mapper-persistent-data lvm2
dnf config-manager --add-repo https://mirrors.aliyun.com/docker-ce/linux/centos/docker-ce.repo
dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

Rocky 上如果找不到包，把该仓库的 `$releasever` 改成 `9` 或 `8` 后再安装。

```bash
systemctl enable --now docker
docker compose version
```

## 镜像加速

### Docker

编辑 `/etc/docker/daemon.json`。优先用云账号里的专属加速地址。`https://docker.m.daocloud.io` 只是一个会变动的公共备用。

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

### Composer 与 apt

写在 `.env.prod` 里，构建时生效：

```bash
APT_MIRROR=mirrors.aliyun.com
COMPOSER_MIRROR=https://mirrors.aliyun.com/composer/
```

腾讯云、华为云镜像同样可用，例如 `mirrors.cloud.tencent.com` 和 `https://mirrors.cloud.tencent.com/composer/`。

### npm

生产镜像不执行 npm。以后如果在这台机器上构建前端资源：

```bash
npm config set registry https://registry.npmmirror.com
```

## firewalld 与 SELinux

```bash
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload
systemctl restart docker
```

改完防火墙要重启 Docker，否则已发布的端口规则可能被 firewalld 清掉。不要开放 MySQL 和 Redis。

保持 SELinux 为 Enforcing（`getenforce`）。代码在镜像里，数据在命名卷里，正常启动不用改策略。只有把宿主机文件挂进容器时，才在卷后面加 `,z`。出现权限错误先看 `ausearch -m avc -ts recent`，不要先 `setenforce 0`。

## 首次启动

```bash
git clone https://github.com/z18666607076-tech/flashmall-api.git
cd flashmall-api
cp .env.prod.example .env.prod
chmod 600 .env.prod
```

`.env.prod` 里至少改这些项：

- `APP_KEY`：32 字节随机数的 base64，前缀 `base64:`。有 PHP 8.4 时执行 `php artisan key:generate --show`。
- `DB_PASSWORD` 和 `DB_ROOT_PASSWORD`：换成足够长的随机密码。
- `APP_URL`、`APP_DOMAIN`、`SITE_ADDRESS`：你的域名。只有 `APP_URL` 带 `https://`。
- `ACME_EMAIL`：能收到 Let's Encrypt 过期提醒的邮箱。

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

`DEMO_MODE=true` 时会写入英文演示商品、一场限时抢购（无线耳机 10 件）和一个演示用户。登录接口 `POST /api/v1/auth/wechat`，请求体 `{"code":"demo"}`。该用户不是管理员。

演示模式下，后台改商品、管理抢购、发货、完成和退款都会返回 403。购物车、下单、伪造支付和抢购可以访问，并受 `.env.prod` 里的频率限制。每天 `DEMO_RESET_AT`（默认上海时间 03:00）会执行 `demo:reset`，清掉订单、购物车和非管理员用户，再重新种入商品和抢购。管理员账号会保留。`DEMO_MODE=false` 时这条命令拒绝执行。

Horizon、MySQL、Redis 都不映射到公网。访问 `/horizon` 会得到 404。

## 4 GB 内存

| 服务 | 上限 | 说明 |
| --- | --- | --- |
| MySQL | 1100 MB | 缓冲池 512 MB，关闭 performance schema，最多 80 个连接 |
| php-fpm | 800 MB | 每个进程 128 MB，最多 8 个进程 |
| Horizon | 400 MB | 2 个 worker |
| Redis | 320 MB | 256 MB，`noeviction`，避免抢购库存键被删 |
| 定时任务 | 180 MB | `schedule:work` |
| Caddy | 128 MB | 证书和反向代理 |

Opcache 关闭了时间戳校验。更新代码要重新构建镜像。

## 证书、更新、备份、回滚

Caddy 用 80 端口做 HTTP-01 验证。备案和域名解析都要先就绪。Let's Encrypt 的地址是 `acme-v02.api.letsencrypt.org`，从国内访问出站 443 有时较慢。证书在 `caddy_data` 卷里，会自动续期。域名还没解析好时，可暂时设 `CADDY_AUTO_HTTPS=off` 和 `SITE_ADDRESS=:80`，只在服务器本机访问。

更新前先备份：

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T mysql \
  mysqldump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction flashmall \
  | gzip > "/root/flashmall-$(date +%F).sql.gz"
git pull
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

数据库名和 root 密码以 `.env.prod` 为准。应用容器启动时会执行 `php artisan migrate --force`。请把备份拷到另一台机器。

回滚是恢复这份 SQL，再检出上一个 git 版本并重新构建。迁移只向前，不要对没备份的库执行 `migrate:rollback`。

```bash
gunzip -c /root/flashmall-YYYY-MM-DD.sql.gz | docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T mysql \
  mysql -uroot -p"$DB_ROOT_PASSWORD" flashmall
git checkout <上一个版本>
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

更完整的英文说明、防火墙重载后为什么要重启 Docker、以及 SELinux 挂载标签，见 [DEPLOY.md](DEPLOY.md)。本次在云端虚拟机上测得的 k6 数字见 [LOAD_TEST.md](LOAD_TEST.md)，那不是这台 4 GB 服务器上的结果。
