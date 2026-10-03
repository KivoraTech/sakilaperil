#!/bin/bash
# Sakila — install or update the website on cPanel hosting (first install AND later versions).
#
# Upload these files into your cPanel HOME folder (/home/<cpaneluser>, NOT public_html):
#   sakila-cpanel-<version>.zip   the package
#   sakila-deploy.sh              this script
#   sakila.env                    your settings file (first install only; see INSTALL.md step 5)
#
# Then run it ONE of these ways:
#   cPanel → Terminal:   bash ~/sakila-deploy.sh
#   cPanel → Cron Jobs:  "Once Per Minute", command:  /bin/bash $HOME/sakila-deploy.sh
#                        wait 2–3 minutes, read ~/sakila-deploy.log, then DELETE the cron job.
#                        (Safe to leave running meanwhile: with no new zip it does nothing.)
#
# What it does: checks PHP and the database first (nothing is changed if a check fails) → backs up
# the current site + database → puts the new files in place (your .env and uploads are kept) →
# first install: imports the database and the site's photos → runs database updates → checks the site.
#
# Undo the last update:   bash ~/sakila-deploy.sh rollback
#
# Optional settings (environment variables): PHP_BIN=/path/to/php  KEEP_BACKUPS=3
set -euo pipefail

HOME_DIR="${SAKILA_HOME:-$HOME}"
APP="$HOME_DIR/sakila"
WEB="$HOME_DIR/public_html"
BACKUPS="$HOME_DIR/sakila-backups"
LOG="$HOME_DIR/sakila-deploy.log"
LOCK="$HOME_DIR/.sakila-deploy.lock"
STAMP="$(date +%Y%m%d-%H%M%S)"
TMP="$HOME_DIR/.sakila-deploy-tmp-$STAMP"
KEEP_BACKUPS="${KEEP_BACKUPS:-3}"
MODE="${1:-deploy}"

# Backups (database dumps, previous versions with their .env), the working folder and the log hold
# secrets and personal data: readable by this account only, whatever the server's default umask.
# (public_html and the app keep the normal permissions the web server needs.)
mkdir -p "$BACKUPS"
chmod 700 "$BACKUPS"
touch "$LOG"
chmod 600 "$LOG"
if [ -t 1 ]; then
  exec > >(tee -a "$LOG") 2>&1 # Terminal: show on screen and keep a copy in the log
else
  exec >>"$LOG" 2>&1 # cron: log only
fi

say() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
ZIP=""
SWAPPED=0
MYCNF=""

cleanup() {
  rm -rf "$TMP" "$LOCK"
  if [ -n "$MYCNF" ]; then rm -f "$MYCNF"; fi
}
fail() {
  say "✗ FAILED: $*"
  # Don't let a cron job retry the same broken package every minute.
  if [ -n "$ZIP" ] && [ -f "$ZIP" ]; then
    mv "$ZIP" "$ZIP.failed"
    say "  The package was renamed to $(basename "$ZIP").failed. Fix the problem, rename it back to .zip, run again."
  fi
  cleanup
  exit 1
}

# ── One run at a time (a cron job fires every minute) ────────────────────────────────────────
if ! mkdir "$LOCK" 2>/dev/null; then
  if [ -n "$(find "$LOCK" -maxdepth 0 -mmin +30 2>/dev/null)" ]; then
    rm -rf "$LOCK" && mkdir "$LOCK"
  else
    exit 0
  fi
fi
trap 'fail "unexpected error on line $LINENO"' ERR

