<?php

namespace App\Commands;

use App\Database\Seeds\SchoolDemoSeeder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Restaura el colegio de demostración («Colegio Horizonte»): borra todo lo que hicieron los visitantes (páginas,
 * mensajes, archivos, cuentas cambiadas…) y lo vuelve a cargar, conservando su consumo de IA del mes.
 * En cPanel, como tarea programada (cron) cada noche, por ejemplo a las 3:00 a. m.:
 *   0 3 * * * /usr/local/bin/php /home/USUARIO/educai/spark demo:reset > /dev/null 2>&1
 * Si el colegio no existe, lo crea. No toca ninguna otra institución ni al superadministrador.
 */
class DemoReset extends BaseCommand
{
    protected $group       = 'Agenda Escolar';
    protected $name        = 'demo:reset';
    protected $description = 'Restaura el colegio de demostración a su estado inicial (para cron diario).';

    public function run(array $params)
    {
        $db       = db_connect();
        $existing = $db->table('institutions')->where('name', SchoolDemoSeeder::NAME)->where('deleted_at', null)->get()->getRowArray();
        $seeder   = new SchoolDemoSeeder(config('Database'));
        $t0       = microtime(true);
        $seeder->load($existing ? (int) $existing['id'] : null);
        CLI::write(sprintf('Listo en %.1f s.', microtime(true) - $t0), 'green');
    }
}
