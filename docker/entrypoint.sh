#!/bin/sh
# Container entrypoint shared by the app, queue and scheduler services.
# CONTAINER_ROLE decides the behaviour:
#   app       -> wait for DB, storage:link, migrate, warm/clear caches, php-fpm
#   queue     -> wait for DB + finished migrations, then run the given command
#   scheduler -> same waiting rules as queue
set -e

ROLE="${CONTAINER_ROLE:-app}"
cd /var/www/html

log() { echo "[entrypoint:${ROLE}] $1"; }

wait_for_db() {
    # why: works in BOTH database modes (host PostgreSQL and the compose
    # `postgres` service), unlike a depends_on which requires the service
    # to exist in the compose file.
    [ "${DB_CONNECTION:-pgsql}" = "sqlite" ] && return 0
    log "waiting for database at ${DB_HOST}:${DB_PORT:-5432}..."
    php -r '
        $h = getenv("DB_HOST"); $p = getenv("DB_PORT") ?: 5432;
        $d = getenv("DB_DATABASE"); $u = getenv("DB_USERNAME"); $w = getenv("DB_PASSWORD");
        for ($i = 0; $i < 60; $i++) {
            try { new PDO("pgsql:host=$h;port=$p;dbname=$d", $u, $w, [PDO::ATTR_TIMEOUT => 2]); exit(0); }
            catch (Throwable $e) { sleep(1); }
        }
        fwrite(STDERR, "database not reachable after 60s: ".$e->getMessage()."\n");
        exit(1);
    '
    log "database is up."
}

wait_for_migrations() {
    # Queue/scheduler must not touch tables the app container is still creating.
    log "waiting for migrations to finish..."
    i=0
    until php artisan migrate:status --no-ansi >/dev/null 2>&1 \
        && ! php artisan migrate:status --no-ansi 2>/dev/null | grep -q Pending; do
        sleep 2
        i=$((i + 2))
        if [ "$i" -ge 180 ]; then
            log "WARNING: migrations still pending after 180s - continuing anyway."
            break
        fi
    done
}

ensure_app_key() {
    if [ -z "$APP_KEY" ]; then
        if [ "$APP_ENV" = "production" ]; then
            log "FATAL: APP_KEY is empty. Set it in the environment (php artisan key:generate --show)."
            exit 1
        fi
        # why: only possible in dev, where .env is bind-mounted and writable.
        if [ -f .env ]; then
            log "APP_KEY empty - generating one into .env"
            php artisan key:generate --force
        fi
    fi
}

wait_for_db

case "$ROLE" in
    app)
        ensure_app_key
        [ -L public/storage ] || php artisan storage:link || true
        log "running migrations..."
        php artisan migrate --force
        if [ "$APP_ENV" = "production" ]; then
            # config + routes + views + events in one go.
            log "caching configuration for production..."
            php artisan optimize
        else
            # why: stale caches after switching branches are the #1 dev footgun.
            php artisan optimize:clear >/dev/null
        fi
        ;;
    queue|scheduler)
        wait_for_migrations
        ;;
esac

log "starting: $*"
exec "$@"
