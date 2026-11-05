#!/usr/bin/env bash
set -euo pipefail
project_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$project_root"
if [[ -e .env.docker ]]; then
    printf '%s\n' '.env.docker already exists; keeping the existing key and passwords.'
    exit 0
fi
command -v openssl >/dev/null
umask 077
app_key="base64:$(openssl rand -base64 32)"
db_password=$(openssl rand -hex 24)
root_password=$(openssl rand -hex 24)
test_password=$(openssl rand -hex 12)
temp_file=$(mktemp .env.docker.XXXXXX)
trap 'rm -f -- "$temp_file"' EXIT
while IFS= read -r line || [[ -n "$line" ]]; do
    case "$line" in
        APP_KEY=*) printf 'APP_KEY=%s\n' "$app_key" ;;
        DADU_DB_PASSWORD=*) printf 'DADU_DB_PASSWORD=%s\n' "$db_password" ;;
        DADU_DB_ROOT_PASSWORD=*) printf 'DADU_DB_ROOT_PASSWORD=%s\n' "$root_password" ;;
        DADU_TEST_PASSWORD=*) printf 'DADU_TEST_PASSWORD=%s\n' "$test_password" ;;
        *) printf '%s\n' "$line" ;;
    esac
done < .env.docker.example > "$temp_file"
chmod 600 "$temp_file"
mv -- "$temp_file" .env.docker
printf '%s\n' 'Created .env.docker with independent keys/passwords. Keep this file on the server.'
printf '%s\n' 'Read DADU_TEST_PASSWORD locally on the server when sharing access with your testers.'
