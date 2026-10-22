#!/usr/bin/env bash
#
# Container entrypoint for the MirApay Laravel application.
#
# Every configuration value is read from the process environment (set on the
# deploy platform), so no .env file is shipped inside the image. The
# script prepares the mounted storage volume, resolves an APP_KEY, waits for
# the database, optionally migrates, warms the framework caches and then hands
# over to the container command.
#
# Recognised control variables:
#   CONTAINER_ROLE   app | queue | scheduler   (default: app)
#   RUN_MIGRATIONS   true | false              (default: true for role "app")
#   RUN_SEEDERS      true | false              (default: false)
#   WAIT_FOR_DB      true | false              (default: true)
#   OPTIMIZE_CACHES  true | false              (default: true)

set -euo pipefail

APP_ROOT=/var/www/html
CONTAINER_ROLE="${CONTAINER_ROLE:-app}"
RUN_SEEDERS="${RUN_SEEDERS:-false}"
WAIT_FOR_DB="${WAIT_FOR_DB:-true}"
OPTIMIZE_CACHES="${OPTIMIZE_CACHES:-true}"
DB_WAIT_TIMEOUT="${DB_WAIT_TIMEOUT:-60}"
APP_KEY_FILE="${APP_KEY_FILE:-$APP_ROOT/storage/app/private/.app-key}"

if [ -z "${RUN_MIGRATIONS:-}" ]; then
    if [ "$CONTAINER_ROLE" = "app" ]; then
        RUN_MIGRATIONS=true
    else
        RUN_MIGRATIONS=false
    fi
fi

cd "$APP_ROOT"

log() {
    printf '[entrypoint] %s\n' "$*"
}

as_web_user() {
    if [ "$(id -u)" = "0" ]; then
        setpriv --reuid=www-data --regid=www-data --clear-groups "$@"
    else
        "$@"
    fi
}

# ---------------------------------------------------------------------------
# Storage volume. The host bind mount arrives empty on a fresh install and
# would otherwise hide the directory tree baked into the image.
# ---------------------------------------------------------------------------
prepare_storage() {
    log "preparing storage directories"

    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

    if [ "$(id -u)" = "0" ]; then
        chown -R www-data:www-data storage bootstrap/cache
    fi
    chmod -R ug+rwX storage bootstrap/cache

    # public/storage -> storage/app/public, recreated because the symlink
    # target only exists once the volume is mounted.
    as_web_user php artisan storage:link --force --quiet || \
        log "warning: could not create the public storage symlink"
}

# ---------------------------------------------------------------------------
# Application key. Supplied through APP_KEY in normal deployments; otherwise a
# key is generated once and persisted on the storage volume so that encrypted
# data and sessions survive a restart.
# ---------------------------------------------------------------------------
resolve_app_key() {
    if [ -n "${APP_KEY:-}" ]; then
        return
    fi

    if [ -s "$APP_KEY_FILE" ]; then
        APP_KEY="$(cat "$APP_KEY_FILE")"
        export APP_KEY
        log "loaded APP_KEY from $APP_KEY_FILE"
        return
    fi

    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY

    mkdir -p "$(dirname "$APP_KEY_FILE")"
    printf '%s' "$APP_KEY" > "$APP_KEY_FILE"
    chmod 600 "$APP_KEY_FILE"
    if [ "$(id -u)" = "0" ]; then
        chown www-data:www-data "$APP_KEY_FILE"
    fi

    log "generated a new APP_KEY and stored it at $APP_KEY_FILE"
    log "set APP_KEY in the environment to pin it explicitly"
}

# Fortify only falls back to APP_KEY when this is unset, never when empty.
resolve_passkeys_secret() {
    if [ -z "${PASSKEYS_USER_HANDLE_SECRET:-}" ]; then
        PASSKEYS_USER_HANDLE_SECRET="$APP_KEY"
        export PASSKEYS_USER_HANDLE_SECRET
    fi
}

wait_for_database() {
    if [ "$WAIT_FOR_DB" != "true" ]; then
        return
    fi

    local host="${DB_HOST:-mysql}"
    local port="${DB_PORT:-3306}"
    local waited=0

    log "waiting for the database at ${host}:${port}"

    until mysqladmin ping --host="$host" --port="$port" --silent >/dev/null 2>&1; do
        waited=$((waited + 2))
        if [ "$waited" -ge "$DB_WAIT_TIMEOUT" ]; then
            log "database not reachable after ${DB_WAIT_TIMEOUT}s, giving up"
            exit 1
        fi
        sleep 2
    done

    log "database is up"
}

run_migrations() {
    if [ "$RUN_MIGRATIONS" != "true" ]; then
        return
    fi

    log "running migrations"
    as_web_user php artisan migrate --force --no-interaction

    if [ "$RUN_SEEDERS" = "true" ]; then
        log "running seeders"
        as_web_user php artisan db:seed --force --no-interaction
    fi
}

warm_caches() {
    if [ "$OPTIMIZE_CACHES" != "true" ]; then
        return
    fi

    log "caching configuration, routes, views and events"
    as_web_user php artisan optimize:clear --quiet
    as_web_user php artisan config:cache --quiet
    as_web_user php artisan route:cache --quiet
    as_web_user php artisan view:cache --quiet
    as_web_user php artisan event:cache --quiet
}

log "starting container role: $CONTAINER_ROLE"

resolve_app_key
resolve_passkeys_secret
prepare_storage
wait_for_database
run_migrations
warm_caches

log "handing over to: $*"

# PHP-FPM drops its workers to www-data itself; anything else (queue worker,
# scheduler) runs as www-data so files it writes to storage stay writable.
if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

if [ "$(id -u)" = "0" ]; then
    exec setpriv --reuid=www-data --regid=www-data --clear-groups "$@"
fi

exec "$@"
