#!/usr/bin/env bash
# Menjalankan backend (Laravel :8100) dan frontend (Next.js :3010) di latar
# belakang. Idempoten — proses lama dihentikan dulu agar tidak dobel.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# --- Backend ---
cd "$ROOT/curriculum-service"
pkill -f "artisan serve" 2>/dev/null || true
mkdir -p storage/logs
nohup php artisan serve --host=0.0.0.0 --port=8100 \
  > storage/logs/serve.log 2>&1 &
echo "Backend Laravel berjalan di :8100 (log: curriculum-service/storage/logs/serve.log)"

# --- Frontend ---
cd "$ROOT/curriculum-web"
pkill -f "next dev" 2>/dev/null || true
nohup npm run dev -- --port 3010 --hostname 0.0.0.0 \
  > /tmp/next-dev.log 2>&1 &
echo "Frontend Next.js berjalan di :3010 (log: /tmp/next-dev.log)"

echo "Buka forwarded port 3010 (app.github.dev). Login NIDN 0000000001 / Admin#1234."
