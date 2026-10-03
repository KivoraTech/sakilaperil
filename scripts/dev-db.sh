#!/usr/bin/env bash
# Local development database (macOS + XAMPP): starts a private MariaDB on 127.0.0.1:3307 from
# XAMPP's binaries, without sudo and without touching XAMPP's own databases. `.env` points at it
# (DB_PORT=3307). It does not survive a reboot: run this again when `php artisan serve` or the
# tests cannot connect.
#
#   scripts/dev-db.sh           start (creates the data folder and the databases on first run)
#   scripts/dev-db.sh stop
#   scripts/dev-db.sh status
#
# Prefer XAMPP's own MySQL (port 3306, started from the XAMPP manager)? Set DB_PORT=3306 in .env,
# create the databases `sakila_laravel` and `sakila_laravel_test` in phpMyAdmin and skip this script.
set -euo pipefail
XAMPP="${XAMPP_HOME:-/Applications/XAMPP/xamppfiles}"
DATA="${SAKILA_DB_HOME:-$HOME/.cache/sakila-mariadb-scratch}"
PORT="${SAKILA_DB_PORT:-3307}"
MYSQL="$XAMPP/bin/mysql"
[[ -x "$XAMPP/sbin/mysqld" ]] || { echo "XAMPP not found at $XAMPP (set XAMPP_HOME, or use any MySQL 8 / MariaDB 10.4+ and set DB_* in .env)." >&2; exit 1; }
up() { "$MYSQL" -h 127.0.0.1 -P "$PORT" -u root -e "SELECT 1" >/dev/null 2>&1; }

case "${1:-start}" in
  status)
    if up; then echo "running on 127.0.0.1:$PORT (data: $DATA)"; else echo "not running"; exit 1; fi ;;
  stop)
    if [[ -f "$DATA/mysqld.pid" ]]; then kill "$(cat "$DATA/mysqld.pid")" && echo "stopped"; else echo "not running"; fi ;;
  start)
    if up; then echo "already running on 127.0.0.1:$PORT"; else
      mkdir -p "$DATA"
      if [[ ! -d "$DATA/data/mysql" ]]; then
        echo "First run: creating the data folder in $DATA/data"
        "$XAMPP/bin/mysql_install_db" --no-defaults --basedir="$XAMPP" --datadir="$DATA/data" --auth-root-authentication-method=normal >/dev/null
      fi
      rm -f "$DATA/mysqld.sock"
      nohup "$XAMPP/sbin/mysqld" --no-defaults --basedir="$XAMPP" --datadir="$DATA/data" --port="$PORT" --bind-address=127.0.0.1 \
        --socket="$DATA/mysqld.sock" --pid-file="$DATA/mysqld.pid" --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci --log-error="$DATA/error.log" >/dev/null 2>&1 &
      for _ in $(seq 1 40); do up && break; sleep 0.5; done
      up || { echo "MariaDB did not start; see $DATA/error.log" >&2; exit 1; }
      echo "started on 127.0.0.1:$PORT"
    fi
    for db in sakila_laravel sakila_laravel_test; do
      "$MYSQL" -h 127.0.0.1 -P "$PORT" -u root -e "CREATE DATABASE IF NOT EXISTS \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    done
    echo "databases ready: sakila_laravel, sakila_laravel_test (user root, no password)" ;;
  *) echo "usage: $0 [start|stop|status]" >&2; exit 1 ;;
esac
