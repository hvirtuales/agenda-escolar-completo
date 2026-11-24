#!/bin/sh
# Pasa Agenda Escolar de su MariaDB propia a la MariaDB compartida del VPS (docker/db-compartida/).
#
# Uso (en /var/www/html/docker):
#   ./migrar-a-db-compartida.sh             migra (pide la clave del usuario creado con crear-base.sh)
#   ./migrar-a-db-compartida.sh --revertir  vuelve a la MariaDB propia (sus datos quedan intactos)
#
# Qué hace: respaldo → detiene la app → copia la base → compara conteos → cambia la conexión en el .env de la app →
# arranca la app con docker-compose.externa.yml → detiene (no borra) la MariaDB propia.
# Si algo falla antes del cambio, la app vuelve a arrancar con la MariaDB propia, como estaba.
set -eu
cd "$(dirname "$0")"

die()  { echo; echo "ERROR: $*" >&2; exit 1; }
step() { echo; echo "==> $*"; }

[ -f docker-compose.yml ] && [ -f docker-compose.externa.yml ] && [ -f .env ] || die "córralo dentro de /var/www/html/docker (con docker/.env creado)"
if docker compose version >/dev/null 2>&1; then DC="docker compose"; else DC="docker-compose"; fi
OWN="$DC -f docker-compose.yml"
EXT="$DC -f docker-compose.externa.yml"
SHARED_HOST="db-compartida"
TABLES="users institutions institution_users audit_logs migrations"

# Volumen writable/ de la app (el nombre depende de la versión de Compose: se toma del contenedor).
app_volume() {
  cid=$($DC -f "$1" ps -aq app | head -n1)
  [ -n "$cid" ] || die "no encuentro el contenedor de la app"
  docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/writable"}}{{.Name}}{{end}}{{end}}' "$cid"
}
# Ejecuta un comando sobre el volumen writable/ con la imagen de la app (funciona aunque la app esté detenida).
on_volume() { docker run --rm --entrypoint sh -v "$VOL":/w agenda-escolar:latest -c "$1"; }

# ---------------------------------------------------------------- Vuelta atrás
if [ "${1:-}" = "--revertir" ]; then
  VOL=$(app_volume docker-compose.externa.yml 2>/dev/null || app_volume docker-compose.yml)
  step "Restaurando el .env de la app de antes de la migración"
  on_volume '[ -f /w/.env.antes-migracion ] && cp /w/.env.antes-migracion /w/.env && chown www-data:www-data /w/.env && chmod 640 /w/.env' \
    || die "no existe writable/.env.antes-migracion (¿se llegó a migrar?)"
  step "Volviendo al modo con MariaDB propia"
  sed -i '/^COMPOSE_FILE=/d' .env
  $OWN up -d
  echo; echo "Listo: la app usa otra vez su MariaDB propia (agenda-escolar-db)."
  exit 0
fi

# ---------------------------------------------------------------- Comprobaciones
step "Comprobaciones"
grep -q '^COMPOSE_FILE=docker-compose.externa.yml' .env && die "ya está migrada (docker/.env usa docker-compose.externa.yml)"

OWN_DB=$($OWN ps -q db)
APP=$($OWN ps -q app)
[ -n "$OWN_DB" ] && [ -n "$APP" ] || die "la app y su MariaDB propia deben estar corriendo: $OWN up -d"
VOL=$(app_volume docker-compose.yml)
[ -n "$VOL" ] || die "no encuentro el volumen writable/ de la app"

docker network inspect db-compartida >/dev/null 2>&1 || die "no existe la red db-compartida: levante primero /opt/db-compartida"
SHARED=$(docker ps -q --filter network=db-compartida --filter label=com.docker.compose.service=mariadb | head -n1)
[ -n "$SHARED" ] || die "no encuentro el contenedor de la base compartida (cd /opt/db-compartida && docker compose ps)"
[ "$(docker inspect -f '{{.State.Health.Status}}' "$SHARED")" = "healthy" ] || die "la base compartida no está lista (healthy)"

# Base y usuario actuales (del .env de la app); en la compartida se usan los mismos nombres.
envval() { on_volume 'cat /w/.env' | sed -n "s/^database\.default\.$1 *= *//p" | head -n1 | sed -e "s/^'//" -e "s/' *\$//"; }
DB=$(envval database); DBUSER=$(envval username)
[ -n "$DB" ] && [ -n "$DBUSER" ] || die "no pude leer la base y el usuario del .env de la app"
echo "Base: $DB · usuario: $DBUSER · destino: $SHARED_HOST"

