# AMPUH — Marketplace UMKM

AMPUH (Aplikasi Merah Putih Universitas Hasanuddin) adalah marketplace untuk UMKM dan koperasi. Pembeli berbelanja, membayar lewat Midtrans atau kripto, dan setiap pesanan yang lunas dicatat sidik jarinya (keccak256) di jaringan **Base** sehingga siapa pun bisa memverifikasi bahwa isi pesanan tidak diubah.

## Fitur

- Katalog dengan pencarian dan filter kategori, halaman produk, halaman toko publik.
- Keranjang, checkout multi-penjual (satu pesanan per penjual), Midtrans dan CoinPayments.
- Riwayat pesanan dengan kuitansi yang bisa diverifikasi di Base.
- Buku transaksi publik (`/transparansi`) dan halaman verifikasi (`/verifikasi?q=<hash>`).
- Dashboard penjual: produk (multi-foto, foto utama), pesanan lunas, aktivasi Midtrans, profil toko.
- Login penjual dengan nomor HP, reset password lewat OTP SMS.
- Wishlist, profil pembeli, halaman bantuan.

## Stack

| Bagian | Teknologi |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | React 19 + TypeScript lewat Inertia.js, Vite 8 |
| UI | [shadcn/ui](https://ui.shadcn.com) (Radix, style *nova*), Tailwind CSS v4, lucide-react |
| Blockchain | Kontrak `OrderRegistry` (Solidity, Foundry) di Base; lokal pakai anvil fork Base Sepolia |
| Pembayaran | Midtrans, CoinPayments (DOKU legacy) |
| Database | SQLite (lokal), MySQL/PostgreSQL (production) |

## Menjalankan secara lokal

Prasyarat: PHP 8.2+, Composer, Node.js 20+, [Foundry](https://book.getfoundry.sh/getting-started/installation) (untuk Base lokal).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link

# Base lokal (terminal terpisah), lalu salin BASE_* yang dicetak ke .env
blockchain/start-local.sh
blockchain/deploy-local.sh
php artisan config:clear && php artisan base:status

# Data demo: 3 koperasi, 12 produk berfoto, pesanan yang sudah tercatat di Base
php artisan db:seed --class=DemoSeeder

npm run dev          # terminal 1
php artisan serve    # terminal 2 -> http://localhost:8000
```

Akun demo: pembeli `test@example.com` / `password`, penjual `081234560001` / `password`.

Detail blockchain: [blockchain/README.md](blockchain/README.md). Deploy production dan reverse proxy: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Struktur

```
app/Http/Controllers      Controller mengembalikan Inertia::render('<halaman>', props)
app/Support/Present.php   Mengubah model Eloquent menjadi props untuk React
app/Services/BaseChainService.php   Sidik jari pesanan, tanda tangan tx, baca kontrak
blockchain/               Kontrak OrderRegistry, tes Foundry, skrip Base lokal
resources/js/pages        Satu file .tsx per halaman (nama = komponen Inertia)
resources/js/components   Komponen aplikasi (kuitansi, kartu produk, header) + ui/ dari shadcn
resources/css/app.css     Token warna dan tipografi (merah-putih + biru Base khusus bukti on-chain)
.claude/skills            Skill desain: frontend-design (Anthropic) dan shadcn (resmi)
```

## Konfigurasi `.env`

| Variabel | Keterangan |
| --- | --- |
| `BASE_*` | RPC, chain id, alamat kontrak, kunci recorder. Lihat `blockchain/README.md`. |
| `MIDTRANS_*` | Server/client key Midtrans. Webhook: `POST /api/midtrans/callback`. |
| `COINPAYMENTS_*` | Kredensial CoinPayments. IPN: `POST /api/coinpayments/ipn`. |
| `SMS_GATEWAY_*` | Pengiriman OTP reset password penjual. |
| `DOKU_*` | Legacy, hanya jika DOKU masih dipakai. |

## Perintah berguna

```bash
php artisan base:status            # cek koneksi Base dan kontrak
php artisan base:anchor <order-id> # catat ulang pesanan yang gagal dicatat
cd blockchain && forge test        # tes kontrak
npx tsc --noEmit                   # cek tipe TypeScript
php artisan test
```

## Route utama

| Route | Halaman |
| --- | --- |
| `/` | Katalog |
| `/products/{id}` | Detail produk |
| `/seller/{id}` | Toko publik |
| `/transparansi`, `/transparansi/{txHash}` | Buku transaksi dan kuitansi publik |
| `/verifikasi?q=` | Verifikasi hash atau nomor pesanan |
| `/cart`, `/checkout`, `/riwayat-pesanan` | Pembeli |
| `/seller/dashboard`, `/seller/products`, `/seller/orders`, `/seller/activation` | Penjual |

Foto produk demo di `database/seeders/demo-images` berlisensi CC0 / public domain, sumbernya di `CREDITS.json`.
