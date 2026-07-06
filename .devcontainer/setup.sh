#!/usr/bin/env bash
# Setup sekali jalan saat Codespace/devcontainer pertama kali dibuat.
# Database sudah terisi otomatis dari .devcontainer/db/seed.sql (impor MySQL),
# jadi TIDAK perlu migrate/seed — data langsung sama dengan lokal.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# --- Helper: set/append variabel di file .env ---
setenv() {
  local key="$1" val="$2" file="$3"
  if grep -qE "^#?\s*${key}=" "$file"; then
    # Escape karakter khusus untuk sed
    local esc
    esc=$(printf '%s' "$val" | sed -e 's/[\/&|]/\\&/g')
    sed -i -E "s|^#?[[:space:]]*${key}=.*|${key}=${esc}|" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

echo "==> Backend (curriculum-service)"
cd "$ROOT/curriculum-service"

[ -f .env ] || cp .env.example .env

setenv APP_ENV        local              .env
setenv APP_DEBUG      true               .env
setenv APP_URL        http://localhost:8100 .env
setenv DB_CONNECTION  mysql              .env
setenv DB_HOST        db                 .env
setenv DB_PORT        3306               .env
setenv DB_DATABASE    curriculum_service .env
setenv DB_USERNAME    root               .env
setenv DB_PASSWORD    password123        .env

composer install --no-interaction --prefer-dist --no-progress

# Buat APP_KEY bila belum ada.
if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan storage:link || true
php artisan config:clear || true

echo "==> Frontend (curriculum-web)"
cd "$ROOT/curriculum-web"
npm install

echo "==> Selesai. Server dijalankan oleh start.sh."
