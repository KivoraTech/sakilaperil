#!/usr/bin/env bash
# End-to-end test of the deployment path (not part of Pest: it builds a package and needs MySQL/MariaDB):
#   1. scripts/build-cpanel.sh builds the zip (which builds and self-checks sakila-database.sql);
#   2. scripts/cpanel/sakila-deploy.sh runs in a throwaway "cPanel home": refused settings file,
#      first install, the installed site answers, update, refused update, rollback.
#
#   npm run test:deploy            (or: composer test:deploy, bash tests/deploy/run.sh)
# Env: MYSQL_HOST (127.0.0.1) MYSQL_PORT (3306) MYSQL_USER (root) MYSQL_PASSWORD ("")
#      PHP82 / PHP_BIN  PHP used to run the installed site (default: php)
#      PACKAGE_ZIP      test an existing zip instead of building one
#      PACKAGE_VERSION  version label of the built package (default: test)
#      DIST_DIR         where the package is built (default: dist/)   SKIP_NPM_BUILD=1 reuse public/build
set -euo pipefail
cd "$(dirname "$0")/../.."
ROOT="$(pwd)"
command -v mysql >/dev/null 2>&1 || export PATH="$PATH:/Applications/XAMPP/xamppfiles/bin" # local dev: XAMPP's client
H="${MYSQL_HOST:-127.0.0.1}"; P="${MYSQL_PORT:-3306}"; U="${MYSQL_USER:-root}"; PW="${MYSQL_PASSWORD:-}"
export MYSQL_HOST="$H" MYSQL_PORT="$P" MYSQL_USER="$U" MYSQL_PASSWORD="$PW"
PHP_BIN="${PHP_BIN:-${PHP82:-$(command -v php)}}"
VERSION="${PACKAGE_VERSION:-test}"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/sakila-deploy-test.XXXXXX")"
HOME_DIR="$WORK/home"
DB="sakila_deploytest_$$"
DBUSER="sakila_dt_$$"; DBPASS="dt-$$-S3cret\"pw" # a password with a quote: the deploy script must cope
PORT=$((3500 + RANDOM % 400))
URL="http://127.0.0.1:$PORT"
SERVER_PID=""
PASS=0; FAIL=0

sql() { MYSQL_PWD="$PW" mysql -h "$H" -P "$P" -u "$U" -N "$@"; }
cleanup() {
  [[ -n "$SERVER_PID" ]] && kill "$SERVER_PID" 2>/dev/null || true
  sql -e "DROP DATABASE IF EXISTS \`$DB\`; DROP USER IF EXISTS '$DBUSER'@'localhost'; DROP USER IF EXISTS '$DBUSER'@'127.0.0.1'; DROP USER IF EXISTS '$DBUSER'@'%'" 2>/dev/null || true
  rm -rf "$WORK"
}
trap cleanup EXIT
ok() { PASS=$((PASS + 1)); echo "  ✓ $1"; }
ko() { FAIL=$((FAIL + 1)); echo "  ✗ $1"; }
check() { local d="$1"; shift; if "$@" >/dev/null 2>&1; then ok "$d"; else ko "$d"; fi; }
deploy() { SAKILA_HOME="$HOME_DIR" PHP_BIN="$PHP_BIN" bash "$HOME_DIR/sakila-deploy.sh" "$@" </dev/null; }
mode() { stat -f '%Lp' "$1" 2>/dev/null || stat -c '%a' "$1"; }
http() { curl -s -o /dev/null -w '%{http_code}' "$URL$1"; }

# ── 1. Package ───────────────────────────────────────────────────────────────────────────────
if [[ -n "${PACKAGE_ZIP:-}" ]]; then
  ZIP="$PACKAGE_ZIP"; DEPLOY_SH="$ROOT/scripts/cpanel/sakila-deploy.sh"
  echo "▸ Testing existing package $ZIP"
else
  echo "▸ Building the package ($VERSION)"
  scripts/build-cpanel.sh "$VERSION"
  DIST="${DIST_DIR:-$ROOT/dist}"
  ZIP="$DIST/sakila-cpanel-$VERSION.zip"; DEPLOY_SH="$DIST/sakila-deploy.sh"
fi
[[ -f "$ZIP" ]] || { echo "✗ no package at $ZIP" >&2; exit 1; }
echo "▸ Package contents"
LIST="$(unzip -Z1 "$ZIP")"
check "has the app, the public folder and the database file" bash -c 'for f in sakila/artisan public_html/index.php sakila-database.sql sakila/VERSION; do grep -qx "$f" <<<"$0" || exit 1; done' "$LIST"
check "ships no secrets, tests, client originals or demo data" bash -c '! grep -Eq "(^|/)\.env$|^sakila/tests/|^sakila/assets/|^sakila/node_modules/|^sakila/database/seeders/content/|\.git/" <<<"$0"' "$LIST"

