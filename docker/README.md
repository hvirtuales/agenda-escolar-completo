# Agenda Escolar con Docker

Dos contenedores: **app** (PHP 8.4 + Apache, raíz web en `public/`) y **db** (MariaDB 11.4).
Sirve para un VPS o servidor propio. En cPanel no se usa Docker: allí va el ZIP o el repositorio completo.

Dominio configurado: **https://agendaescolar.co/** (`APP_URL` en `docker/.env`).

## Cómo queda armado
```
Internet ──HTTPS──> proxy con certificado (Nginx/Caddy) ──HTTP──> 127.0.0.1:8080 (app) ──> db (red interna)
```
- La app solo escucha en `127.0.0.1`: desde fuera no se llega sin pasar por el proxy.
- La base de datos no publica ningún puerto.
- `TRUSTED_PROXIES` (por defecto la red interna de Docker) indica a CodeIgniter de qué IPs creer `X-Forwarded-For` y
  `X-Forwarded-Proto`. Sin eso la app no sabría que la conexión es HTTPS y registraría la IP del proxy.

## Primera instalación
```bash
cd docker
cp .env.example .env          # cambie DB_PASSWORD y DB_ROOT_PASSWORD
docker compose up -d --build
```
Configure el proxy para `agendaescolar.co` → `http://127.0.0.1:8080`, enviando `Host` y `X-Forwarded-Proto: https`.
Ejemplo con Caddy (obtiene el certificado solo):
```
agendaescolar.co {
    reverse_proxy 127.0.0.1:8080
}
```
Abra `https://agendaescolar.co/install.php`:

| Campo | Valor |
|---|---|
| Servidor | `db` |
| Puerto | `3306` |
| Base de datos, usuario, contraseña | los de `docker/.env` |
| URL de la plataforma | ya viene propuesta: `https://agendaescolar.co/` |

El instalador crea el `.env`, la base de datos y el superadministrador. Al reiniciar el contenedor, `install.php` deja de publicarse.

## Dónde quedan los datos
- Volumen `app_data` → `writable/`: el `.env` de la aplicación (clave de cifrado incluida), sesiones, registros y archivos de los colegios.
- Volumen `db_data` → la base de datos.

Recrear o actualizar los contenedores no borra nada. **No use `docker compose down -v`**: eso elimina los volúmenes.

## Actualizar
```bash
git pull
cd docker && docker compose build --pull && docker compose up -d
```
Luego, como superadministrador: **Consola › Configuración › Base de datos › Actualizar base de datos**
(o `AUTO_MIGRATE=1` en `docker/.env` para aplicar las migraciones al arrancar).

Las imágenes base están fijadas por versión y huella (`docker/Dockerfile`, `docker-compose.yml`). Para traer parches de
seguridad, actualice esas versiones (a mano o con Renovate/Dependabot) y reconstruya. Revise la imagen con:
```bash
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image --severity HIGH,CRITICAL --ignore-unfixed agenda-escolar:latest
```

## Seguridad aplicada
- Imagen en dos etapas: la final no lleva compiladores, paquetes `-dev`, `unzip` ni Composer; se aplican las actualizaciones de Debian.
- El código es de `root`; PHP (www-data) solo puede escribir en `writable/`.
- Contenedor sin privilegios extra (`no-new-privileges`, solo las capacidades mínimas).
- PHP: sin errores en pantalla, sin versión expuesta, sesiones en modo estricto, funciones de ejecución de comandos deshabilitadas.
- Apache: `ServerTokens Prod`, `TRACE` deshabilitado. Cookies con `Secure` automático bajo HTTPS.

## Tareas útiles
```bash
docker compose exec -u www-data app php spark demo:reset          # restaurar el colegio de demostración
docker compose exec -u www-data app php spark migrate --all
docker compose logs -f app
docker compose exec db mariadb-dump -u root -p educai > respaldo.sql   # respaldo
```
