<?php

declare(strict_types=1);

/**
 * Instalador web de Agenda Escolar (CodeIgniter 4) para hosting compartido con cPanel.
 *
 * Qué hace:
 *   1. Revisa requisitos: PHP 8.2+, extensiones, carpeta vendor/ y permisos de writable/.
 *   2. Prueba la conexión a MySQL con los datos del formulario.
 *   3. Escribe el .env de producción (clave de cifrado nueva; respalda el .env anterior si existe).
 *   4. Ejecuta las migraciones, crea las plantillas de rol y el superadministrador (y, si se pide, los datos de demostración).
 *   5. Ajusta index.php y el .htaccess según dónde quedó el proyecto, y deja writable/installed.lock.
 *
 * Distribuciones de carpetas soportadas (el instalador detecta cuál es):
 *   A) Recomendada: proyecto en ~/educai y la raíz del dominio (Document Root) apuntando a ~/educai/public.
 *   B) Proyecto en ~/educai y el contenido de public/ copiado dentro de ~/public_html: se reescribe index.php.
 *   C) Todo el proyecto dentro de ~/public_html: se crea public_html/.htaccess que envía todo a public/.
 *
 * Al terminar, elimine este archivo (el instalador ofrece un botón para hacerlo). Con writable/installed.lock presente
 * el instalador no vuelve a ejecutarse.
 */

const MIN_PHP = '8.2';
const REQUIRED_EXT = ['intl', 'mbstring', 'mysqli', 'json', 'curl', 'fileinfo', 'openssl'];

error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(300);
session_start();

/* ---------------------------------------------------------------- Utilidades */

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function isProjectRoot(string $dir): bool
{
    return is_file($dir . '/app/Config/Paths.php') && is_file($dir . '/spark');
}

/** Busca la raíz del proyecto: la carpeta superior (A y C) o una carpeta hermana de public_html (B). */
function findRoot(): ?string
{
    $parent = dirname(__DIR__);
    if (isProjectRoot($parent)) {
        return realpath($parent) ?: $parent;
    }
    foreach (glob($parent . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (isProjectRoot($dir)) {
            return realpath($dir) ?: $dir;
        }
    }

    return null;
}

function detectLayout(string $root): string
{
    if (realpath(dirname(__DIR__)) !== $root) {
        return 'B';
    }
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    return ($docRoot !== false && $docRoot === $root) ? 'C' : 'A';
}

function suggestedBaseUrl(string $layout): string
{
    $https  = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($layout === 'C') {
        $path = preg_replace('#/public$#', '', $path);
    }

    return ($https ? 'https' : 'http') . '://' . $host . $path . '/';
}

function envValue(string $v): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $v) . "'";
}

