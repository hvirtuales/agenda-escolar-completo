<?php

namespace App\Libraries;

/**
 * Actualizar la base de datos desde la consola (para hosting sin terminal, como cPanel sin SSH).
 *
 * Ejecuta las migraciones pendientes del proyecto (namespace App), igual que «php spark migrate». Solo la usa el
 * superadministrador, con su contraseña, y queda en la auditoría (ConsoleController::migrate).
 * - Un candado de archivo (writable/migrate.lock) impide dos ejecuciones a la vez (doble clic, dos pestañas).
 * - Las migraciones solo agregan o cambian estructura; no hay botón para revertirlas desde la web.
 */
class DbUpdater
{
    private function runner(): \CodeIgniter\Database\MigrationRunner
    {
        return \Config\Services::migrations(null, null, false)->setNamespace('App'); // instancia propia
    }

    /** Migraciones del proyecto que aún no se aplicaron: [['version', 'name']]. */
    public function pending(): array
    {
        $r = $this->runner();
        $r->ensureTable();
        $all = $r->findMigrations();
        foreach ($r->getHistory() as $h) {
            unset($all[$r->getObjectUid($h)]);
        }

        return array_values(array_map(static fn ($m) => ['version' => $m->version, 'name' => self::label($m->name)], $all));
    }

    /** Última migración aplicada: ['name', 'at'] o null. */
    public function last(): ?array
    {
        $h = $this->runner()->getHistory();
        if (! $h) {
            return null;
        }
        $l = end($h);

        return ['name' => self::label(substr(strrchr('\\' . $l->class, '\\'), 1)), 'at' => date('Y-m-d H:i', (int) $l->time)];
    }

    /**
     * Aplica las pendientes. Devuelve ['ok' => bool, 'applied' => [nombres], 'error' => ?string].
     * El detalle técnico de un error va al registro (writable/logs), no a la pantalla.
     */
    public function run(): array
    {
        $before = $this->pending();
        if (! $before) {
            return ['ok' => true, 'applied' => [], 'error' => null];
        }
        $fh = @fopen(WRITEPATH . 'migrate.lock', 'c');
        if (! $fh || ! flock($fh, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'applied' => [], 'error' => 'Ya hay una actualización en curso. Espere un momento y recargue.'];
        }
        @set_time_limit(300);
        try {
            $this->runner()->latest();
            $after   = array_column($this->pending(), 'version');
            $applied = array_column(array_filter($before, static fn ($m) => ! in_array($m['version'], $after, true)), 'name');

            return ['ok' => $after === [], 'applied' => $applied, 'error' => $after === [] ? null : 'Quedaron migraciones sin aplicar. Revise el registro del servidor.'];
        } catch (\Throwable $e) {
            log_message('error', 'Actualizar base de datos: ' . $e->getMessage());

            return ['ok' => false, 'applied' => [], 'error' => 'La actualización falló: ' . mb_substr($e->getMessage(), 0, 200)];
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** «CreateImpersonations» → «Create impersonations». */
    private static function label(string $class): string
    {
        return ucfirst(strtolower(trim(preg_replace('/(?<!^)([A-Z])/', ' $1', $class))));
    }
}
