# Setup & Deployment

Panduan step-by-step dari clone sampai online di belakang reverse proxy.

## 1. Setup lokal

```bash
git clone <repo-url> web_umkm && cd web_umkm
composer install
npm install
cp .env.example .env          # .env tidak ikut git; selalu mulai dari .env.example
php artisan key:generate
touch database/database.sqlite   # hanya jika DB_CONNECTION=sqlite
php artisan migrate --seed
php artisan storage:link
npm run dev                   # terminal 1
php artisan serve             # terminal 2 -> http://localhost:8000
```

Akun seed: `test@example.com` (lihat `database/seeders/DatabaseSeeder.php`).

Kunci API (Midtrans, CoinPayments, SMS) boleh kosong saat development.
Fitur yang butuh kunci tersebut hanya gagal di langkah pembayaran/hashing;
biarkan `BASE_ENABLED=false` jika tidak menjalankan Base lokal (lihat `blockchain/README.md`).

## 2. Migrasi database (MySQL / PostgreSQL)

1. Buat database dan user, contoh MySQL:
   ```sql
   CREATE DATABASE ampuh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'ampuh'@'127.0.0.1' IDENTIFIED BY '<password>';
   GRANT ALL ON ampuh.* TO 'ampuh'@'127.0.0.1';
   ```
2. Ubah `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ampuh
   DB_USERNAME=ampuh
   DB_PASSWORD=<password>
   ```
3. `php artisan config:clear && php artisan migrate --force`
4. Seed kategori awal: `php artisan db:seed --class=CategorySeeder --force`
5. Memindah data dari SQLite lama: ekspor per tabel (mis. `sqlite3 database/database.sqlite .dump`
   atau tool seperti DBeaver) lalu impor ke database baru **setelah** langkah 3.

## 3. Production build

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

`.env` production:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com
```

Lalu:

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Pastikan `storage/` dan `bootstrap/cache/` writable oleh user web server.
Untuk `QUEUE_CONNECTION=database`, jalankan worker (mis. via Supervisor):
`php artisan queue:work --tries=3`.

## 4. Reverse proxy (Nginx + PHP-FPM)

```nginx
server {
    listen 80;
    server_name domain-anda.com;
    root /var/www/web_umkm/public;
    index index.php;

    client_max_body_size 10M;   # upload gambar produk

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known) { deny all; }

    location ~* \.(?:css|js|woff2?|png|jpg|jpeg|webp|svg|ico)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

HTTPS: `sudo certbot --nginx -d domain-anda.com`.

### Jika Laravel berada di belakang proxy lain (Cloudflare, Caddy, load balancer)

Agar URL, redirect, dan callback pembayaran memakai `https`, percayai header proxy
di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*');
})
```

(ganti `'*'` dengan IP proxy jika diketahui).

### Callback pembayaran

Daftarkan URL berikut di dashboard gateway (harus publik dan HTTPS):

- Midtrans notification URL: `https://domain-anda.com/api/midtrans/callback`
- CoinPayments IPN: `https://domain-anda.com/api/coinpayments/ipn`

### Base (pencatatan on-chain)

Deploy `OrderRegistry` ke Base Sepolia atau Base mainnet dan isi `BASE_*` di `.env` production.
Langkah lengkap dan cara membuat dompet recorder ada di [blockchain/README.md](../blockchain/README.md).
Jangan pakai kunci anvil (`0xac0974…`) di jaringan publik.

## 5. Checklist setelah deploy

- [ ] `https://domain-anda.com/up` mengembalikan 200
- [ ] Upload gambar produk tampil (`storage:link` sudah dijalankan)
- [ ] Login user & seller berjalan
- [ ] Riwayat pesanan (`/riwayat-pesanan`) terbuka
- [ ] Callback pembayaran sandbox diterima
- [ ] `php artisan base:status` menunjukkan kontrak terpasang dan chain id sesuai
- [ ] Pesanan uji yang lunas muncul di `/transparansi` dan lolos `/verifikasi`
