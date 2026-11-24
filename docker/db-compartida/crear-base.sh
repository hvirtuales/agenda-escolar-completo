#!/bin/sh
# Crea una base de datos y su usuario (mismo nombre) en la MariaDB compartida, con una clave aleatoria.
# Uso (en /docker/db-compartida):
#   ./crear-base.sh educai                 crea la base y el usuario
#   ./crear-base.sh educai --nueva-clave   si el usuario ya existe, le da una clave nueva (solo si el proyecto aún no está
#                                          instalado o va a actualizar su .env: con la clave vieja deja de conectar)
# La clave se muestra UNA sola vez: guárdela (va en el instalador del proyecto).
set -eu
cd "$(dirname "$0")"

die() { echo "ERROR: $*" >&2; exit 1; }

NAME="${1:-}"
RESET="${2:-}"
echo "$NAME" | grep -Eq '^[a-z][a-z0-9_]{1,30}$' || die "indique un nombre en minúsculas, sin espacios (p. ej. ./crear-base.sh educai)"

if docker compose version >/dev/null 2>&1; then DC="docker compose"; else DC="docker-compose"; fi
CID=$($DC ps -q mariadb)
[ -n "$CID" ] || die "la base compartida no está corriendo: docker compose up -d"
[ "$(docker inspect -f '{{.State.Health.Status}}' "$CID")" = "healthy" ] || die "la base compartida aún no está lista (espere y revise: docker compose ps)"

sql() { docker exec -i "$CID" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N'; }

EXISTS=$(echo "SELECT COUNT(*) FROM mysql.user WHERE user='$NAME';" | sql)
PASS=$(openssl rand -base64 24 | tr -d '/+=\n')

if [ "$EXISTS" = "0" ]; then
  USERSQL="CREATE USER '$NAME'@'%' IDENTIFIED BY '$PASS';"
else
  [ "$RESET" = "--nueva-clave" ] || die "el usuario «$NAME» ya existe; no se cambia su clave (use --nueva-clave si el proyecto aún no está instalado)"
  USERSQL="ALTER USER '$NAME'@'%' IDENTIFIED BY '$PASS';"
fi

sql <<SQL
CREATE DATABASE IF NOT EXISTS \`$NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
$USERSQL
GRANT ALL PRIVILEGES ON \`$NAME\`.* TO '$NAME'@'%';
FLUSH PRIVILEGES;
SQL

echo
echo "Listo. Base y usuario «$NAME» en la base compartida."
echo "  Servidor:   db-compartida   (puerto 3306)"
echo "  Base:       $NAME"
echo "  Usuario:    $NAME"
echo "  Contraseña: $PASS"
echo
echo "Guarde la contraseña ahora: no se vuelve a mostrar."
