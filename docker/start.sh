#!/bin/sh
set -e

# Cache configuration first so every process sees the same environment.
php artisan config:cache

# Managed databases and sidecars can take longer to accept connections than
# the application container. Retry instead of crash-looping on the first
# failed migration.
ATTEMPTS=0

until php artisan migrate --force --no-interaction; do
    ATTEMPTS=$((ATTEMPTS + 1))

    if [ "$ATTEMPTS" -ge 30 ]; then
        echo "Database migrations failed after ${ATTEMPTS} attempts." >&2
        exit 1
    fi

    echo "Database not ready (attempt ${ATTEMPTS}/30), retrying in 2s..." >&2
    sleep 2
done

exec /usr/bin/supervisord -c /etc/supervisord.conf
