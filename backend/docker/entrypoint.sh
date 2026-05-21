#!/usr/bin/env bash
set -e

cd /var/www

# This script must start as root so php-fpm can open its error log on
# /proc/self/fd/2 and fork worker processes with a different UID. After all
# setup steps run as www-data via `su-exec`, we hand the final command to:
#   - php-fpm: AS ROOT — the master process drops workers to www-data per
#     the user/group directives in docker/php/www.conf.
#   - anything else (artisan queue:work, php-cli, sh, etc.): AS www-data.

if [ "$(id -u)" != "0" ]; then
    echo "[entrypoint] must start as root (UID 0); got $(id -u)" >&2
    exit 1
fi

# Fix ownership on freshly-created volumes. Docker creates named/anonymous
# volumes owned by root the first time a path is mounted over a bind mount,
# which would otherwise lock www-data out of vendor / bootstrap/cache / storage.
chown -R www-data:www-data \
    /var/www/vendor \
    /var/www/bootstrap/cache \
    /var/www/storage \
    2>/dev/null || true

# Bootstrap composer dependencies.
#
# Subtlety: the production image bakes a `--no-dev` vendor at /var/www/vendor.
# When the empty erp_vendor named volume first mounts there, Docker COPIES the
# image's no-dev vendor into the volume — so `vendor/autoload.php` exists, but
# require-dev packages (laravel/pail, pestphp/pest, etc.) are absent. In local
# mode we install with dev; in production we only install if vendor is missing.
APP_ENV_RESOLVED="${APP_ENV:-production}"
if [ "$APP_ENV_RESOLVED" = "local" ]; then
    if [ ! -f vendor/autoload.php ] || ! grep -q '"dev": true' vendor/composer/installed.json 2>/dev/null; then
        echo "[entrypoint] Installing composer dependencies (local, with dev)"
        su-exec www-data:www-data composer install \
            --no-interaction \
            --no-progress \
            --prefer-dist
    fi
elif [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Installing composer dependencies (production, no-dev)"
    su-exec www-data:www-data composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader
fi

# Ensure APP_KEY exists for local dev convenience.
if [ -f artisan ] && [ -z "${APP_KEY:-}" ]; then
    su-exec www-data:www-data php artisan key:generate --force || true
fi

# Passport key permissions. Windows host bind mounts surface keys as 755,
# which League\OAuth2\Server\CryptKey rejects with an E_USER_NOTICE that
# php-fpm escalates to a fatal. Force 600 on each boot.
if [ -f storage/oauth-private.key ]; then
    chmod 600 storage/oauth-private.key storage/oauth-public.key 2>/dev/null || true
fi

# Refresh framework caches each boot (cheap, avoids stale-config surprises).
if [ -f artisan ]; then
    su-exec www-data:www-data php artisan config:clear || true
    su-exec www-data:www-data php artisan route:clear  || true
    su-exec www-data:www-data php artisan view:clear   || true
fi

# Hand off to the actual command.
case "$1" in
    php-fpm|/usr/local/sbin/php-fpm)
        # php-fpm master stays root; workers drop to www-data per www.conf.
        exec "$@"
        ;;
    *)
        # Everything else (queue:work, artisan, sh, etc.) runs as www-data.
        exec su-exec www-data:www-data "$@"
        ;;
esac