# ── 2. A fake cPanel home with an empty database ─────────────────────────────────────────────
mkdir -p "$HOME_DIR/public_html/.well-known"
echo "host default page" >"$HOME_DIR/public_html/index.html"
echo keep >"$HOME_DIR/public_html/.well-known/keep.txt"
cp "$DEPLOY_SH" "$HOME_DIR/sakila-deploy.sh"
# Like the cPanel wizard: an empty utf8mb4 database and a user with a password and all privileges on it.
sql -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
ESCPASS="${DBPASS//\"/\\\"}"
for host in localhost 127.0.0.1 %; do
  sql -e "CREATE USER IF NOT EXISTS '$DBUSER'@'$host' IDENTIFIED BY \"$ESCPASS\"; GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DBUSER'@'$host'"
done
sql -e "FLUSH PRIVILEGES"
php scripts/make-env.php 2>/dev/null >"$HOME_DIR/sakila.env"

echo "▸ A settings file with <placeholders> left in is refused and nothing changes"
cp "$ZIP" "$HOME_DIR/sakila-cpanel-$VERSION.zip"
if deploy; then ko "deploy must fail"; else ok "deploy fails"; fi
check "the package is set aside as .failed" test -f "$HOME_DIR/sakila-cpanel-$VERSION.zip.failed"
check "nothing was installed" bash -c "! test -e '$HOME_DIR/sakila' && test -f '$HOME_DIR/public_html/index.html'"
mv "$HOME_DIR/sakila-cpanel-$VERSION.zip.failed" "$HOME_DIR/sakila-cpanel-$VERSION.zip"

fill_env() { # $1 = file
  perl -0pi -e "s#https://<your-domain>#$URL#g; s#^DB_HOST=.*#DB_HOST=$H#m; s#^DB_PORT=.*#DB_PORT=$P#m; s#^DB_DATABASE=.*#DB_DATABASE=$DB#m; s#^DB_USERNAME=.*#DB_USERNAME=$DBUSER#m; s#<your-domain>#example.org#g; s#\"<mailbox-password>\"#\"x\"#; s#^SESSION_SECURE_COOKIE=.*#SESSION_SECURE_COOKIE=false#m" "$1"
  grep -v '^DB_PASSWORD=' "$1" >"$1.tmp" && printf "DB_PASSWORD='%s'\n" "$DBPASS" >>"$1.tmp" && mv "$1.tmp" "$1"
}
fill_env "$HOME_DIR/sakila.env"

echo "▸ A plain http:// public address is refused"
cp "$HOME_DIR/sakila.env" "$WORK/good.env"
perl -pi -e 's#^SITE_URL=.*#SITE_URL=http://www.example.org#' "$HOME_DIR/sakila.env"
if deploy; then ko "deploy must fail"; else ok "deploy fails"; fi
check "the log says SITE_URL must be https" grep -q "SITE_URL must be your site's address starting with https" "$HOME_DIR/sakila-deploy.log"
mv "$HOME_DIR/sakila-cpanel-$VERSION.zip.failed" "$HOME_DIR/sakila-cpanel-$VERSION.zip"
cp "$WORK/good.env" "$HOME_DIR/sakila.env"

echo "▸ First install"
if deploy; then ok "deploy succeeds"; else ko "deploy succeeds"; tail -20 "$HOME_DIR/sakila-deploy.log"; echo "deploy test: first install failed, stopping"; exit 1; fi
check "the app and its settings are in place" test -f "$HOME_DIR/sakila/.env" -a -f "$HOME_DIR/sakila/artisan"
check "the uploaded settings file was removed from the home folder" bash -c "! test -e '$HOME_DIR/sakila.env'"
check "the database was imported (40+ tables, content, no users)" bash -c "[[ \$(MYSQL_PWD='$PW' mysql -h $H -P $P -u $U -N -e \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB'\") -ge 40 && \$(MYSQL_PWD='$PW' mysql -h $H -P $P -u $U -N -e \"SELECT COUNT(*) FROM \\\`$DB\\\`.pages\") -ge 1 && \$(MYSQL_PWD='$PW' mysql -h $H -P $P -u $U -N -e \"SELECT COUNT(*) FROM \\\`$DB\\\`.users\") -eq 0 ]]"
check "no migration is pending after the import" bash -c "cd '$HOME_DIR/sakila' && '$PHP_BIN' artisan migrate:status --pending --no-ansi | grep -qi 'no pending'"
check "the host's default page was moved away, other files kept" bash -c "! test -e '$HOME_DIR/public_html/index.html' && test -f '$HOME_DIR/public_html/.well-known/keep.txt' && test -f '$HOME_DIR/public_html/build/manifest.json'"
check ".env is private (600)" test "$(mode "$HOME_DIR/sakila/.env")" = 600
check "backups folder is private (700)" test "$(mode "$HOME_DIR/sakila-backups")" = 700
check "deploy log is private (600)" test "$(mode "$HOME_DIR/sakila-deploy.log")" = 600
check "the photos were installed" bash -c "test -n \"\$(find '$HOME_DIR/sakila-uploads' -type f | head -1)\""

