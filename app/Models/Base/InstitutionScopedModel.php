<?php

namespace App\Models\Base;

use CodeIgniter\Model;

/**
 * Modelo base para tablas de una institución (columna institution_id). Documento maestro 7.1.
 *
 * Limita automáticamente lecturas, conteos, actualizaciones y borrados a la institución activa, y asigna
 * institution_id al insertar. Así ningún módulo puede leer datos de otra institución por olvido.
 * No use $model->builder() directamente en estos modelos: se saltaría el filtro.
 */
abstract class InstitutionScopedModel extends Model
{
    protected $returnType = 'array';

    protected $beforeInsert = ['scopeSetInstitution'];
    protected $beforeFind   = ['scopeToInstitution'];
    protected $beforeUpdate = ['scopeToInstitution'];
    protected $beforeDelete = ['scopeToInstitution'];

    /** Institución forzada (consola de plataforma, seeders, comandos y trabajos en segundo plano). */
    protected ?int $forcedInstitutionId = null;

    public function forInstitution(int $institutionId): static
    {
        $this->forcedInstitutionId = $institutionId;

        return $this;
    }

    protected function currentInstitutionId(): int
    {
        $id = $this->forcedInstitutionId ?? service('tenant')->institutionId();
        if (! $id) {
            throw new \RuntimeException('No hay una institución activa para consultar ' . $this->table . '.');
        }

        return $id;
    }

    protected function scopeToInstitution(array $data): array
    {
        $this->where($this->table . '.institution_id', $this->currentInstitutionId());

        return $data;
    }

    protected function scopeSetInstitution(array $data): array
    {
        $data['data']['institution_id'] = $this->currentInstitutionId();

        return $data;
    }

    public function countAllResults(bool $reset = true, bool $test = false)
    {
        $this->where($this->table . '.institution_id', $this->currentInstitutionId());

        return parent::countAllResults($reset, $test);
    }
}
