<?php

namespace App\Libraries;

/**
 * Auditoría general (documento maestro 10). Solo inserta: la aplicación nunca edita ni borra el registro.
 * Úsela con service('audit')->log('accion.clave', [detalle], $institutionId).
 */
class Audit
{
    public function log(string $action, array $detail = [], ?int $institutionId = null): void
    {
        db_connect()->table('audit_logs')->insert([
            'institution_id' => $institutionId ?? service('tenant')->institutionId(),
            'user_id'        => service('auth')->id(),
            'action'         => $action,
            'detail'         => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'     => service('request')->getIPAddress(),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
