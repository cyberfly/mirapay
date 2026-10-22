#!/usr/bin/env bash
# Runs as root: prepares storage, the app key, the database and Laravel's caches,
# then hands off to php-fpm (as root, it drops to www-data itself) or to the
# container command as www-data.
set -euo pipefail

cd /var/www/html

as_www() {
    setpriv --reuid=www-data --regid=www-data --init-groups "$@"
}

artisan() {
    as_www php artisan "$@"
}

# Storage is a bind mount that may start empty.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache

# APP_KEY: use the platform value, else the one saved in storage, else generate one.
key_file=storage/app/private/.app-key

if [ -z "${APP_KEY:-}" ]; then
    if [ -s "$key_file" ]; then
        APP_KEY="$(cat "$key_file")"
    else
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        (umask 077 && printf '%s' "$APP_KEY" > "$key_file")
        chmod 600 "$key_file"
        chown www-data:www-data "$key_file"
        echo "entrypoint: generated APP_KEY and saved it to $key_file. Set APP_KEY on the platform." >&2
    fi
    export APP_KEY
fi

# Fortify only falls back to APP_KEY when this is unset, not when it is empty.
if [ -z "${PASSKEYS_USER_HANDLE_SECRET:-}" ]; then
    export PASSKEYS_USER_HANDLE_SECRET="$APP_KEY"
fi

artisan storage:link --force

# Wait for the database.
db_host="${DB_HOST:-mysql}"
db_port="${DB_PORT:-3306}"
deadline=$((SECONDS + 60))

until MYSQL_PWD="${DB_PASSWORD:-}" mysqladmin ping -h "$db_host" -P "$db_port" -u "${DB_USERNAME:-root}" --silent >/dev/null 2>&1; do
    if [ "$SECONDS" -ge "$deadline" ]; then
        echo "entrypoint: database at $db_host:$db_port not reachable after 60s" >&2
        exit 1
    fi
    sleep 2
done

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    artisan migrate --force

    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        artisan db:seed --force
    fi
fi

artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
