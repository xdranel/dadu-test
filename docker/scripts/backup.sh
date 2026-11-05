#!/usr/bin/env bash
set -euo pipefail
project_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$project_root"
compose=(docker compose --env-file .env.docker)
umask 077
mkdir -p backups
stamp=$(date -u +%Y%m%dT%H%M%SZ)
"${compose[@]}" exec -T db sh -c \
    'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --single-transaction --no-tablespaces --set-gtid-purged=OFF -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
    > "backups/$stamp.sql"
"${compose[@]}" exec -T app tar -C /var/www/html/storage/app -czf - . \
    > "backups/$stamp-storage.tar.gz"
cp -- .env.docker "backups/$stamp.env"
printf 'Backup saved under backups/%s.*; keep copies off this server too.\n' "$stamp"
