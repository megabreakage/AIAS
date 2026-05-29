#!/usr/bin/env bash
# Drop all MySQL databases prefixed with 'aias'

set -euo pipefail

ENV_FILE="$(dirname "$0")/.env"

# Load from .env if present
if [[ -f "$ENV_FILE" ]]; then
    DB_CENTRAL_HOST=$(grep -m1 '^DB_CENTRAL_HOST=' "$ENV_FILE" | cut -d= -f2)
    DB_CENTRAL_PORT=$(grep -m1 '^DB_CENTRAL_PORT=' "$ENV_FILE" | cut -d= -f2)
    DB_CENTRAL_USERNAME=$(grep -m1 '^DB_CENTRAL_USERNAME=' "$ENV_FILE" | cut -d= -f2)
    DB_CENTRAL_PASSWORD=$(grep -m1 '^DB_CENTRAL_PASSWORD=' "$ENV_FILE" | cut -d= -f2 || true)
fi

MYSQL_HOST="${DB_CENTRAL_HOST:-127.0.0.1}"
MYSQL_PORT="${DB_CENTRAL_PORT:-3306}"
MYSQL_USER="${DB_CENTRAL_USERNAME:-root}"
MYSQL_PASS="${DB_CENTRAL_PASSWORD:-}"

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

DB_COUNT=$(echo "$DATABASES" | wc -l | tr -d ' ')
read -r -p "Drop all ${DB_COUNT} database(s)? [y/N] " CONFIRM
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
