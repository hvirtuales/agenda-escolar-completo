# Agenda Escolar con Docker

Levanta la plataforma con dos contenedores: **app** (PHP 8.3 + Apache, raíz web en `public/`) y **db** (MariaDB 11.4).
Sirve para un VPS o servidor propio, o para probar en un equipo con Docker Desktop. En cPanel no se usa Docker: allí va el ZIP o el repositorio completo.

## Primera instalación
```bash
cd docker
cp .env.example .env          # cambie DB_PASSWORD y DB_ROOT_PASSWORD
docker compose up -d --build
```
Abra `http://localhost:8080/install.php` (o el puerto de `APP_PORT`) y complete el instalador:

| Campo | Valor |
|---|---|
| Servidor | `db` |
| Puerto | `3306` |
| Base de datos, usuario, contraseña | los de `docker/.env` |
| URL de la plataforma | la que verán los usuarios, p. ej. `https://agendaescolar.co/` |

El instalador crea el `.env`, la base de datos y el superadministrador. Al reiniciar el contenedor, `install.php` deja de publicarse.

## Dónde quedan los datos
- Volumen `app_data` → `writable/`: el `.env` de la aplicación (clave de cifrado incluida), sesiones, registros y archivos de los colegios.
- Volumen `db_data` → la base de datos.

Recrear o actualizar los contenedores no borra nada. **No use `docker compose down -v`**: eso elimina los volúmenes.

## Actualizar
```bash
git pull
cd docker && docker compose up -d --build
```
Luego, como superadministrador: **Consola › Configuración › Base de datos › Actualizar base de datos**.
(O ponga `AUTO_MIGRATE=1` en `docker/.env` para aplicar las migraciones al arrancar.)

## Tareas útiles
```bash
docker compose exec -u www-data app php spark demo:reset          # restaurar el colegio de demostración
docker compose exec -u www-data app php spark migrate --all
docker compose logs -f app
docker compose exec db mariadb-dump -u root -p educai > respaldo.sql   # respaldo
```

## HTTPS
Ponga delante un proxy con certificado (Nginx, Caddy, Traefik o Cloudflare) que reenvíe a `http://localhost:8080`
y envíe `X-Forwarded-Proto: https`. Use la URL con `https://` en el instalador.
