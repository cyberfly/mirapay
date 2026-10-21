#!/usr/bin/env bash
set -e

cd /var/www/html

# Uploaded files (storage/app/public) live in a shared volume that nginx mounts
# directly at public/storage, so no storage:link is needed here.

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Waiting for database..."
    until php artisan db:show >/dev/null 2>&1; do
        sleep 2
    done

    php artisan migrate --force
fi

php artisan optimize

exec "$@"
