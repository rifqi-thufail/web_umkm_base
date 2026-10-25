#!/usr/bin/env bash
# Deploy to ampuh.finance on Hostinger shared hosting (no VPS needed).
#
#   scripts/deploy-hostinger.sh
#
# Server layout (pre-existing): the app lives in public_html/web_umkm and public_html/index.php
# boots it; public_html/.htaccess serves /build and /storage from inside web_umkm.
# The repo's root .htaccess is deployed to web_umkm/ and refuses everything else there.
# The server's .env, storage/ and .git are left untouched.
set -euo pipefail

SSH="ssh -o BatchMode=yes -p 65002 u595677082@153.92.10.3"
APP_DIR="domains/ampuh.finance/public_html/web_umkm"

cd "$(dirname "$0")/.."

echo "==> Building frontend"
npm run build

echo "==> Backing up database"
$SSH bash -s <<'EOF'
set -e
cd ~/domains/ampuh.finance/public_html/web_umkm
set -a; . ./.env; set +a
mkdir -p ~/backups
mysqldump --single-transaction -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > ~/backups/db-$(date +%Y%m%d-%H%M).sql.gz
php artisan down --retry=30 || true
EOF

echo "==> Uploading code"
rsync -az --delete -e "ssh -p 65002" \
  --exclude /.git --exclude /node_modules --exclude /vendor \
  --exclude /.env --exclude '/.env.*' --exclude /storage --exclude /public/storage --exclude /public/hot \
  --exclude /mpc --exclude /blockchain --exclude /.claude --exclude '/database/*.sqlite' \
  --exclude /error_log --exclude /.phpunit.result.cache \
  ./ "u595677082@153.92.10.3:$APP_DIR/"

echo "==> Installing and migrating on server"
$SSH bash -s <<'EOF'
set -e
cd ~/domains/ampuh.finance/public_html/web_umkm
composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
php artisan migrate --force
php artisan optimize
php artisan up
EOF

echo "==> Checking"
curl -s -o /dev/null -w "/up %{http_code}\n" https://ampuh.finance/up
curl -s -o /dev/null -w "/transparansi %{http_code}\n" https://ampuh.finance/transparansi
# The app tree must stay unreachable; abort loudly if the deny rule stopped working.
for p in /web_umkm/.env /web_umkm/composer.json /web_umkm/storage/logs/laravel.log; do
  code=$(curl -s -o /dev/null -I -w "%{http_code}" "https://ampuh.finance$p")
  echo "$p $code"
  [ "$code" = "403" ] || [ "$code" = "404" ] || { echo "!! $p is reachable ($code)"; exit 1; }
done
