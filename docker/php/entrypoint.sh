#!/bin/sh
set -e

cd /var/www/html

# APP_KEY: taken from the platform when set. Otherwise a key is generated once
# and kept on the storage-private volume, so encrypted data and sessions
# survive redeploys. Pin it on the platform later (copy the file's contents)
# so it also survives the volume being reset.
APP_KEY_FILE=storage/app/private/.app-key

if [ -z "${APP_KEY:-}" ]; then
    if [ -s "$APP_KEY_FILE" ]; then
        APP_KEY="$(cat "$APP_KEY_FILE")"
        echo "Loaded APP_KEY from $APP_KEY_FILE"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
        printf '%s' "$APP_KEY" > "$APP_KEY_FILE"
        chmod 600 "$APP_KEY_FILE"
        echo "Generated a new APP_KEY and stored it at $APP_KEY_FILE"
    fi
    export APP_KEY
fi

# Fortify only falls back to APP_KEY when this is unset, never when it is empty.
if [ -z "${PASSKEYS_USER_HANDLE_SECRET:-}" ]; then
    export PASSKEYS_USER_HANDLE_SECRET="$APP_KEY"
fi

# Only the `app` service sets RUN_MIGRATIONS, so queue workers never race it.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Waiting for database..."
    until php artisan db:show >/dev/null 2>&1; do
        sleep 2
    done

    php artisan migrate --force
fi

# Cache config/routes/views using the environment the platform injected.
# The cached config carries APP_KEY, so PHP-FPM workers use the resolved key.
php artisan optimize

exec "$@"
