# GitHub Source Code Handoff

Dokumen ini dipakai untuk serah-terima source code proyek Marketplace UMKM, termasuk backend Laravel dan README proyek.

## Tujuan Serah-Terima

- Memastikan pihak penerima bisa mengakses repository dengan benar.
- Menyediakan informasi teknis untuk menjalankan aplikasi secara lokal maupun production.
- Menjelaskan file dan konfigurasi penting yang harus ikut diserahkan.

## Opsi Transfer Akses GitHub

### 1. Transfer ownership repository

Gunakan ini jika repository ingin dipindahkan ke akun lain atau organization lain.

Langkah umum:

1. Buka repository di GitHub.
2. Masuk ke `Settings`.
3. Scroll ke bagian `Danger Zone`.
4. Pilih `Transfer ownership`.
5. Masukkan nama repository dan username/org tujuan.
6. Konfirmasi transfer.

Catatan:

- Pastikan penerima sudah siap menerima repo.
- Jika repo memakai GitHub Actions, secrets, atau environment, cek ulang setelah transfer.
- Jika repo private, pastikan akses organisasi/akun tujuan memang sudah aktif.

### 2. Beri full access collaborator

Gunakan ini jika ownership tetap di akun lama, tetapi ada orang/tim yang harus mengelola source code penuh.

Langkah umum:

1. Buka `Settings` repository.
2. Masuk ke `Collaborators and teams`.
3. Tambahkan username tujuan.
4. Beri role `Admin` bila butuh akses penuh setara pengelola repo.

Catatan:

- Ini tidak memindahkan ownership.
- Cocok jika hanya ingin operasional dan maintenance bersama.

## File Yang Harus Diserahkan

- Seluruh source code backend Laravel.
- [README.md](../README.md) yang sudah diperbarui.
- `.env.example` dan daftar variabel environment.
- `database/migrations` dan `database/seeders`.
- `config/doku.php` dan `config/blockchain.php`.
- `app/Services/DokuPaymentService.php`.
- `app/Services/BaseChainService.php` dan folder `blockchain/` (kontrak OrderRegistry).
- `routes/web.php` dan `routes/api.php`.
- Folder `resources/views` jika ikut dikelola.

## Checklist Teknis Handoff

- Pastikan dependency bisa di-install ulang dengan `composer install` dan `npm install`.
- Pastikan `php artisan migrate --seed` berhasil di environment baru.
- Pastikan kredensial DOKU tersedia jika payment gateway akan dipakai.
- Pastikan `BASE_REGISTRY_ADDRESS` dan kunci recorder Base tersedia jika pencatatan on-chain aktif.
- Pastikan storage, database, dan webhook URL ikut disiapkan.
- Pastikan environment production tidak memakai nilai sandbox secara tidak sengaja.
- Pastikan akses ke GitHub repository, secret, branch protection, dan Actions sudah jelas.

## Informasi Operasional yang Perlu Dicatat

- Versi PHP dan Laravel yang dipakai.
- Skema database aktif.
- Status integrasi DOKU.
- Alamat kontrak OrderRegistry dan jaringan Base yang dipakai.
- Daftar akun test atau seed data.
- Alur deployment dan build frontend.

## Cara Menjalankan Proyek

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
```

## Risiko Jika Handoff Tidak Lengkap

- Payment gateway tidak bisa dipakai karena credential belum diserahkan.
- Webhook tidak aktif karena URL atau signature key belum tersedia.
- Pencatatan ke Base gagal karena node RPC tidak bisa dihubungi (ulangi dengan `php artisan base:anchor <id>`).
- Deployment gagal karena `.env`, storage, atau database belum disiapkan.

## Rekomendasi Akhir

Sebelum transfer selesai, pastikan penerima mendapat:

- akses repo GitHub,
- salinan source code terakhir,
- dokumentasi setup,
- daftar credential yang wajib diganti,
- dan status fitur yang sudah siap produksi.
