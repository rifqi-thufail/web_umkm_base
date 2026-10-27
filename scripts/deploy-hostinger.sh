#!/usr/bin/env bash
# Deploy to Hostinger shared hosting (no VPS needed).
#
#   scripts/deploy-hostinger.sh                       production, https://ampuh.finance
#   scripts/deploy-hostinger.sh staging               staging, https://staging.ampuh.finance
#   scripts/deploy-hostinger.sh staging --seed-demo   staging + demo stores, products and test logins
#
# Server layout: the app lives in <docroot>/web_umkm and <docroot>/index.php boots it;
# <docroot>/.htaccess serves /build and /storage from inside web_umkm.
# The repo's root .htaccess is deployed to web_umkm/ and refuses everything else there.
# The server's .env, storage/ and .git are left untouched.
# Production's docroot files pre-date this script and are not touched; staging's come from
# scripts/hostinger/staging-root/. First-time staging setup: docs/STAGING.md.
set -euo pipefail

TARGET="${1:-production}"
SEED_DEMO="${2:-}"
HOST="u595677082@153.92.10.3"
SSH="ssh -o BatchMode=yes -p 65002 $HOST"

case "$TARGET" in
  production)
    DOCROOT="domains/ampuh.finance/public_html"
    URL="https://ampuh.finance"
    ;;
  staging)
    DOCROOT="${STAGING_DOCROOT:-domains/ampuh.finance/public_html/staging}"
    URL="${STAGING_URL:-https://staging.ampuh.finance}"
    ;;
  *)
    echo "usage: $0 [production|staging] [--seed-demo]" >&2
    exit 2
    ;;
esac
APP_DIR="$DOCROOT/web_umkm"

if [ -n "$SEED_DEMO" ] && { [ "$SEED_DEMO" != "--seed-demo" ] || [ "$TARGET" != "staging" ]; }; then
  echo "!! --seed-demo is only allowed for staging" >&2
  exit 2
fi

cd "$(dirname "$0")/.."

echo "==> [$TARGET] Checking server"
$SSH DOCROOT="$DOCROOT" TARGET="$TARGET" bash -s <<'EOF'
set -e
[ -d ~/"$DOCROOT" ] || { echo "!! ~/$DOCROOT does not exist. Create the subdomain in hPanel first (docs/STAGING.md)."; exit 1; }
mkdir -p ~/"$DOCROOT"/web_umkm
cd ~/"$DOCROOT"/web_umkm
[ -f .env ] || { echo "!! $PWD/.env is missing. Upload it from .env.$TARGET.example first (docs/STAGING.md)."; exit 1; }
grep -q "^APP_ENV=$TARGET\$" .env || { echo "!! $PWD/.env does not say APP_ENV=$TARGET; refusing to deploy."; exit 1; }
EOF

echo "==> Building frontend"
npm run build

echo "==> Backing up database"
$SSH DOCROOT="$DOCROOT" TARGET="$TARGET" bash -s <<'EOF'
set -e
cd ~/"$DOCROOT"/web_umkm
set -a; . ./.env; set +a
mkdir -p ~/backups
if [ -d vendor ]; then
  mysqldump --single-transaction -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
    | gzip > ~/backups/db-$TARGET-$(date +%Y%m%d-%H%M).sql.gz
  php artisan down --retry=30 || true
fi
EOF

echo "==> Uploading code"
rsync -az --delete -e "ssh -p 65002" \
  --exclude /.git --exclude /node_modules --exclude /vendor \
  --exclude /.env --exclude '/.env.*' --exclude /storage --exclude /public/storage --exclude /public/hot \
  --exclude /blockchain --exclude /.claude --exclude '/database/*.sqlite' \
  --exclude /error_log --exclude /.phpunit.result.cache \
  ./ "$HOST:$APP_DIR/"

if [ "$TARGET" = "staging" ]; then
  echo "==> Uploading staging document root"
  rsync -az -e "ssh -p 65002" \
    scripts/hostinger/staging-root/ public/favicon.ico "$HOST:$DOCROOT/"
fi

echo "==> Installing and migrating on server"
$SSH DOCROOT="$DOCROOT" SEED_DEMO="$SEED_DEMO" bash -s <<'EOF'
set -e
cd ~/"$DOCROOT"/web_umkm
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
php artisan migrate --force
if [ "$SEED_DEMO" = "--seed-demo" ]; then
  php artisan db:seed --class=CategorySeeder --force
  php artisan db:seed --class=DemoSeeder --force
fi
php artisan optimize
php artisan up
EOF

echo "==> Checking"
curl -s -o /dev/null -w "/up %{http_code}\n" "$URL/up"
if [ "$TARGET" = "production" ]; then
  curl -s -o /dev/null -w "/transparansi %{http_code}\n" "$URL/transparansi"
else
  # The gate must refuse visitors without the access key.
  code=$(curl -s -o /dev/null -w "%{http_code}" "$URL/")
  echo "/ without key $code"
  [ "$code" = "403" ] || { echo "!! staging is reachable without the access key ($code)"; exit 1; }
fi
# The app tree must stay unreachable; abort loudly if the deny rule stopped working.
for p in /web_umkm/.env /web_umkm/composer.json /web_umkm/storage/logs/laravel.log; do
  code=$(curl -s -o /dev/null -I -w "%{http_code}" "$URL$p")
  echo "$p $code"
  [ "$code" = "403" ] || [ "$code" = "404" ] || { echo "!! $p is reachable ($code)"; exit 1; }
done
