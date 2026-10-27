# Staging (staging.ampuh.finance)

Staging adalah salinan AMPUH untuk UAT: database sendiri, pembayaran sandbox Midtrans, Base Sepolia,
dan akun uji dari `DemoSeeder`. Situs ini publik di internet tetapi terkunci: hanya browser yang pernah
membuka tautan akses yang bisa masuk, dan mesin pencari diminta tidak mengindeks.

## Apa yang dijaga oleh kode

| Pengaman | Di mana |
| --- | --- |
| Tanpa `?access=<STAGING_ACCESS_KEY>` (atau cookie dari tautan itu) semua halaman 403; tanpa kunci di `.env` 503 | `app/Http/Middleware/StagingGate.php` |
| Webhook `/api/midtrans/callback`, `/api/coinpayments/ipn` dan `/up` tetap bisa diakses gateway | rute `api` di luar grup `web` |
| Midtrans dan DOKU selalu mode sandbox, apa pun isi `.env` | `AppServiceProvider::boot()` |
| Pencatatan Base ditolak di Base mainnet (chain 8453) | `AppServiceProvider::boot()` |
| `X-Robots-Tag: noindex` dan `robots.txt` `Disallow: /` | `SecurityHeaders`, `scripts/hostinger/staging-root/robots.txt` |
| Spanduk kuning "STAGING" di setiap halaman | `resources/js/app.tsx` |
| Kode OTP reset penjual tampil di layar bila `MAIL_MAILER=log` | `SellerPasswordResetController` |
| Deploy menolak jalan bila `.env` server tidak berisi `APP_ENV=staging` | `scripts/deploy-hostinger.sh` |

## Setup pertama (sekali, lewat hPanel)

1. **Subdomain.** hPanel → Domains → Subdomains → buat `staging` untuk `ampuh.finance`.
   Folder bawaannya `public_html/staging`. Jika hPanel memakai folder lain, jalankan deploy dengan
   `STAGING_DOCROOT=domains/<...>/public_html/<...>`.
2. **SSL.** hPanel → Security → SSL → pasang SSL gratis untuk `staging.ampuh.finance`.
3. **Database.** hPanel → Databases → Management → buat database dan user baru, misalnya
   `u595677082_staging`. Jangan pakai database production.
4. **.env.** Salin `.env.staging.example`, isi nilai `<...>`, lalu unggah ke
   `domains/ampuh.finance/public_html/staging/web_umkm/.env` (File Manager atau `scp -P 65002`).
   `APP_KEY` boleh kosong: deploy pertama membuatnya.
   Buat kunci akses dengan `openssl rand -hex 24`.
5. **Base Sepolia.** Deploy `OrderRegistry` khusus staging dengan dompet recorder baru
   (`blockchain/README.md`), isi `BASE_REGISTRY_ADDRESS` dan `BASE_RECORDER_PRIVATE_KEY`, lalu kirim
   sedikit ETH Sepolia ke dompet itu. Belum siap? Isi `BASE_ENABLED=false` dulu.
6. **Midtrans sandbox.** Di https://dashboard.sandbox.midtrans.com salin Server/Client key ke `.env`
   dan isi Notification URL `https://staging.ampuh.finance/api/midtrans/callback`.
7. **Cron.** hPanel → Advanced → Cron Jobs, setiap menit:
   `cd ~/domains/ampuh.finance/public_html/staging/web_umkm && php artisan schedule:run`.

## Deploy

```bash
scripts/deploy-hostinger.sh staging --seed-demo
```

`--seed-demo` mengisi kategori, 3 koperasi demo, 12 produk berfoto dan pesanan contoh. Aman diulang.
Deploy berikutnya cukup `scripts/deploy-hostinger.sh staging`.

Lalu buka `https://staging.ampuh.finance/?access=<STAGING_ACCESS_KEY>` sekali di setiap browser penguji.

## Akun uji (dari DemoSeeder)

| Peran | Login | Password |
| --- | --- | --- |
| Pembeli | `test@example.com` | `password` |
| Penjual | `081234560001` (juga `…0002`, `…0003`) | `password` |

Kartu uji Midtrans sandbox: https://docs.midtrans.com/docs/testing-payment-on-sandbox

## Catatan

- Folder `public_html/staging` ada di dalam docroot production. Aturan rewrite production tidak ikut
  berlaku karena `.htaccess` staging menyalakan `RewriteEngine` sendiri, tetapi direktif non-rewrite
  di `public_html/.htaccess` tetap berlaku untuk staging.
- Email (verifikasi pembeli, lupa password pembeli, form bantuan) hanya tercatat di
  `storage/logs/laravel.log` selama `MAIL_MAILER=log`. Pakai SMTP sandbox seperti Mailtrap bila
  penguji perlu menerima email.
