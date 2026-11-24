#!/bin/sh
# Instala Agenda Escolar en el VPS desde cero, con la MariaDB compartida y el Caddy que ya existe.
# Uso (como root, dentro de la carpeta docker/ del proyecto clonado, p. ej. /docker/agenda-escolar/docker):
#   ./instalar.sh
# Se puede volver a correr: lo que ya existe (base compartida, docker/.env, base del proyecto) se reutiliza.
set -eu
cd "$(dirname "$0")"
HERE=$(pwd)
SHARED_DIR="${SHARED_DIR:-/docker/db-compartida}"
DOMAIN="agendaescolar.co"

die()  { echo; echo "ERROR: $*" >&2; exit 1; }
step() { echo; echo "==> $*"; }
ask()  { printf "%s [s/N] " "$1"; read -r r; [ "$r" = "s" ] || [ "$r" = "S" ]; }

[ -f docker-compose.yml ] && [ -f Dockerfile ] && [ -d ../app ] || die "córralo dentro de la carpeta docker/ del proyecto"
command -v docker >/dev/null || die "Docker no está instalado"
if docker compose version >/dev/null 2>&1; then DC="docker compose"; else DC="docker-compose"; fi

# ---------------------------------------------------------------- 1. Caddy existente
step "Buscando el Caddy del VPS"
CADDY="${CADDY:-$(docker ps --format '{{.Names}}' | grep -i caddy | head -n1)}"
[ -n "$CADDY" ] || die "no encuentro un contenedor de Caddy corriendo (indíquelo con: CADDY=nombre ./instalar.sh)"
PROXY_NETWORK=$(docker inspect "$CADDY" -f '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' | grep -v '^$' | grep -v '^db-compartida$' | head -n1)
[ -n "$PROXY_NETWORK" ] || die "no pude leer la red de $CADDY"
echo "Caddy: $CADDY · red: $PROXY_NETWORK"

# ---------------------------------------------------------------- 2. MariaDB compartida
step "Base de datos compartida ($SHARED_DIR)"
mkdir -p "$SHARED_DIR"
for f in docker-compose.yml crear-base.sh .env.example; do
  [ -f "$SHARED_DIR/$f" ] || cp "db-compartida/$f" "$SHARED_DIR/$f"
done
chmod +x "$SHARED_DIR/crear-base.sh"
if [ ! -f "$SHARED_DIR/.env" ]; then
  echo "DB_ROOT_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=\n')" > "$SHARED_DIR/.env"
  chmod 600 "$SHARED_DIR/.env"
  echo "Clave de root generada en $SHARED_DIR/.env (los proyectos no la usan)."
fi
(cd "$SHARED_DIR" && $DC up -d)
printf "Esperando a que la base esté lista"
i=0
until [ "$(cd "$SHARED_DIR" && docker inspect -f '{{.State.Health.Status}}' "$($DC ps -q mariadb)" 2>/dev/null)" = "healthy" ]; do
  i=$((i + 1)); [ "$i" -le 40 ] || die "la base compartida no quedó lista: cd $SHARED_DIR && $DC logs mariadb"
  printf "."; sleep 3
done
echo " lista."

# ---------------------------------------------------------------- 3. docker/.env del proyecto
step "Configuración (docker/.env)"
if [ ! -f .env ]; then
  cp .env.example .env
  echo "Creado docker/.env"
fi
sed -i "s|^PROXY_NETWORK=.*|PROXY_NETWORK=$PROXY_NETWORK|" .env
chmod 600 .env
DB_NAME=$(sed -n 's/^DB_NAME=//p' .env | head -n1); DB_NAME=${DB_NAME:-educai}
grep -q '^COMPOSE_FILE=' .env && die "docker/.env tiene COMPOSE_FILE (modo MariaDB propia); quítelo para usar la base compartida"