echo "▸ The installed site answers"
(cd "$HOME_DIR/public_html" && exec "$PHP_BIN" -S "127.0.0.1:$PORT" -t . >"$WORK/serve.log" 2>&1) &
SERVER_PID=$!
for _ in $(seq 1 40); do curl -s -o /dev/null "$URL/api/health" && break; sleep 0.25; done
check "/api/health is 200" test "$(http /api/health)" = 200
check "/it is 200" test "$(http /it)" = 200
check "/en is 200" test "$(http /en)" = 200
check "/install is available until an administrator exists" test "$(http /install)" = 200
check "/admin redirects to the login" test "$(http /admin)" = 302
HEADERS="$(curl -sI "$URL/it")"
check "public pages set no cookie" bash -c '! grep -qi "^set-cookie" <<<"$0"' "$HEADERS"
check "CSP allows no inline scripts" bash -c 'grep -i "^content-security-policy" <<<"$0" | grep -q "script-src .self. https" ' "$HEADERS"
check "built assets use root-relative URLs" bash -c "curl -s -H 'Host: evil.example' '$URL/it' | grep -q 'src=\"/build/assets/' && ! curl -s -H 'Host: evil.example' '$URL/it' | grep -q 'evil.example/build'"

echo "▸ Update"
echo marker >"$HOME_DIR/sakila-uploads/my-photo.txt"
cp "$HOME_DIR/sakila/.env" "$WORK/env.before"
cp "$ZIP" "$HOME_DIR/sakila-cpanel-$VERSION-b.zip"
if deploy; then ok "deploy succeeds"; else ko "deploy succeeds"; tail -20 "$HOME_DIR/sakila-deploy.log"; fi
LAST="$(ls -1d "$HOME_DIR"/sakila-backups/20*/ | sort | tail -n 1)"
check "the settings file is unchanged" cmp -s "$HOME_DIR/sakila/.env" "$WORK/env.before"
check "uploads are untouched" test -f "$HOME_DIR/sakila-uploads/my-photo.txt"
check "a database backup and the previous version were kept" test -s "$LAST/database.sql.gz" -a -d "$LAST/sakila"
check "the database backup is private" test "$(mode "$LAST/database.sql.gz")" != 644 -o "$(mode "$HOME_DIR/sakila-backups")" = 700
check "the site is up after the update" test "$(http /it)" = 200
check "the log reports a passing health check" grep -q "api/health → OK" "$HOME_DIR/sakila-deploy.log"

echo "▸ An update with broken settings is refused before the live site is touched"
cp "$ZIP" "$HOME_DIR/sakila-cpanel-$VERSION-c.zip"
perl -pi -e 's#^LOCAL_UPLOADS_DIR=.*#LOCAL_UPLOADS_DIR=relative/path#' "$HOME_DIR/sakila/.env"
if deploy; then ko "deploy must fail"; else ok "deploy fails"; fi
check "the log names the broken setting" grep -q "LOCAL_UPLOADS_DIR must be an absolute path" "$HOME_DIR/sakila-deploy.log"
cp "$WORK/env.before" "$HOME_DIR/sakila/.env"
check "the live site still answers" test "$(http /it)" = 200
rm -f "$HOME_DIR"/sakila-cpanel-*.failed

echo "▸ Rollback"
if deploy rollback; then ok "rollback succeeds"; else ko "rollback succeeds"; fi
check "the site is up after the rollback" test "$(http /it)" = 200
check "nothing to do without a new package" deploy

echo
echo "deploy test: $PASS passed, $FAIL failed"
[[ "$FAIL" == "0" ]]
