#!/bin/sh
# Entrypoint container backend: siapkan storage, cache config, migrasi, lalu
# serahkan ke perintah utama (supervisord).
set -e

cd /var/www/html

# Pastikan struktur storage ada (penting bila storage adalah volume kosong).
mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Cache konfigurasi & route untuk performa produksi (aman diulang tiap start).
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

# Migrasi database (idempoten). Tautan storage publik.
php artisan migrate --force || true
php artisan storage:link || true

exec "$@"
