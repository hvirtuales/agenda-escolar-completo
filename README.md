# Agenda Escolar — paquete completo para cPanel

Copia lista para instalar: incluye `vendor/` (CodeIgniter y librerías) y la estructura vacía de `writable/`.
**No incluye** `.env` (se crea en el servidor con el instalador) ni archivos subidos por los colegios.

El código de trabajo vive en el repositorio `agenda-escolar`; este repositorio se regenera desde el paquete de despliegue.

## Instalación nueva
1. Descargue el repositorio (Code › Download ZIP) y súbalo a cPanel **fuera de `public_html`**, por ejemplo `/home/usuario/educai`.
2. Apunte el dominio o subdominio a la carpeta `public/`.
3. Dé permisos de escritura a `writable/` y sus subcarpetas.
4. Abra `https://su-dominio/install.php`: pide los datos de la base de datos, crea el `.env`, aplica las migraciones y crea el superadministrador.

## Actualizar una instalación existente
1. Reemplace los archivos **sin tocar** `.env` ni `writable/`.
2. Entre como superadministrador y pulse **Consola › Configuración › Base de datos › Actualizar base de datos**.

## Requisitos
PHP 8.2 o superior con las extensiones intl, mbstring, zip, gd, mysqli y curl; MySQL o MariaDB.