# ── Helpers ──────────────────────────────────────────────────────────────────────────────────
# Value of KEY in an .env file (last occurrence, surrounding quotes removed).
envget() {
  local v
  v="$(grep -E "^$2=" "$1" 2>/dev/null | tail -n 1 | cut -d= -f2-)" || true
  v="${v%$'\r'}"
  case "$v" in
    \"*\") v="${v#\"}"; v="${v%\"}" ;;
    \'*\') v="${v#\'}"; v="${v%\'}" ;;
  esac
  printf '%s' "$v"
}

find_php() {
  local c
  for c in "${PHP_BIN:-}" /usr/local/bin/php php \
    /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /opt/alt/php82/usr/bin/php \
    /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php \
    /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php; do
    [ -n "$c" ] || continue
    command -v "$c" >/dev/null 2>&1 || continue
    if "$c" -r 'exit(PHP_VERSION_ID >= 80200 && extension_loaded("pdo_mysql") && extension_loaded("mbstring") ? 0 : 1);' 2>/dev/null; then
      command -v "$c"
      return 0
    fi
  done
  return 1
}

# MySQL client options in a private file, so the password never appears in the process list.
make_mycnf() {
  local envfile="$1" pw
  MYCNF="$HOME_DIR/.sakila-deploy-my-$STAMP.cnf"
  pw="$(envget "$envfile" DB_PASSWORD)"
  pw="${pw//\\/\\\\}"
  pw="${pw//\"/\\\"}"
  (
    umask 077
    {
      echo "[client]"
      echo "host=$(envget "$envfile" DB_HOST)"
      echo "port=$(envget "$envfile" DB_PORT)"
      echo "user=$(envget "$envfile" DB_USERNAME)"
      echo "password=\"$pw\""
      echo "default-character-set=utf8mb4"
    } >"$MYCNF"
  )
}
db() { mysql --defaults-extra-file="$MYCNF" "$DB_NAME" "$@"; }

artisan() { (cd "$APP" && "$PHP" artisan "$@"); }

# Newest backup that holds a previous version (a refused update leaves only a database dump).
latest_backup() {
  local d
  for d in $(ls -1d "$BACKUPS"/20*/ 2>/dev/null | sort -r); do
    if [ -d "${d}sakila" ]; then
      printf '%s' "${d%/}"
      return 0
    fi
  done
  return 0
}

# ── Rollback: put the previous version's files back ──────────────────────────────────────────
if [ "$MODE" = "rollback" ]; then
  say "── Sakila rollback ──"
  B="$(latest_backup)"
  [ -n "$B" ] && [ -d "$B/sakila" ] || fail "no previous version found in $BACKUPS"
  PHP="$(find_php)" || fail "PHP 8.2+ not found"
  mv "$APP" "$BACKUPS/sakila-rolled-back-$STAMP"
  mv "$B/sakila" "$APP"
  if [ -d "$B/public_html-build" ]; then
    rm -rf "$WEB/build" && cp -R "$B/public_html-build" "$WEB/build"
    cp "$B/public_html-index.php" "$WEB/index.php"
    cp "$B/public_html-htaccess" "$WEB/.htaccess"
  fi
  artisan up >/dev/null 2>&1 || true
  artisan view:clear >/dev/null 2>&1 || true
  say "✓ Files of the previous version are back ($(cat "$APP/VERSION" 2>/dev/null || echo unknown))."
  if [ -f "$B/database.sql.gz" ]; then
    say "  If the failed update changed the database, restore it in phpMyAdmin → Import with:"
    say "  $B/database.sql.gz"
  fi
  trap - ERR
  cleanup
  exit 0
fi

# ── 1. Is there a package to install? ────────────────────────────────────────────────────────
ZIP="$(ls -1t "$HOME_DIR"/sakila-cpanel-*.zip 2>/dev/null | head -n 1 || true)"
if [ -z "$ZIP" ]; then
  trap - ERR
  [ -t 0 ] && say "Nothing to do: no sakila-cpanel-*.zip in $HOME_DIR."
  cleanup
  exit 0
fi
say "── Sakila deploy: $(basename "$ZIP") ──"

# ── 2. Checks (nothing is changed until all of them pass) ────────────────────────────────────
PHP="$(find_php)" || fail "PHP 8.2 or newer with pdo_mysql + mbstring was not found. cPanel → Select PHP Version → choose 8.2+ and tick the extensions (INSTALL.md step 1). Or run: PHP_BIN=/opt/alt/php82/usr/bin/php bash ~/sakila-deploy.sh"
say "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"
command -v unzip >/dev/null 2>&1 || fail "the 'unzip' command is missing on this server; extract the zip with File Manager instead (INSTALL.md)"
command -v mysql >/dev/null 2>&1 || fail "the 'mysql' command is missing on this server; import the database with phpMyAdmin instead (INSTALL.md)"

FIRST_INSTALL=1
[ -f "$APP/artisan" ] && [ -f "$APP/.env" ] && FIRST_INSTALL=0

mkdir -p "$TMP"
chmod 700 "$TMP"
unzip -q "$ZIP" -d "$TMP" || fail "the zip could not be extracted (upload it again; it may be incomplete)"
[ -f "$TMP/sakila/artisan" ] && [ -f "$TMP/public_html/index.php" ] || fail "this zip is not a Sakila package"
NEW_VERSION="$(cat "$TMP/sakila/VERSION" 2>/dev/null || echo unknown)"

if [ "$FIRST_INSTALL" = "1" ]; then
  ENV_SRC="$HOME_DIR/sakila.env"
  [ -f "$ENV_SRC" ] || fail "first install: upload your settings file as $ENV_SRC (INSTALL.md step 5)"
  # The generated template uses /home/<cpaneluser>: fill in the real home folder.
  sed "s#/home/<cpaneluser>#$HOME_DIR#g" "$ENV_SRC" >"$TMP/env" || fail "could not read $ENV_SRC"
else
  cp "$APP/.env" "$TMP/env"
fi
ENVF="$TMP/env"
for key in APP_KEY APP_URL DB_DATABASE DB_USERNAME DB_PASSWORD; do
  val="$(envget "$ENVF" "$key")"
  [ -n "$val" ] || fail "$key is empty in the settings file"
  case "$val" in *"<"*">"*) fail "$key still contains a <placeholder> in the settings file: fill in the real value" ;; esac
