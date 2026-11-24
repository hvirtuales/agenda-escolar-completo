<?php

namespace App\Libraries;

/**
 * Contacto comercial del botón flotante de las páginas públicas (inicio y login): WhatsApp y correo.
 * Lo edita el superadministrador en Consola › Configuración (platform_settings: contact.whatsapp, contact.email); si no
 * hay nada guardado, usa agendaescolar.contactWhatsapp / agendaescolar.contactEmail del .env. Sin datos, no se muestra.
 */
class Contact
{
    /** ['whatsapp' => solo dígitos con indicativo (573001234567) | '', 'email' => correo | ''] */
    public function get(): array
    {
        $rows = array_column(db_connect()->table('platform_settings')->like('setting', 'contact.', 'after')->get()->getResultArray(), 'value', 'setting');
        $cfg  = config('AgendaEscolar');

        return [
            'whatsapp' => preg_replace('/\D/', '', (string) ($rows['contact.whatsapp'] ?? $cfg->contactWhatsapp)),
            'email'    => filter_var((string) ($rows['contact.email'] ?? $cfg->contactEmail), FILTER_VALIDATE_EMAIL) ?: '',
        ];
    }

    /** Guarda el contacto. Devuelve un error o null. */
    public function save(string $whatsapp, string $email): ?string
    {
        $wa = preg_replace('/\D/', '', $whatsapp);
        if ($wa !== '' && (strlen($wa) < 10 || strlen($wa) > 15)) {
            return t('App.contactBadWhatsapp');
        }
        $email = trim($email);
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return t('App.contactBadEmail');
        }
        $db = db_connect();
        foreach (['contact.whatsapp' => $wa, 'contact.email' => $email] as $k => $v) {
            $row = ['value' => $v, 'updated_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')];
            $q   = $db->table('platform_settings')->where('setting', $k);
            $q->countAllResults(false) ? $q->update($row) : $db->table('platform_settings')->insert($row + ['setting' => $k]);
        }

        return null;
    }
}