# ---------------------------------------------------------------- 4. Base del proyecto
step "Base «$DB_NAME» en la base compartida"
CID=$(cd "$SHARED_DIR" && $DC ps -q mariadb)
EXISTS=$(docker exec -e U="$DB_NAME" "$CID" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N -e "SELECT COUNT(*) FROM mysql.user WHERE user=\"$U\""')
TABLES=$(docker exec -e D="$DB_NAME" "$CID" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"$D\""')
if [ "$EXISTS" = "0" ]; then
  "$SHARED_DIR/crear-base.sh" "$DB_NAME"
elif [ "$TABLES" = "0" ]; then
  echo "El usuario «$DB_NAME» ya existe y su base está vacía (de un intento anterior)."
  if ask "¿Generarle una clave nueva para usarla en el instalador?"; then
    "$SHARED_DIR/crear-base.sh" "$DB_NAME" --nueva-clave
  else
    echo "Se mantiene la clave que ya tenía."
  fi
else
  echo "La base «$DB_NAME» ya tiene $TABLES tablas: se deja como está (no se borra nada)."
fi

# ---------------------------------------------------------------- 5. App
step "Construyendo y levantando la app (la primera vez tarda varios minutos)"
$DC up -d --build
printf "Esperando a la app"
i=0
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$($DC ps -q app)" 2>/dev/null)" = "healthy" ]; do
  i=$((i + 1)); [ "$i" -le 40 ] || die "la app no quedó lista: $DC logs app"
  printf "."; sleep 3
done
echo " lista."

step "Comprobando que Caddy llega a la app"
docker exec "$CADDY" wget -qO- http://agenda-escolar-app/robots.txt >/dev/null 2>&1 \
  && echo "OK: $CADDY → agenda-escolar-app" \
  || die "Caddy no alcanza la app: revise que PROXY_NETWORK ($PROXY_NETWORK) sea la red del Caddy"

# ---------------------------------------------------------------- 6. Caddyfile
step "Sitio $DOMAIN en el Caddyfile"
CADDYFILE=$(docker inspect "$CADDY" -f '{{range .Mounts}}{{if eq .Destination "/etc/caddy/Caddyfile"}}{{.Source}}{{end}}{{end}}')
if [ -z "$CADDYFILE" ] || [ ! -f "$CADDYFILE" ]; then
  echo "No encuentro el Caddyfile de $CADDY en el VPS. Agregue a mano el contenido de $HERE/caddy/agendaescolar.caddy y recargue Caddy."
elif grep -q "$DOMAIN" "$CADDYFILE"; then
  echo "Ya está en $CADDYFILE."
elif ask "¿Agrego el bloque de $DOMAIN al final de $CADDYFILE y recargo Caddy (sin cortar los otros sitios)?"; then
  cp "$CADDYFILE" "$CADDYFILE.bak-$(date +%Y%m%d-%H%M%S)"
  { echo; cat caddy/agendaescolar.caddy; } >> "$CADDYFILE"
  if docker exec "$CADDY" caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null 2>&1; then
    docker exec "$CADDY" caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
    echo "Caddy recargado."
  else
    cp "$(ls -t "$CADDYFILE".bak-* | head -n1)" "$CADDYFILE"
    die "el Caddyfile no validó con el bloque nuevo; lo dejé como estaba. Revíselo a mano."
  fi
else
  echo "Agréguelo después: contenido de $HERE/caddy/agendaescolar.caddy al final de $CADDYFILE, y recargue Caddy."
fi

# ---------------------------------------------------------------- Final
cat <<EOF

Listo. Abra https://$DOMAIN/install.php y complete:
  Servidor:    db-compartida   (ya viene propuesto)
  Puerto:      3306
  Base:        $DB_NAME
  Usuario:     $DB_NAME
  Contraseña:  la que mostró crear-base.sh arriba
  URL:         https://$DOMAIN/   (ya viene propuesta)

Al terminar el instalador, reinicie la app para retirar install.php:
  cd $HERE && $DC restart app
EOF