printf "Contraseña del usuario «%s» en la base compartida (la que dio crear-base.sh): " "$DBUSER"
stty -echo 2>/dev/null || true; read -r PW; stty echo 2>/dev/null || true; echo
echo "$PW" | grep -Eq '^[A-Za-z0-9._-]{8,}$' || die "la contraseña debe tener al menos 8 caracteres: letras, números, punto, guion o guion bajo"

# La app debe poder conectarse a la compartida con ese usuario, y la base destino debe estar vacía.
docker exec -e P="$PW" -e U="$DBUSER" -e D="$DB" "$SHARED" sh -c 'MYSQL_PWD="$P" mariadb -u"$U" -e "SELECT 1" "$D"' >/dev/null \
  || die "no se pudo entrar a la base compartida como «$DBUSER» (¿corrió ./crear-base.sh $DB? ¿la contraseña es correcta?)"
N=$(docker exec -e D="$DB" "$SHARED" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"$D\""')
[ "$N" = "0" ] || die "la base «$DB» en la compartida no está vacía ($N tablas); no se toca"

# ---------------------------------------------------------------- Respaldo
STAMP=$(date +%Y%m%d-%H%M%S)
BK=/root/respaldos
mkdir -p "$BK"; chmod 700 "$BK"
dump() { $OWN exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb-dump -uroot --single-transaction --routines --triggers --events --default-character-set=utf8mb4 "$1"' sh "$DB"; }

step "Respaldo (con la plataforma en línea) → $BK/$DB-antes-migracion-$STAMP.sql"
dump > "$BK/$DB-antes-migracion-$STAMP.sql"
on_volume 'cp /w/.env /w/.env.antes-migracion && chmod 640 /w/.env.antes-migracion && chown www-data:www-data /w/.env.antes-migracion'
on_volume 'cat /w/.env' > "$BK/agenda-escolar-env-$STAMP"; chmod 600 "$BK/agenda-escolar-env-$STAMP"

# A partir de aquí la app está detenida: si algo falla, se vuelve a arrancar como estaba.
SWITCHED=0
restore() { if [ "$SWITCHED" = "0" ]; then echo; echo "Algo falló: arranco la app otra vez con su MariaDB propia (nada cambió)."; $OWN start app || true; fi; }
trap restore EXIT

step "Deteniendo la app (unos minutos fuera de línea, para que no cambien datos durante la copia)"
$OWN stop app

step "Copiando la base"
dump > "$BK/$DB-migracion-$STAMP.sql"
docker exec -i -e D="$DB" "$SHARED" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot "$D"' < "$BK/$DB-migracion-$STAMP.sql"

step "Comparando conteos (origen → destino)"
count() { # $1 = contenedor, $2 = tabla
  docker exec -e D="$DB" -e T="$2" "$1" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N -e "SELECT COUNT(*) FROM \`$T\`" "$D"'
}
for t in $TABLES; do
  a=$(count "$OWN_DB" "$t"); b=$(count "$SHARED" "$t")
  echo "  $t: $a → $b"
  [ "$a" = "$b" ] || die "los conteos de «$t» no coinciden"
done

step "Cambiando la conexión en el .env de la app"
on_volume "sed -i \
  -e \"s|^database\.default\.hostname *=.*|database.default.hostname = '$SHARED_HOST'|\" \
  -e \"s|^database\.default\.password *=.*|database.default.password = '$PW'|\" \
  -e \"s|^database\.default\.port *=.*|database.default.port = 3306|\" /w/.env"

step "Arrancando la app con la base compartida"
sed -i '/^COMPOSE_FILE=/d' .env
echo "COMPOSE_FILE=docker-compose.externa.yml" >> .env
SWITCHED=1
trap - EXIT
$EXT up -d
$OWN stop db

step "Comprobando"
ok=0
for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
  if $EXT exec -T -e P="$PW" -e U="$DBUSER" -e D="$DB" app php -r '
      $m = @new mysqli("db-compartida", getenv("U"), getenv("P"), getenv("D"), 3306);
      if ($m->connect_errno) exit(1);
      exit(@file_get_contents("http://127.0.0.1/login") === false ? 1 : 0);' 2>/dev/null; then ok=1; break; fi
  sleep 5
done
[ "$ok" = "1" ] || die "la app no responde con la base compartida. Revise: $EXT logs app — o vuelva atrás: ./migrar-a-db-compartida.sh --revertir"

echo
echo "Listo: Agenda Escolar usa la base compartida (db-compartida)."
echo "  Respaldos: $BK/$DB-antes-migracion-$STAMP.sql y $BK/agenda-escolar-env-$STAMP"
echo "  La MariaDB propia quedó DETENIDA con sus datos (para volver atrás: ./migrar-a-db-compartida.sh --revertir)."
echo "  Tras una semana sin problemas puede borrarla: $OWN rm -f db && docker volume rm <proyecto>_db_data"
