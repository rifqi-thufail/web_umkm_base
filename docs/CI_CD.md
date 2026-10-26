# CI/CD

GitHub Actions menjalankan tes di setiap push dan pull request, lalu men-deploy `master` ke Hostinger.

```
push / PR ke branch mana pun ──> CI (PHP tests · types + build · contract tests)

push ke master ──> CI ──> staging (otomatis, jika STAGING_DEPLOY=true)
                          └─> production (menunggu persetujuan di environment "production")
```

| Workflow | File | Pemicu |
| --- | --- | --- |
| CI | `.github/workflows/ci.yml` | push ke branch selain master, pull request, dipanggil oleh Deploy |
| Deploy | `.github/workflows/deploy.yml` | push ke master, atau manual (Actions → Deploy → Run workflow) |

Kedua deploy memakai `scripts/deploy-hostinger.sh`, sama seperti deploy manual: backup database,
`artisan down`, rsync, `composer install`, migrate, `artisan up`, lalu cek `/up` dan aturan deny.

## Alur kerja sehari-hari

1. Buat branch, push, buka pull request. CI harus hijau sebelum merge.
2. Merge ke `master`. Deploy berjalan: staging otomatis, lalu job production menunggu.
3. Cek staging. Jika baik: Actions → run Deploy → **Review deployments** → Approve production.
   Jika tidak: Reject. Production tidak berubah.

Deploy manual tetap bisa: Actions → Deploy → Run workflow → pilih `staging` atau `production`
(centang `seed_demo` untuk mengisi data demo staging).

## Pengaturan GitHub

| Nama | Jenis | Isi |
| --- | --- | --- |
| `HOSTINGER_SSH_KEY` | secret repo | kunci privat deploy (khusus CI), kunci publiknya ada di hPanel → SSH Access |
| `HOSTINGER_KNOWN_HOSTS` | secret repo | keluaran `ssh-keyscan -p 65002 153.92.10.3` |
| `STAGING_DEPLOY` | variable repo | `true` setelah staging siap (docs/STAGING.md); selama `false` staging dilewati |
| `production` | environment | required reviewer = pemilik repo; hanya branch `master` |
| `staging` | environment | hanya branch `master` |

Mengganti kunci deploy:

```bash
ssh-keygen -t ed25519 -N "" -C "github-actions-ampuh" -f /tmp/ampuh_deploy
gh secret set HOSTINGER_SSH_KEY < /tmp/ampuh_deploy
cat /tmp/ampuh_deploy.pub   # tempel di hPanel → Advanced → SSH Access → SSH keys
rm /tmp/ampuh_deploy /tmp/ampuh_deploy.pub
```

## Jika deploy gagal

- Situs bisa tertinggal dalam mode maintenance. Jalankan ulang job, atau
  `ssh -p 65002 u595677082@153.92.10.3 'cd ~/domains/ampuh.finance/public_html/web_umkm && php artisan up'`.
- Backup database sebelum deploy ada di `~/backups/db-<target>-<tanggal>.sql.gz` di server.
- Rollback kode: revert commit di `master` dan push; pipeline men-deploy versi sebelumnya.
