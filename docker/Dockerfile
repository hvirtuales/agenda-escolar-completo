# Agenda Escolar en Docker: PHP 8.4 + Apache, con la raíz web en public/.
# Se construye desde la raíz del proyecto (docker-compose.yml ya lo hace): docker build -f docker/Dockerfile .
#
# Versiones fijadas con su huella (sha256): la imagen no cambia sola. Para traer parches nuevos, actualice las
# etiquetas y huellas de abajo (o deje que Renovate/Dependabot lo haga) y reconstruya: docker compose build --pull
#
# Dos etapas: «build» compila las extensiones y genera vendor/; la imagen final solo lleva lo necesario para ejecutar
# (sin paquetes -dev, compiladores, unzip ni Composer).

ARG PHP_IMAGE=php:8.4.28-apache-trixie@sha256:74bda556239f67022b9bdcc8f1c27c76bb61adcf19a98110f806e949a17acfc6
ARG COMPOSER_IMAGE=composer:2.11.2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b

FROM ${COMPOSER_IMAGE} AS composer

# ---------------------------------------------------------------- Etapa de compilación
FROM ${PHP_IMAGE} AS build

RUN apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev unzip \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" intl mysqli zip gd opcache

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY app ./app
RUN composer dump-autoload --no-dev --optimize

# ---------------------------------------------------------------- Imagen final
FROM ${PHP_IMAGE}

# Parches de Debian publicados después de la imagen base + solo las librerías de ejecución de las extensiones.
# Luego se quitan las herramientas de compilación que trae la imagen oficial ($PHPIZE_DEPS: gcc, make…).
RUN apt-get update \
 && apt-get upgrade -y \
 && apt-get install -y --no-install-recommends libicu76 libzip5 libpng16-16t64 libjpeg62-turbo libwebp7 libfreetype6 \
 && apt-mark manual libicu76 libzip5 libpng16-16t64 libjpeg62-turbo libwebp7 libfreetype6 \
 && apt-get purge -y --auto-remove $PHPIZE_DEPS \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers

# Extensiones compiladas en la etapa anterior.
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN php -m | grep -qiE '^intl$' && php -m | grep -qiE '^mysqli$' && php -m | grep -qiE '^zip$' && php -m | grep -qiE '^gd$' && php -m | grep -qi 'opcache'

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-agenda-escolar.ini

WORKDIR /var/www/html
COPY --chown=root:root . .
COPY --from=build --chown=root:root /app/vendor ./vendor

# - El código es de root (PHP no puede modificarlo); solo writable/ es de www-data.
# - .env vive en el volumen de writable/ (sobrevive a recrear el contenedor). El enlace se crea aquí y la raíz sigue siendo de root;
#   el instalador escribe a través del enlace en writable/.env (vacío hasta instalar).
RUN rm -f .env && ln -s writable/.env .env \
 && mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar \
 && touch writable/.env && chmod 0640 writable/.env \
 && chown -R www-data:www-data writable \
 && sed -i 's/\r$//' docker/entrypoint.sh \
 && install -m 0755 docker/entrypoint.sh /usr/local/bin/agenda-entrypoint

VOLUME ["/var/www/html/writable"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/robots.txt") === false ? 1 : 0);'

ENTRYPOINT ["agenda-entrypoint"]
CMD ["apache2-foreground"]
