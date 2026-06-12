# Treabo VPS Deploy Draft

This is a first Docker-based VPS setup for Treabo while DNS and Cloudflare are being configured.

Expected server folder layout:

```text
/var/www/treabo
  pixer-api
  shop
  admin
```

## 1. Install Docker on Ubuntu

Run as `root`:

```bash
curl -fsSL https://raw.githubusercontent.com/mikhaleff8230/treabo-pixer/main/deploy/server/install-docker-ubuntu.sh -o /tmp/install-docker-ubuntu.sh
bash /tmp/install-docker-ubuntu.sh
```

## 2. Clone Projects

```bash
mkdir -p /var/www/treabo
cd /var/www/treabo

git clone git@github.com:mikhaleff8230/treabo-pixer.git pixer-api
git clone git@github.com:mikhaleff8230/treabo-shop.git shop
git clone git@github.com:mikhaleff8230/treabo-admin.git admin
```

If SSH keys are not configured on the server yet, use HTTPS clone URLs first.

## 3. Create Env Files

```bash
cd /var/www/treabo

cp pixer-api/.env.production.example pixer-api/.env.production
cp pixer-api/deploy/env/mysql.env.example pixer-api/deploy/env/mysql.env
cp shop/.env.production.example shop/.env.production
cp admin/.env.production.example admin/.env.production
```

Then edit all copied files:

```bash
nano pixer-api/.env.production
nano pixer-api/deploy/env/mysql.env
nano shop/.env.production
nano admin/.env.production
```

Required secrets:

- `APP_KEY`
- MySQL passwords
- `PROFFI_ADMIN_TOKEN`
- OpenAI key
- Yandex/Google OAuth keys if enabled
- DaData/Yandex Geo keys
- Cloudflare R2 keys after bucket is ready

Generate Laravel key:

```bash
cd /var/www/treabo/pixer-api
docker compose -p treabo -f deploy/treabo-compose.yml run --rm api sh -lc "COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader && php artisan key:generate --show"
```

Paste the generated value into `APP_KEY=`.

## 4. Start HTTP Stack

From backend repo:

```bash
cd /var/www/treabo/pixer-api
export TREABO_DOMAIN=treabo.example.com
export TREABO_ADMIN_DOMAIN=admin.treabo.example.com
export TREABO_API_DOMAIN=api.treabo.example.com

docker compose -p treabo -f deploy/treabo-compose.yml up -d --build
```

Check:

```bash
docker compose -p treabo -f deploy/treabo-compose.yml ps
docker compose -p treabo -f deploy/treabo-compose.yml logs -f api
```

## 5. Update From Git

```bash
cd /var/www/treabo/pixer-api && git pull
cd /var/www/treabo/shop && git pull
cd /var/www/treabo/admin && git pull

cd /var/www/treabo/pixer-api
docker compose -p treabo -f deploy/treabo-compose.yml up -d --build
```

## 6. HTTPS

For the first launch this compose exposes HTTP on port `80`.
After DNS is active, add HTTPS through one of these paths:

- Cloudflare proxy + Flexible/Full SSL for quick start.
- Certbot on the VPS for direct Let's Encrypt certificates.
- Traefik/Caddy as reverse proxy later.

Cloudflare R2 image CDN can be enabled independently by setting:

```env
PROFFI_UPLOAD_DISK=r2
CLOUDFLARE_R2_PUBLIC_URL=https://cdn.your-domain
```
