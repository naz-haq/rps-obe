# Operasional Produksi

## Deploy yang dapat direproduksi

Isi `IMAGE_TAG` di `.env` dengan SHA commit penuh yang sudah diterbitkan workflow GitHub Actions, lalu jalankan:

```sh
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Jangan gunakan `latest` untuk rollback atau deploy produksi rutin.

## Backup

Jalankan dari root proyek pada VM. Backup mencakup dump MySQL konsisten dan isi privat `storage/app`, disertai checksum SHA-256. Retensi bawaan 14 hari.

```sh
sh deploy/backup-restore.sh backup
```

Atur lokasi dan retensi melalui `BACKUP_ROOT` dan `RETENTION_DAYS`. Salin hasil backup ke penyimpanan di luar VM dan uji restore secara berkala.

## Restore

Restore menghentikan API dan web, mengganti database serta `storage/app`, kemudian menjalankan kembali layanan. Nama direktori backup menjadi frasa konfirmasi:

```sh
RESTORE_CONFIRM=RESTORE-20260905T120000Z \
  sh deploy/backup-restore.sh restore backups/20260905T120000Z
```

Jalankan restore terlebih dahulu di lingkungan nonproduksi, lalu verifikasi login, daftar RPS, unduhan dokumen, dan endpoint health.