function requirements(?string $root): array
{
    $checks   = [];
    $checks[] = ['PHP ' . MIN_PHP . ' o superior (actual: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, MIN_PHP, '>=')];
    foreach (REQUIRED_EXT as $ext) {
        $checks[] = ['Extensión ' . $ext, extension_loaded($ext)];
    }
    $checks[] = ['Proyecto encontrado' . ($root ? ' en ' . $root : ' (sube app/, vendor/, writable/ y spark junto a public/)'), $root !== null];
    if ($root) {
        $checks[] = ['Carpeta vendor/ (dependencias de Composer)', is_file($root . '/vendor/codeigniter4/framework/system/Boot.php')];
        foreach (['writable', 'writable/cache', 'writable/logs', 'writable/session', 'writable/uploads'] as $d) {
            $path = $root . '/' . $d;
            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $checks[] = ['Permiso de escritura en ' . $d . '/', is_dir($path) && is_writable($path)];
        }
        $checks[] = ['Permiso de escritura en la raíz del proyecto (para el .env)', is_writable($root) || is_writable($root . '/.env')];
    }

    return $checks;
}

/* ---------------------------------------------------------------- Pasos de instalación */

function writeEnv(string $root, array $in): void
{
    $key   = 'hex2bin:' . bin2hex(random_bytes(32));
    $https = str_starts_with($in['base_url'], 'https://');
    $lines = [
        '# Generado por install.php el ' . date('Y-m-d H:i:s'),
        'CI_ENVIRONMENT = production',
        '',
        'app.baseURL = ' . envValue($in['base_url']),
        "app.indexPage = ''",
        'app.forceGlobalSecureRequests = ' . ($https ? 'true' : 'false'),
        '',
        'database.default.hostname = ' . envValue($in['db_host']),
        'database.default.database = ' . envValue($in['db_name']),
        'database.default.username = ' . envValue($in['db_user']),
        'database.default.password = ' . envValue($in['db_pass']),
        'database.default.DBDriver = MySQLi',
        'database.default.DBPrefix =',
        'database.default.port = ' . (int) $in['db_port'],
        'database.default.charset = utf8mb4',
        'database.default.DBCollat = utf8mb4_unicode_ci',
        '',
        'encryption.key = ' . $key,
        '',
        'agendaescolar.mfaForAdmins = true', // las cuentas de demostración (users.is_demo) quedan exentas; el superadministrador no
        'agendaescolar.demoMode = ' . ($in['demo'] ? 'true' : 'false'),
    ];
    if ($in['api_key'] !== '') {
        $lines[] = '';
        $lines[] = 'ANTHROPIC_API_KEY = ' . envValue($in['api_key']);
    }

    $env = $root . '/.env';
    if (is_file($env)) {
        copy($env, $env . '.bak-' . date('Ymd-His'));
    }
    if (file_put_contents($env, implode("\n", $lines) . "\n") === false) {
        throw new RuntimeException('No se pudo escribir ' . $env . '. Revisa los permisos de la carpeta.');
    }
    @chmod($env, 0640);
}

/** Arranca CodeIgniter (ya con el .env nuevo) y prepara la base de datos. Devuelve el registro de lo hecho. */
function setupDatabase(string $root, array $in): array
{
    $log = [];
    defined('FCPATH') || define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
    defined('ENVIRONMENT') || define('ENVIRONMENT', 'production'); // bootConsole no lo define (lo hace spark)
    require $root . '/app/Config/Paths.php';
    $paths = new Config\Paths();
    require $paths->systemDirectory . '/Boot.php';

    // Arranque mínimo sin petición: bootConsole fuerza un CLIRequest, que falla bajo Apache.
    if (! class_exists('InstallerBoot', false)) {
        class InstallerBoot extends CodeIgniter\Boot
        {
            public static function bootInstaller(Config\Paths $paths): void
            {
                static::definePathConstants($paths);
                static::loadConstants();
                static::checkMissingExtensions();
                static::loadDotEnv($paths);
                static::loadEnvironmentBootstrap($paths);
                static::loadCommonFunctions();
                static::loadAutoloader();
                static::autoloadHelpers();
            }
        }
    }
    InstallerBoot::bootInstaller($paths);

    ob_start();
    try {
        service('migrations')->setNamespace('App')->latest();
        $log[] = 'Migraciones ejecutadas.';

        if ($in['demo']) {
            // Solo el colegio de demostración: nunca un superadministrador de prueba (el único es el de este formulario).
            Config\Database::seeder()->call('App\Database\Seeds\SchoolDemoSeeder');
            $log[] = 'Colegio de demostración cargado (Colegio Horizonte). Programa en cPanel › Cron la restauración diaria: php ' . $root . '/spark demo:reset';
        }

        service('rbac')->ensureBaseTemplates();
        $log[] = 'Plantillas de rol listas.';

        $db   = db_connect();
        $now  = date('Y-m-d H:i:s');
        $user = $db->table('users')->where('email', $in['admin_email'])->get()->getRowArray();
        $data = [
            'name'                 => $in['admin_name'],
            'password_hash'        => password_hash($in['admin_pass'], PASSWORD_DEFAULT),
            'is_superadmin'        => 1,
            'status'               => 'active',
            'must_change_password' => 0,
            'password_changed_at'  => $now,
            'updated_at'           => $now,
        ];
        if ($user) {
            $db->table('users')->where('id', $user['id'])->update($data);
            $log[] = 'Superadministrador actualizado: ' . $in['admin_email'];
        } else {
            $db->table('users')->insert($data + ['email' => $in['admin_email'], 'created_at' => $now]);
            $log[] = 'Superadministrador creado: ' . $in['admin_email'];
        }
    } finally {
        $out = trim((string) ob_get_clean());
        if ($out !== '') {
            $log[] = strip_tags($out);
        }
    }

    return $log;
}

/** Ajustes según la distribución de carpetas. */
function adjustLayout(string $root, string $layout): array
{
    $log = [];
    if ($layout === 'B') {
        $index = __DIR__ . '/index.php';
        $code  = (string) file_get_contents($index);
        $new   = preg_replace(
            "#require\\s+FCPATH\\s*\\.\\s*'\\.\\./app/Config/Paths\\.php';|require\\s+'[^']*/app/Config/Paths\\.php';#",
            'require ' . var_export(str_replace('\\', '/', $root) . '/app/Config/Paths.php', true) . ';',
            $code,
            1,
        );
        if ($new !== null && $new !== $code) {
            copy($index, $index . '.bak-' . date('Ymd-His'));
            file_put_contents($index, $new);
            $log[] = 'index.php apunta ahora al proyecto en ' . $root . '.';
        }
    }
    if ($layout === 'C') {
        $ht = $root . '/.htaccess';
        if (! is_file($ht)) {
            file_put_contents($ht, "# Generado por install.php: todo el tráfico va a public/ (app/, writable/ y .env quedan inaccesibles)\n"
                . "Options -Indexes\n<IfModule mod_rewrite.c>\n\tRewriteEngine On\n\tRewriteRule ^(.*)$ public/$1 [L]\n</IfModule>\n"
                . "<FilesMatch \"^\\.\">\n\tRequire all denied\n</FilesMatch>\n");
            $log[] = 'Creado .htaccess en la raíz para servir desde public/.';
        }
    }

    return $log;
}

/* ---------------------------------------------------------------- Controlador */

$root    = findRoot();
$layout  = $root ? detectLayout($root) : 'A';
$lock    = $root ? $root . '/writable/installed.lock' : null;
$checks  = requirements($root);
$ready   = ! in_array(false, array_column($checks, 1), true);
$errors  = [];
$done    = null;
$deleted = false;
$_SESSION['install_token'] ??= bin2hex(random_bytes(16));
$token   = $_SESSION['install_token'];

$in = [
    'base_url'    => trim($_POST['base_url'] ?? suggestedBaseUrl($layout)),
    'db_host'     => trim($_POST['db_host'] ?? 'localhost'),
    'db_port'     => trim($_POST['db_port'] ?? '3306'),
    'db_name'     => trim($_POST['db_name'] ?? ''),
    'db_user'     => trim($_POST['db_user'] ?? ''),
    'db_pass'     => (string) ($_POST['db_pass'] ?? ''),
    'admin_name'  => trim($_POST['admin_name'] ?? ''),
    'admin_email' => strtolower(trim($_POST['admin_email'] ?? '')),
    'admin_pass'  => (string) ($_POST['admin_pass'] ?? ''),
    'api_key'     => trim($_POST['api_key'] ?? ''),
    'demo'        => ! empty($_POST['demo']),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($token, (string) ($_POST['token'] ?? ''))) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $deleted = @unlink(__FILE__);
        if (! $deleted) {
            $errors[] = 'No se pudo eliminar install.php. Bórralo desde el Administrador de archivos de cPanel.';
        }
    } elseif ($action === 'install' && $ready && ! is_file((string) $lock)) {
        if (! preg_match('#^https?://[^/]+(/.*)?/$#', $in['base_url'])) {
            $errors[] = 'La URL base debe empezar por http:// o https:// y terminar en «/».';
        }
        foreach (['db_host' => 'servidor', 'db_name' => 'nombre de la base', 'db_user' => 'usuario de la base'] as $k => $label) {
            if ($in[$k] === '') {
                $errors[] = 'Falta el ' . $label . '.';
            }
        }
        if ($in['admin_name'] === '') {
            $errors[] = 'Falta el nombre del superadministrador.';
        }
        if (! filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo del superadministrador no es válido.';
        }
        if (strlen($in['admin_pass']) < 10) {
            $errors[] = 'La contraseña del superadministrador debe tener al menos 10 caracteres.';
        }

        if (! $errors) {
            mysqli_report(MYSQLI_REPORT_OFF);
            $conn = @mysqli_connect($in['db_host'], $in['db_user'], $in['db_pass'], $in['db_name'], (int) $in['db_port']);
            if (! $conn) {
                $errors[] = 'No se pudo conectar a MySQL: ' . mysqli_connect_error()
                    . '. En cPanel, crea la base y el usuario en «Bases de datos MySQL», asigna el usuario a la base con TODOS los privilegios y usa los nombres completos (con el prefijo de la cuenta).';
            } else {
                mysqli_close($conn);
            }
        }

        if (! $errors) {
            try {
                writeEnv($root, $in);
                $done = array_merge(['Archivo .env de producción escrito.'], setupDatabase($root, $in), adjustLayout($root, $layout));
                file_put_contents($lock, 'Instalado el ' . date('Y-m-d H:i:s') . "\n");
                $done[] = 'Instalación bloqueada (writable/installed.lock).';
            } catch (Throwable $e) {
                $errors[] = 'Error durante la instalación: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            }
        }
    }
}

