#!/bin/sh
# PHP container entrypoint.
# Ensures storage/ and bootstrap/cache/ are writable by php-fpm (www-data)
# regardless of what previously wrote to them. Bind-mounted host files can
# end up owned by root (from `docker exec` commands) or the host user, and
# Laravel needs to write logs, compiled views, cached config, and sessions.

set -e

WEB_DIRS="storage bootstrap/cache"

if [ -d /var/www/html/storage ]; then
    # Only chown if not already owned by www-data — avoids slow recursive
    # chown on every restart when perms are already correct.
    for dir in $WEB_DIRS; do
        if [ -d "/var/www/html/$dir" ]; then
            current_owner=$(stat -c '%U' "/var/www/html/$dir" 2>/dev/null || echo "")
            if [ "$current_owner" != "www-data" ]; then
                echo "entrypoint: fixing ownership on $dir"
                chown -R www-data:www-data "/var/www/html/$dir" 2>/dev/null || true
            fi
        fi
    done

    # chmod is idempotent and cheap — always enforce writable perms.
    chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
fi

exec "$@"
