# Agenda Escolar en Docker: PHP 8.3 + Apache, con la raíz web en public/.
# Se construye desde la raíz del proyecto (docker-compose.yml ya lo hace): docker build -f docker/Dockerfile .
FROM php:8.3-apache

# Extensiones que pide la plataforma (intl, mysqli, zip, gd) y opcache. mbstring, curl, fileinfo y openssl ya vienen en la imagen.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev unzip \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" intl mysqli zip gd opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-agenda-escolar.ini

WORKDIR /var/www/html

# Dependencias primero (capa en caché mientras composer.lock no cambie).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .

# - .env vive en el volumen de writable/ (así sobrevive a recrear el contenedor); el instalador lo escribe a través del enlace.
# - La raíz del proyecto es de www-data para que el instalador pueda crear el enlace/respaldo del .env.
RUN composer dump-autoload --no-dev --optimize \
 && rm -f .env && ln -s writable/.env .env \
 && mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar \
 && chown www-data:www-data /var/www/html \
 && chown -R www-data:www-data writable \
 && sed -i 's/\r$//' docker/entrypoint.sh \
 && install -m 0755 docker/entrypoint.sh /usr/local/bin/agenda-entrypoint

VOLUME ["/var/www/html/writable"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/robots.txt") === false ? 1 : 0);'

ENTRYPOINT ["agenda-entrypoint"]
CMD ["apache2-foreground"]
