#!/bin/sh

set -eu

COMPOSE_FILE=${COMPOSE_FILE:-docker-compose.prod.yml}
BACKUP_ROOT=${BACKUP_ROOT:-./backups}
RETENTION_DAYS=${RETENTION_DAYS:-14}

compose() {
    docker compose -f "$COMPOSE_FILE" "$@"
}

backup() {
    timestamp=$(date -u +%Y%m%dT%H%M%SZ)
    destination="$BACKUP_ROOT/$timestamp"
    mkdir -p "$destination"

    compose exec -T db sh -c 'exec mysqldump --single-transaction --quick --lock-tables=false -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
        | gzip > "$destination/database.sql.gz"
    compose exec -T api tar -czf - -C /var/www/html/storage/app . \
        > "$destination/storage-app.tar.gz"

    (cd "$destination" && sha256sum database.sql.gz storage-app.tar.gz > SHA256SUMS)
    find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime "+$RETENTION_DAYS" -exec rm -rf {} \;
    printf 'Backup selesai: %s\n' "$destination"
}

restore() {
    source_dir=${1:-}
    if [ -z "$source_dir" ] || [ ! -d "$source_dir" ]; then
        printf 'Direktori backup tidak valid.\n' >&2
        exit 2
    fi

    expected="RESTORE-$(basename "$source_dir")"
    if [ "${RESTORE_CONFIRM:-}" != "$expected" ]; then
        printf 'Set RESTORE_CONFIRM=%s untuk mengonfirmasi restore.\n' "$expected" >&2
        exit 2
    fi

    (cd "$source_dir" && sha256sum -c SHA256SUMS)
    compose stop api web
    gunzip -c "$source_dir/database.sql.gz" \
        | compose exec -T db sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
    compose run --rm --no-deps --entrypoint sh api -c \
        'find /var/www/html/storage/app -mindepth 1 -delete && tar -xzf - -C /var/www/html/storage/app' \
        < "$source_dir/storage-app.tar.gz"
    compose up -d api web
    printf 'Restore selesai dari: %s\n' "$source_dir"
}

case "${1:-}" in
    backup)
        backup
        ;;
    restore)
        restore "${2:-}"
        ;;
    *)
        printf 'Pemakaian: %s backup | %s restore <direktori-backup>\n' "$0" "$0" >&2
        exit 2
        ;;
esac