# Agenda Escolar con Docker (VPS de Hostinger)

La app (PHP 8.4 + Apache, raíz web en `public/`) usa la **MariaDB compartida** del VPS y el **Caddy que ya existe**
(proyecto ai-specialists). Dominio: **https://agendaescolar.co/**.

```
Internet ──443──> Caddy existente ──red del Caddy──> agenda-escolar-app ──red db-compartida──> db-compartida (MariaDB)
                                                                                                 └── otras bases de otros proyectos
```
- Ni la app ni la base publican puertos: solo se llega por Caddy, y a la base solo desde la red `db-compartida`.
- Cada proyecto tiene su base y su usuario en la MariaDB compartida (`db-compartida/crear-base.sh`).
- `TRUSTED_PROXIES` indica a CodeIgniter de qué IPs creer `X-Forwarded-For`/`X-Forwarded-Proto` (los envía Caddy).

| Carpeta en el VPS | Qué es |
|---|---|
| `/docker/agenda-escolar` | clon de este repositorio (se actualiza con `git pull`) |
| `/docker/agenda-escolar/docker/.env` | configuración de Docker del proyecto (no está en git) |
| `/docker/db-compartida` | la MariaDB compartida (la crea `instalar.sh`) |

## Instalar desde cero
1. **DNS**: registros A de `agendaescolar.co` y `www` → IP del VPS (y ningún AAAA apuntando a otro servidor).
2. **Llave de solo lectura para GitHub** (el repositorio es privado):
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/agenda_escolar -N "" -C "vps-agenda-escolar"
   cat ~/.ssh/agenda_escolar.pub
   ```
   GitHub › `hvirtuales/agenda-escolar` › Settings › Deploy keys › Add deploy key (sin «Allow write access»).
3. **Clonar e instalar**:
   ```bash
   GIT_SSH_COMMAND="ssh -i ~/.ssh/agenda_escolar -o IdentitiesOnly=yes" git clone git@github.com:hvirtuales/agenda-escolar.git /docker/agenda-escolar
   cd /docker/agenda-escolar
   git config core.sshCommand "ssh -i ~/.ssh/agenda_escolar -o IdentitiesOnly=yes"
   cd docker && sh instalar.sh
   ```
   `instalar.sh` detecta el Caddy y su red, monta la base compartida en `/docker/db-compartida`, crea la base `educai`
   (**muestra su contraseña una sola vez: guárdela**), construye y levanta la app, comprueba que Caddy la alcanza y
   ofrece agregar el sitio al Caddyfile (con copia de respaldo y validación).
4. **Instalador web**: `https://agendaescolar.co/install.php` — servidor `db-compartida`, base y usuario `educai`,
   la contraseña del paso 3. Crea el `.env` de la app y el superadministrador.
5. Retirar el instalador: `cd /docker/agenda-escolar/docker && docker compose restart app`.

## Actualizar
```bash
cd /docker/agenda-escolar && git pull
cd docker && docker compose up -d --build
```
Luego, como superadministrador: **Consola › Configuración › Base de datos › Actualizar base de datos**
(o `AUTO_MIGRATE=1` en `docker/.env`).

## Dónde quedan los datos
- Volumen `agenda-escolar_app_data` → `writable/`: el `.env` de la app (clave de cifrado incluida), sesiones, registros y archivos de los colegios.
- Base `educai` dentro de la MariaDB compartida (volumen `db-compartida_datos`).

Recrear o actualizar contenedores no borra nada. **No use `docker compose down -v`**: elimina los volúmenes.

## Respaldos
```bash
mkdir -p /root/respaldos
cd /docker/db-compartida && docker compose exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb-dump -uroot --single-transaction educai' > /root/respaldos/educai-$(date +%F).sql
docker run --rm -v agenda-escolar_app_data:/w -v /root/respaldos:/r alpine tar czf /r/agenda-escolar-archivos-$(date +%F).tgz -C /w .
```

## Base compartida para otros proyectos
```bash
cd /docker/db-compartida && ./crear-base.sh nombre_proyecto
```
El otro proyecto se une a la red externa `db-compartida` y se conecta a `db-compartida:3306` con su usuario.

## Alternativa: MariaDB propia
En un servidor sin la base compartida: en `docker/.env` ponga `COMPOSE_FILE=docker-compose.db-propia.yml`,
`DB_PASSWORD` y `DB_ROOT_PASSWORD`, y `docker compose up -d --build`. En el instalador: servidor `agenda-escolar-db`.

## Seguridad aplicada
- Imagen en dos etapas, versiones fijadas con huella; la final no lleva compiladores, paquetes `-dev`, `unzip` ni Composer.
- El código es de `root`; PHP (www-data) solo escribe en `writable/`. Contenedor con `no-new-privileges` y capacidades mínimas.
- PHP sin errores en pantalla ni versión expuesta, sesiones estrictas, sin funciones de ejecución de comandos.
- Apache con `ServerTokens Prod` y `TRACE` deshabilitado. Cookies `Secure` automáticas bajo HTTPS.
- Revisar la imagen: `docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --ignore-unfixed agenda-escolar:latest`

## Tareas útiles
```bash
docker compose exec -u www-data app php spark migrate --all
docker compose logs -f app
```
