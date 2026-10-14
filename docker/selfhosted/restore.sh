#!/usr/bin/env bash
# Restores a backup made by ./backup.sh:  ./restore.sh <stamp>
# REPLACES the current database and uploaded files. The backed-up .env is not
# applied automatically - if you are restoring onto a new machine, copy
# backups/env-<stamp> to .env first so the APP_KEY matches.
set -euo pipefail
cd "$(dirname "$0")"

stamp=${1:-}
[ -n "$stamp" ] || { echo "usage: ./restore.sh <stamp>   (see ./backups)" >&2; exit 1; }
db="backups/db-$stamp.sql.gz"; files="backups/files-$stamp.tar.gz"
[ -f "$db" ] && [ -f "$files" ] || { echo "no backup with stamp $stamp in ./backups" >&2; exit 1; }

if [ -f "backups/env-$stamp" ] && ! cmp -s .env "backups/env-$stamp"; then
    echo "warning: .env differs from the one saved with this backup (a different APP_KEY makes encrypted data unreadable)." >&2
fi

printf 'This replaces the current data with backup %s. Type "restore" to continue: ' "$stamp"
read -r answer; [ "$answer" = "restore" ] || { echo "cancelled"; exit 1; }

docker compose up -d pgsql redis
docker compose stop caddy web horizon scheduler reverb
docker compose exec -T pgsql sh -c 'dropdb -U "$POSTGRES_USER" --if-exists --force "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'
gunzip -c "$db" | docker compose exec -T pgsql sh -c 'psql -q -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null
docker compose run --rm --no-deps -T --entrypoint sh web -c 'rm -rf /var/www/html/storage/app/* && tar xzf - -C /var/www/html/storage' < "$files"
docker compose up -d
echo "Restored backup $stamp."
