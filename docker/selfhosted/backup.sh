#!/usr/bin/env bash
# Backs up the database, the uploaded files and .env into ./backups/.
# .env is part of the backup on purpose: its APP_KEY is needed to read
# encrypted data, so a database backup without it is only half a backup.
# Copy the backups folder somewhere OFF this machine (see README.md).
set -euo pipefail
cd "$(dirname "$0")"

stamp=$(date +%Y%m%d-%H%M%S)
mkdir -p backups
umask 077

docker compose exec -T pgsql sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "backups/db-$stamp.sql.gz"
docker compose exec -T web tar czf - -C /var/www/html/storage app > "backups/files-$stamp.tar.gz"
cp .env "backups/env-$stamp"

echo "Backed up to backups/ (stamp $stamp):"
ls -lh backups/*"$stamp"*
echo "Restore with: ./restore.sh $stamp"