done
# The public address: used in canonical links, emails and payment return URLs.
for key in APP_URL SITE_URL; do
  val="$(envget "$ENVF" "$key")"
  [ -n "$val" ] || continue # SITE_URL may be left out: it then equals APP_URL
  case "$val" in *"<"*">"*) fail "$key still contains a <placeholder> in the settings file: fill in the real value" ;; esac
  case "$val" in
    https://*) ;;
    http://127.0.0.1* | http://localhost*) ;; # local test installs only
    *) fail "$key must be your site's address starting with https:// (now: $val)" ;;
  esac
  case "$val" in */) fail "$key must not end with a slash" ;; esac
done
DB_NAME="$(envget "$ENVF" DB_DATABASE)"
UPLOADS="$(envget "$ENVF" LOCAL_UPLOADS_DIR)"
[ -n "$UPLOADS" ] || UPLOADS="$HOME_DIR/sakila-uploads"
make_mycnf "$ENVF"
db -e "SELECT 1" >/dev/null 2>&1 || fail "cannot connect to database '$DB_NAME' with the DB_* values in the settings file. Create the database and user (INSTALL.md step 2) and check DB_DATABASE / DB_USERNAME / DB_PASSWORD / DB_HOST"
TABLES="$(db -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")"
say "Database '$DB_NAME': reachable, $TABLES tables. $([ "$FIRST_INSTALL" = 1 ] && echo 'First install' || echo "Update from $(cat "$APP/VERSION" 2>/dev/null || echo unknown)") → version $NEW_VERSION"
if [ "$FIRST_INSTALL" = "1" ] && [ "$TABLES" = "0" ] && [ ! -f "$TMP/sakila-database.sql" ]; then
  fail "the database is empty and this package has no sakila-database.sql"
fi

# ── 3. Backup ────────────────────────────────────────────────────────────────────────────────
B="$BACKUPS/$STAMP"
mkdir -p "$B"
if [ "$TABLES" != "0" ]; then
  if command -v mysqldump >/dev/null 2>&1; then
    mysqldump --defaults-extra-file="$MYCNF" --single-transaction --no-tablespaces "$DB_NAME" | gzip >"$B/database.sql.gz" ||
      fail "the database backup failed (nothing was changed)"
    say "Database backup: $B/database.sql.gz ($(du -h "$B/database.sql.gz" | cut -f1))"
  else
    say "! mysqldump is missing: NO database backup was made. Export it in phpMyAdmin if you need one."
  fi
fi

# ── 4. Prepare the new version next to the live one ──────────────────────────────────────────
NEW="$TMP/sakila"
cp "$ENVF" "$NEW/.env"
chmod 600 "$NEW/.env"
chmod -R u+rwX "$NEW/storage" "$NEW/bootstrap/cache"
mkdir -p "$UPLOADS"
[ -w "$UPLOADS" ] || fail "the uploads folder $UPLOADS is not writable"
# Last check before anything live is touched: the new version's own view of the settings
# (URLs, database, uploads folder). The live site would answer 503 with the same list.
if ! CONFIG_REPORT="$(cd "$NEW" && "$PHP" artisan sakila:config-check --no-ansi 2>&1)"; then
  echo "$CONFIG_REPORT"
  fail "the settings file is not complete (see the ERROR lines above); nothing was changed"
fi
echo "$CONFIG_REPORT" | grep '^WARN' | while read -r line; do say "! ${line#WARN   }"; done || true
# Start in maintenance mode so visitors never see new code on an old database.
(cd "$NEW" && "$PHP" artisan down --retry=30 >/dev/null) || fail "the new version does not start with PHP $("$PHP" -r 'echo PHP_VERSION;') (see $NEW/storage/logs)"

