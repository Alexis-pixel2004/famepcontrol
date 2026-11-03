#!/bin/sh
set -e

# Primer arranque: si no existe .env, lo creamos desde .env.docker
if [ ! -f .env ]; then
    echo "[entrypoint] generando .env desde .env.docker"
    cp .env.docker .env
fi

# Directorios de runtime (en produccion el build excluye storage/cache)
mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

# Limpiar estados cacheados que puedan venir del host o de la imagen
php artisan config:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Ejecuta el comando principal de la imagen (php-fpm por defecto)
exec "$@"