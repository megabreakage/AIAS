#!/usr/bin/env bash
# Drop all MySQL databases prefixed with 'aias'

set -euo pipefail

MYSQL_HOST="${DB_CENTRAL_HOST:-127.0.0.1}"
MYSQL_PORT="${DB_CENTRAL_PORT:-3306}"
MYSQL_USER="${DB_CENTRAL_USERNAME:-root}"
MYSQL_PASS="${DB_CENTRAL_PASSWORD:-}"

# Load from .env if present
if [[ -f "$(dirname "$0")/.env" ]]; then
    source <(grep -E "^DB_CENTRAL_(HOST|PORT|USERNAME|PASSWORD)=" "$(dirname "$0")/.env" | sed 's/^DB_CENTRAL_/MYSQL_/; s/HOST/HOST/; s/PORT/PORT/; s/USERNAME/USER/; s/PASSWORD/PASS/')
fi

CONN=(-h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER")
[[ -n "$MYSQL_PASS" ]] && CONN+=(-p"$MYSQL_PASS")

echo "Fetching databases starting with 'aias'..."

DATABASES=$(mysql "${CONN[@]}" --silent --skip-column-names -e \
    "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'aias%';")

if [[ -z "$DATABASES" ]]; then
    echo "No databases found."
    exit 0
fi

echo ""
echo "Databases to drop:"
echo "$DATABASES" | sed 's/^/  - /'
echo ""

read -r -p "Drop all ${#DATABASES[@]} database(s)? [y/N] " CONFIRM
if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
    echo "Aborted."
    exit 0
fi

while IFS= read -r DB; do
    [[ -z "$DB" ]] && continue
    echo "Dropping: $DB"
    mysql "${CONN[@]}" -e "DROP DATABASE \`${DB}\`;"
done <<< "$DATABASES"

echo ""
echo "Done."
