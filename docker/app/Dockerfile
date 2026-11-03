# FAMEP - imagen de la aplicacion (php-fpm)
FROM php:8.3-fpm-alpine

# INSTALL_DEV=true  => perfil desarrollo (bind-mount + dependencias dev)
# INSTALL_DEV=false => perfil produccion (dependencias de runtime nada mas)
ARG INSTALL_DEV=true

# Extensines que Laravel y la conexion Postgres necesitan
RUN apk add --no-cache \
        git \
        unzip \
        postgresql-libs \
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        icu-dev \
        libzip-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
    && docker-php-ext-enable opcache \
    && apk del --no-cache .build-deps

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Limites de subida de PHP (evidencias fotograficas): alineados con nginx
COPY docker/app/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

WORKDIR /var/www/html

# Copia del codigo (el .dockerignore excluye vendor, node_modules, storage, .env)
COPY . .

# Instalar dependencias segun el perfil
RUN if [ "$INSTALL_DEV" = "true" ]; then \
        composer install --no-interaction --prefer-dist --optimize-autoloader; \
    else \
        composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader; \
    fi

# Permisos para que nginx (www-data) pueda escribir logs y cache de vistas
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

# Entrypoint: bootstrap del .env, cache:clear y arranque de php-fpm
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]