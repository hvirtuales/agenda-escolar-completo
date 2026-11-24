<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cupos de IA de los planes tras pasar a Claude Haiku 5.5 (unas 40 veces más barato que Opus 5.5).
 *
 * Los cupos originales se pensaron para el costo de Opus y alcanzaban para menos de una consulta por usuario al mes.
 * Los nuevos dejan el costo de IA cerca del 4 % del precio del plan (≈3.000 tokens por consulta, medido en uso real).
 * Solo cambia los planes que conservan el cupo original (no pisa un plan editado a mano en la consola).
 * Además, todos los planes incluyen el nivel avanzado: con un solo modelo solo cambia el esfuerzo, y la diferencia de
 * costo es insignificante (la casilla se quitó de la consola y el Gateway ya no lo usa).
 */
class RaisePlanQuotas extends Migration
{
    private const QUOTAS = [
        // plan => [cupo original, cupo nuevo]
        'Prueba'        => [200000, 2000000],
        'Básico'        => [600000, 30000000],
        'Institucional' => [2000000, 100000000],
    ];

    public function up()
    {
        $now = date('Y-m-d H:i:s');
        foreach (self::QUOTAS as $name => [$old, $new]) {
            $this->db->table('plans')->where('name', $name)->where('monthly_tokens', $old)->update(['monthly_tokens' => $new, 'updated_at' => $now]);
        }
        $this->db->table('plans')->update(['advanced_model' => 1]);
    }

    public function down()
    {
        foreach (self::QUOTAS as $name => [$old, $new]) {
            $this->db->table('plans')->where('name', $name)->where('monthly_tokens', $new)->update(['monthly_tokens' => $old]);
        }
    }
}