$installed = $lock && is_file($lock) && $done === null;
$layoutText = [
    'A' => 'A — la raíz del dominio apunta a la carpeta public/ del proyecto (recomendada).',
    'B' => 'B — el proyecto está en una carpeta hermana y public/ se copió en public_html: se ajustará index.php.',
    'C' => 'C — todo el proyecto está dentro de la raíz del dominio: se creará un .htaccess que envía todo a public/.',
][$layout];

?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalar Agenda Escolar</title>
<style>
  :root {
    --bg: #f7f7f8; --surface: #fff; --muted: #f0f0f2; --line: #e4e4e7; --ink: #18181b; --ink-soft: #6b6b76;
    --accent: #1d4ed8; --accent-ink: #fff; --ok: #15803d; --danger: #b91c1c; --warn: #b45309;
  }
  @media (prefers-color-scheme: dark) {
    :root { --bg: #0f0f11; --surface: #18181b; --muted: #222226; --line: #2e2e33; --ink: #f4f4f5; --ink-soft: #a1a1aa;
      --accent: #3b82f6; --ok: #22c55e; --danger: #f87171; --warn: #f59e0b; }
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--ink); font: 14px/1.5 "Segoe UI Variable", "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif; }
  main { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  h2 { font-size: 15px; margin: 0; }
  p.lead { color: var(--ink-soft); margin: 0 0 24px; }
  .card { background: var(--surface); border: 1px solid var(--line); border-radius: 6px; margin-bottom: 16px; }
  .card-header { padding: 12px 16px; border-bottom: 1px solid var(--line); }
  .card-body { padding: 16px; }
  ul.checks { list-style: none; margin: 0; padding: 0; }
  ul.checks li { display: flex; gap: 8px; padding: 4px 0; }
  .ok { color: var(--ok); } .bad { color: var(--danger); }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
  .full { grid-column: 1 / -1; }
  @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } }
  label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; }
  .hint { color: var(--ink-soft); font-size: 12px; margin-top: 4px; }
  input[type=text], input[type=email], input[type=password], input[type=url], input[type=number] {
    width: 100%; height: 32px; padding: 0 10px; border: 1px solid var(--line); border-radius: 4px;
    background: var(--surface); color: var(--ink); font: inherit;
  }
  input:focus { outline: 2px solid var(--accent); outline-offset: -1px; }
  .check { display: flex; gap: 8px; align-items: flex-start; font-weight: 400; }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 32px; padding: 0 14px;
    border: 1px solid var(--line); border-radius: 4px; background: var(--surface); color: var(--ink); font: inherit;
    font-weight: 600; cursor: pointer; text-decoration: none; }
  .btn:hover { background: var(--muted); }
  .btn-primary { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); }
  .btn-primary:hover { background: var(--accent); filter: brightness(1.08); }
  .btn-danger { color: var(--danger); }
  .btn[disabled] { opacity: .5; cursor: not-allowed; }
  .actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .alert { border: 1px solid var(--line); border-left: 4px solid var(--danger); background: var(--surface); padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
  .alert.success { border-left-color: var(--ok); }
  .alert.warn { border-left-color: var(--warn); }
  .alert ul { margin: 6px 0 0; padding-left: 18px; }
  code { background: var(--muted); padding: 1px 5px; border-radius: 3px; font-size: 12px; }
</style>
</head>
<body>
<main>
  <h1>Instalar Agenda Escolar</h1>
  <p class="lead">Asistente de instalación para hosting con cPanel.</p>

<?php if ($deleted): ?>
  <div class="alert success"><strong>install.php eliminado.</strong>
    <div class="actions" style="margin-top:10px"><a class="btn btn-primary" href="./login">Ir al inicio de sesión</a></div></div>
</main></body></html>
<?php exit; endif; ?>

<?php if ($errors): ?>
  <div class="alert"><strong>No se pudo continuar</strong><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<?php if ($done !== null): ?>
  <div class="alert success"><strong>Instalación completa</strong>
    <ul><?php foreach ($done as $d): ?><li><?= nl2br(h($d)) ?></li><?php endforeach ?></ul></div>
  <div class="alert warn"><strong>Elimina este instalador.</strong> Deja de ser necesario y no debe quedar publicado.
    <form method="post" class="actions" style="margin-top:10px">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <button class="btn btn-danger" name="action" value="delete">Eliminar install.php</button>
      <a class="btn" href="<?= h($in['base_url']) ?>login">Ir al inicio de sesión</a>
    </form></div>

<?php elseif ($installed): ?>
  <div class="alert warn"><strong>Agenda Escolar ya está instalada.</strong> Para reinstalar, borra <code>writable/installed.lock</code>
    (y, si quieres empezar de cero, vacía la base de datos). Si ya no lo necesitas, elimina este archivo.
    <form method="post" class="actions" style="margin-top:10px">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <button class="btn btn-danger" name="action" value="delete">Eliminar install.php</button>
      <a class="btn" href="./login">Ir al inicio de sesión</a>
    </form></div>

<?php else: ?>
  <section class="card">
    <div class="card-header"><h2>1. Requisitos del servidor</h2></div>
    <div class="card-body">
      <ul class="checks">
        <?php foreach ($checks as [$label, $ok]): ?>
          <li><span class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✖' ?></span><span><?= h($label) ?></span></li>
        <?php endforeach ?>
      </ul>
      <?php if ($root): ?><p class="hint">Distribución detectada: <?= h($layoutText) ?></p><?php endif ?>
      <?php if (! $ready): ?>
        <p class="hint">Corrige lo marcado con ✖ (en cPanel: «Seleccionar versión de PHP» para la versión y las extensiones;
          «Administrador de archivos › Permisos» para writable/ con 755) y recarga la página.</p>
        <div class="actions"><a class="btn" href="">Volver a revisar</a></div>
      <?php endif ?>
    </div>
  </section>

  <?php if ($ready): ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="token" value="<?= h($token) ?>">

    <section class="card">
      <div class="card-header"><h2>2. Sitio</h2></div>
      <div class="card-body grid">
        <div class="full">
          <label for="base_url">URL base</label>
          <input type="url" id="base_url" name="base_url" value="<?= h($in['base_url']) ?>" required>
          <div class="hint">Con https:// si el dominio tiene certificado SSL, y terminada en «/».</div>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>3. Base de datos MySQL</h2></div>
      <div class="card-body grid">
        <div class="full hint">Créala antes en cPanel › «Bases de datos MySQL»: base, usuario y asignación con todos los privilegios.
          Los nombres llevan el prefijo de la cuenta, por ejemplo <code>micuenta_educai</code>.</div>
        <div><label for="db_host">Servidor</label><input type="text" id="db_host" name="db_host" value="<?= h($in['db_host']) ?>" required></div>
        <div><label for="db_port">Puerto</label><input type="number" id="db_port" name="db_port" value="<?= h($in['db_port']) ?>" required></div>
        <div><label for="db_name">Nombre de la base</label><input type="text" id="db_name" name="db_name" value="<?= h($in['db_name']) ?>" required></div>
        <div><label for="db_user">Usuario</label><input type="text" id="db_user" name="db_user" value="<?= h($in['db_user']) ?>" required></div>
        <div class="full"><label for="db_pass">Contraseña</label><input type="password" id="db_pass" name="db_pass" value=""></div>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>4. Superadministrador de la plataforma</h2></div>
      <div class="card-body grid">
        <div><label for="admin_name">Nombre</label><input type="text" id="admin_name" name="admin_name" value="<?= h($in['admin_name']) ?>" required></div>
        <div><label for="admin_email">Correo</label><input type="email" id="admin_email" name="admin_email" value="<?= h($in['admin_email']) ?>" required></div>
        <div class="full"><label for="admin_pass">Contraseña</label>
          <input type="password" id="admin_pass" name="admin_pass" minlength="10" required autocomplete="new-password">
          <div class="hint">Mínimo 10 caracteres.</div></div>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>5. Opcional</h2></div>
      <div class="card-body grid">
        <div class="full"><label for="api_key">Clave de la API de Anthropic (IA)</label>
          <input type="password" id="api_key" name="api_key" value="" autocomplete="off">
          <div class="hint">Sin clave, las funciones de IA quedan desactivadas en producción. Se puede agregar luego en el .env como <code>ANTHROPIC_API_KEY</code>.</div></div>
        <div class="full"><label class="check"><input type="checkbox" name="demo" value="1" <?= $in['demo'] ? 'checked' : '' ?>>
          <span>Demostración pública: carga el «Colegio Horizonte» (6.º a 11.º, todas las asignaturas) y muestra en el inicio de sesión sus cuentas de prueba
          (contraseña <code>Agenda-2026</code>), sin el superadministrador. Esas cuentas no pueden cambiar contraseñas ni cuentas, y el colegio se restaura cada noche
          con una tarea programada (<code>php spark demo:reset</code>).</span></label></div>
      </div>
    </section>

    <div class="actions">
      <button class="btn btn-primary" name="action" value="install">Instalar</button>
    </div>
  </form>
  <?php endif ?>
<?php endif ?>
</main>
</body>
</html>
