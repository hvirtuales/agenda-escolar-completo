#!/bin/sh
# Arranque del contenedor de Agenda Escolar.
set -e
cd /var/www/html

# El volumen de writable/ puede llegar vacío o con otro dueño: asegura sus carpetas y permisos.
for d in cache logs session uploads debugbar; do
  mkdir -p "writable/$d"
done
chown www-data:www-data writable writable/cache writable/logs writable/session writable/uploads writable/debugbar
find writable -not -user www-data -exec chown www-data:www-data {} + 2>/dev/null || true

if [ -f writable/installed.lock ]; then
  # Ya instalada: el instalador web no se publica.
  rm -f public/install.php

  # Opcional: aplica las migraciones pendientes al arrancar (tras actualizar la imagen).
  if [ "${AUTO_MIGRATE:-0}" = "1" ]; then
    su -s /bin/sh www-data -c "php spark migrate --all" || echo "Aviso: no se pudieron aplicar las migraciones; use Consola › Configuración › Base de datos." >&2
  fi
fi

exec "$@"