# ── 5. Switch ────────────────────────────────────────────────────────────────────────────────
mkdir -p "$WEB"
if [ "$FIRST_INSTALL" = "0" ]; then
  [ -d "$WEB/build" ] && cp -R "$WEB/build" "$B/public_html-build"
  [ -f "$WEB/index.php" ] && cp "$WEB/index.php" "$B/public_html-index.php"
  [ -f "$WEB/.htaccess" ] && cp "$WEB/.htaccess" "$B/public_html-htaccess"
  mv "$APP" "$B/sakila"
else
  # The host's placeholder page would otherwise win over index.php.
  for f in index.html index.htm default.html default.htm default.php; do
    [ -f "$WEB/$f" ] && mv "$WEB/$f" "$B/public_html-$f"
  done
  [ -e "$APP" ] && mv "$APP" "$B/sakila-incomplete"
fi
mv "$NEW" "$APP"
SWAPPED=1
rm -rf "$WEB/build"
cp -R "$TMP/public_html/." "$WEB/" # other files in public_html (.well-known, cgi-bin, …) are left alone

restore_previous() {
  if [ "$FIRST_INSTALL" = "0" ] && [ -d "$B/sakila" ]; then
    mv "$APP" "$BACKUPS/sakila-failed-$STAMP"
    mv "$B/sakila" "$APP"
    rm -rf "$WEB/build"
    [ -d "$B/public_html-build" ] && cp -R "$B/public_html-build" "$WEB/build"
    [ -f "$B/public_html-index.php" ] && cp "$B/public_html-index.php" "$WEB/index.php"
    [ -f "$B/public_html-htaccess" ] && cp "$B/public_html-htaccess" "$WEB/.htaccess"
    artisan up >/dev/null 2>&1 || true
    say "  The previous version's files were put back and the site is online again."
    [ -f "$B/database.sql.gz" ] && say "  If the site misbehaves, restore the database backup in phpMyAdmin: $B/database.sql.gz"
  fi
  return 0
}

# ── 6. Database ──────────────────────────────────────────────────────────────────────────────
if [ "$TABLES" = "0" ]; then
  say "Importing the database (first install)…"
  db <"$TMP/sakila-database.sql" || { restore_previous; fail "the database import failed"; }
fi
if [ "$FIRST_INSTALL" = "1" ] && [ -d "$TMP/sakila-uploads" ]; then
  cp -Rn "$TMP/sakila-uploads/." "$UPLOADS/" 2>/dev/null || true # the site's photos; never overwrites yours
fi
if ! artisan migrate --force; then
  restore_previous
  fail "the database update (migrate) failed"
fi

# ── 7. Go live ───────────────────────────────────────────────────────────────────────────────
artisan view:cache >/dev/null 2>&1 || true
artisan up >/dev/null
rm -f "$HOME_DIR/sakila.env" # the live copy is $APP/.env (keep your own copy somewhere safe)
mv "$ZIP" "$B/"
ZIP=""

URL="$(envget "$APP/.env" APP_URL)"
if command -v curl >/dev/null 2>&1; then
  HEALTH="$(curl -sS -m 20 -o /dev/null -w '%{http_code}' "$URL/api/health" 2>/dev/null || true)"
  if [ "$HEALTH" = "200" ]; then
    say "Health check: $URL/api/health → OK"
  else
    say "! Health check: $URL/api/health answered '${HEALTH:-nothing}'. If the domain or HTTPS is not ready yet this is normal; otherwise see $APP/storage/logs."
  fi
fi

# Keep only the newest backups.
TOTAL="$(ls -1d "$BACKUPS"/20*/ 2>/dev/null | wc -l | tr -d ' ')"
if [ "$TOTAL" -gt "$KEEP_BACKUPS" ]; then
  ls -1d "$BACKUPS"/20*/ | sort | head -n "$((TOTAL - KEEP_BACKUPS))" | while read -r old; do rm -rf "$old"; done
fi

say "✓ Version $NEW_VERSION is live. Backup of the previous state: $B"
if [ "$FIRST_INSTALL" = "1" ]; then
  say "  Next: open $URL/install to create your administrator (INSTALL.md step 8), then add the cron job (step 9)."
  say "  After you have signed in, delete the INSTALL_TOKEN line from $APP/.env."
fi
trap - ERR
cleanup
exit 0
