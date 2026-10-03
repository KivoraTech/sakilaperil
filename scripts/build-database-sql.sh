#!/usr/bin/env bash
# Builds dist/sakila-database.sql: the complete schema + the association's real content for a fresh install, importable
# in cPanel → phpMyAdmin (MySQL 8 or MariaDB 10.4+). Contains NO user accounts (create the first
# administrator with /install or `php artisan sakila:create-admin`).
#
#   scripts/build-database-sql.sh [out.sql]
# Env: MYSQL_HOST (127.0.0.1) MYSQL_PORT (3307 scratch / 3306 XAMPP) MYSQL_USER (root) MYSQL_PASSWORD ("")
#      UPLOADS_OUT (folder that receives the processed photos, default dist/sakila-uploads)
set -euo pipefail
cd "$(dirname "$0")/.."

OUT="${1:-dist/sakila-database.sql}"
export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"
H="${MYSQL_HOST:-127.0.0.1}"; P="${MYSQL_PORT:-3306}"; U="${MYSQL_USER:-root}"
export MYSQL_PWD="${MYSQL_PASSWORD:-}"
DB="sakila_release_dump_$$" # unique per run: two builds never share a scratch database
UPLOADS_OUT="${UPLOADS_OUT:-dist/sakila-uploads}"
mkdir -p "$(dirname "$OUT")" "$UPLOADS_OUT"
UPLOADS_OUT="$(cd "$UPLOADS_OUT" && pwd)"

mysql -h "$H" -P "$P" -u "$U" -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# Fresh schema + the association's REAL content (database/content, loaded through the CMS's own
# actions; photos are processed into $UPLOADS_OUT). No demo seeder, no users.
run() {
  APP_ENV=local DB_HOST="$H" DB_PORT="$P" DB_USERNAME="$U" DB_PASSWORD="${MYSQL_PASSWORD:-}" DB_DATABASE="$DB" \
    LOCAL_UPLOADS_DIR="$UPLOADS_OUT" CACHE_STORE=array php artisan "$@"
}
run migrate:fresh --force >/dev/null
run sakila:load-content >/dev/null
# Must be clean: no placeholder or demo content in what we ship.
run sakila:content-check >/dev/null || { run sakila:content-check >&2; echo "✗ content-check failed" >&2; exit 1; }

# No people, no history: users (cascades to accounts/sessions; authorship becomes NULL), logs, runtime rows.
mysql -h "$H" -P "$P" -u "$U" "$DB" -e "
  DELETE FROM users;
  DELETE FROM audit_logs; DELETE FROM http_sessions; DELETE FROM cache; DELETE FROM cache_locks;
  DELETE FROM login_throttles; DELETE FROM rate_limits; DELETE FROM form_rate_limits;
  DELETE FROM verifications; DELETE FROM page_preview_drafts; DELETE FROM jobs; DELETE FROM failed_jobs;"

# The schema is dumped from the database that was just migrated, so the CREATE TABLE statements, the
# `migrations` rows and the data rows always describe the same schema, also after new migrations are
# added (a fixed baseline file + the full `migrations` table would claim migrations ran that the
# imported schema does not contain).
DUMP="mysqldump -h $H -P $P -u $U --skip-comments --skip-triggers --no-tablespaces --skip-add-locks --skip-disable-keys --default-character-set=utf8mb4"
{
  echo "-- Sakila per il Nepal — database for a fresh install ($(date -u +%Y-%m-%dT%H:%MZ))."
  echo "-- Import in cPanel → phpMyAdmin into an EMPTY database (utf8mb4). Works on MySQL 8 and MariaDB 10.4+."
  echo "-- Contains the site content and settings, and NO user accounts."
  echo "SET NAMES utf8mb4;"
  echo "SET FOREIGN_KEY_CHECKS = 0;"
  echo "SET time_zone = '+00:00';"
  echo
  echo "-- ── Schema (all migrations applied; the migrations table below records them) ──"
  $DUMP --no-data --skip-add-drop-table --compact "$DB" | grep -v '^/\*![0-9]* SET ' 
  echo
  echo "-- ── Data ──"
  $DUMP --no-create-info --complete-insert --tz-utc "$DB"
  echo
  echo "SET FOREIGN_KEY_CHECKS = 1;"
} > "$OUT"

# ── Self-check: the file, imported into an empty database, must equal the migrated database ──
VERIFY="${DB}_verify"
sql() { mysql -h "$H" -P "$P" -u "$U" -N "$@"; }
sql -e "DROP DATABASE IF EXISTS \`$VERIFY\`; CREATE DATABASE \`$VERIFY\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
sql "$VERIFY" < "$OUT" || { echo "✗ the generated SQL file does not import" >&2; exit 1; }
fingerprint() { # columns, indexes, foreign keys and row counts of one database
  sql -e "
    SELECT 'col', table_name, column_name, column_type, is_nullable, IFNULL(column_default,'<null>'), extra, IFNULL(collation_name,'')
      FROM information_schema.columns WHERE table_schema='$1' ORDER BY table_name, ordinal_position;
    SELECT 'idx', table_name, index_name, non_unique, seq_in_index, column_name, IFNULL(sub_part,'')
      FROM information_schema.statistics WHERE table_schema='$1' ORDER BY table_name, index_name, seq_in_index;
    SELECT 'fk', table_name, constraint_name, referenced_table_name, update_rule, delete_rule
      FROM information_schema.referential_constraints WHERE constraint_schema='$1' ORDER BY table_name, constraint_name;"
  for t in $(sql -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$1' ORDER BY table_name"); do
    echo "rows $t $(sql -e "SELECT COUNT(*) FROM \`$1\`.\`$t\`")"
  done
}
if ! diff <(fingerprint "$DB") <(fingerprint "$VERIFY") >/dev/null; then
  echo "✗ the imported SQL file differs from the migrated database:" >&2
  diff <(fingerprint "$DB") <(fingerprint "$VERIFY") | head -40 >&2 || true
  exit 1
fi
PENDING="$(APP_ENV=local DB_HOST="$H" DB_PORT="$P" DB_USERNAME="$U" DB_PASSWORD="${MYSQL_PASSWORD:-}" DB_DATABASE="$VERIFY" \
  CACHE_STORE=array php artisan migrate:status --pending --no-ansi 2>&1 || true)"
if ! echo "$PENDING" | grep -qi "no pending migrations"; then
  echo "✗ after importing the SQL file there are still pending migrations:" >&2
  echo "$PENDING" >&2
  exit 1
fi
USERS="$(sql -e "SELECT COUNT(*) FROM \`$VERIFY\`.users")"
[[ "$USERS" == "0" ]] || { echo "✗ the SQL file contains user accounts" >&2; exit 1; }
sql -e "DROP DATABASE \`$VERIFY\`"

mysql -h "$H" -P "$P" -u "$U" -e "DROP DATABASE \`$DB\`"
echo "Wrote $OUT ($(du -h "$OUT" | cut -f1)), verified against the migrated schema, and the photos in $UPLOADS_OUT" >&2
