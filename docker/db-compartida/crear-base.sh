#!/bin/sh
# Crea una base de datos y su usuario (mismo nombre) en la MariaDB compartida, con una clave aleatoria.
# Uso (en /opt/db-compartida):  ./crear-base.sh educai
# La clave se muestra UNA sola vez: guárdela (va en el instalador o en el .env del proyecto).
set -eu
cd "$(dirname "$0")"

die() { echo "ERROR: $*" >&2; exit 1; }

NAME="${1:-}"
echo "$NAME" | grep -Eq '^[a-z][a-z0-9_]{1,30}$' || die "indique un nombre en minúsculas, sin espacios (p. ej. ./crear-base.sh educai)"

if docker compose version >/dev/null 2>&1; then DC="docker compose"; else DC="docker-compose"; fi
CID=$($DC ps -q mariadb)
[ -n "$CID" ] || die "la base compartida no está corriendo: docker compose up -d"
[ "$(docker inspect -f '{{.State.Health.Status}}' "$CID")" = "healthy" ] || die "la base compartida aún no está lista (espere y revise: docker compose ps)"

sql() { docker exec -i "$CID" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N'; }

EXISTS=$(echo "SELECT COUNT(*) FROM mysql.user WHERE user='$NAME';" | sql)
[ "$EXISTS" = "0" ] || die "el usuario «$NAME» ya existe; no se cambia su clave (el proyecto que lo usa dejaría de conectar)"

PASS=$(openssl rand -base64 24 | tr -d '/+=\n')

sql <<SQL
CREATE DATABASE IF NOT EXISTS \`$NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$NAME'@'%' IDENTIFIED BY '$PASS';
GRANT ALL PRIVILEGES ON \`$NAME\`.* TO '$NAME'@'%';
FLUSH PRIVILEGES;
SQL

echo
echo "Listo. Base y usuario «$NAME» creados en la base compartida."
echo "  Servidor:   db-compartida   (puerto 3306)"
echo "  Base:       $NAME"
echo "  Usuario:    $NAME"
echo "  Contraseña: $PASS"
echo
echo "Guarde la contraseña ahora: no se vuelve a mostrar."
