<?php

namespace App\Libraries;

/**
 * Asistente flotante de la interfaz: responde preguntas abiertas sobre cómo usar la plataforma, según el rol de quien
 * pregunta y la pantalla en la que está. Úsela con service('helpAssistant').
 *
 * - Con IA real y una institución activa: usa el modelo (función «helpdesk») con los artículos pertinentes de la Ayuda
 *   como única fuente.
 * - Sin IA real (simulada o sin configurar), sin institución (consola) o si la IA falla: responde con el artículo más
 *   pertinente de la Ayuda (resumen y pasos), sin consumir cupo.
 * - La base de conocimiento es la Ayuda (Config\HelpCenter, service('help')): una sola fuente para ambas.
 * - Nombre, imagen (PNG, JPG, WebP o GIF animado) y si está activo los define el superadministrador (platform_settings).
 */
class HelpAssistant
{
    private const DIR      = WRITEPATH . 'uploads/assistant/';
    private const MAX_SIZE = 2 * 1024 * 1024;
    private const TYPES    = ['image/gif' => 'gif', 'image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'];

    private function db()
    {
        return db_connect();
    }

    /* ---------- Ajustes ---------- */

    public function settings(): array
    {
        $rows = array_column($this->db()->table('platform_settings')->like('setting', 'assistant.', 'after')->get()->getResultArray(), 'value', 'setting');

        return [
            'enabled' => ($rows['assistant.enabled'] ?? '1') === '1',
            'name'    => ($rows['assistant.name'] ?? '') !== '' ? $rows['assistant.name'] : 'Asistente',
            'avatar'  => ($rows['assistant.avatar'] ?? '') ?: null,
        ];
    }

    private function set(string $key, ?string $value): void
    {
        $row = ['value' => $value, 'updated_by' => service('auth')->id(), 'updated_at' => date('Y-m-d H:i:s')];
        $q   = $this->db()->table('platform_settings')->where('setting', $key);
        $q->countAllResults(false) ? $q->update($row) : $this->db()->table('platform_settings')->insert($row + ['setting' => $key]);
    }

    public function save(bool $enabled, string $name): void
    {
        $this->set('assistant.enabled', $enabled ? '1' : '0');
        $this->set('assistant.name', mb_substr(trim($name), 0, 40));
    }

    /** Guarda la imagen tal cual (un GIF conserva su animación). Devuelve null o el mensaje de error. */
    public function setAvatar(\CodeIgniter\HTTP\Files\UploadedFile $file): ?string
    {
        if (! $file->isValid() || $file->hasMoved()) {
            return 'Elige una imagen.';
        }
        $mime = $file->getMimeType();
        if (! isset(self::TYPES[$mime]) || $file->getSize() > self::MAX_SIZE || @getimagesize($file->getTempName()) === false) {
            return 'Usa una imagen GIF, PNG, WebP o JPG de hasta 2 MB.';
        }
        if (! is_dir(self::DIR)) {
            mkdir(self::DIR, 0775, true);
        }
        $name = 'asistente-' . bin2hex(random_bytes(6)) . '.' . self::TYPES[$mime];
        $old  = $this->settings()['avatar'];
        $file->move(self::DIR, $name);
        $this->set('assistant.avatar', $name);
        $this->removeFile($old);

        return null;
    }

    public function clearAvatar(): void
    {
        $this->removeFile($this->settings()['avatar']);
        $this->set('assistant.avatar', null);
    }

    private function removeFile(?string $name): void
    {
        if ($name && basename($name) === $name && is_file(self::DIR . $name)) {
            @unlink(self::DIR . $name);
        }
    }

    /** Ruta y tipo de la imagen personalizada, o null (se usa el logo). */
    public function avatarFile(): ?array
    {
        $name = $this->settings()['avatar'];
        if (! $name || basename($name) !== $name || ! is_file(self::DIR . $name)) {
            return null;
        }

        return ['path' => self::DIR . $name, 'mime' => array_search(pathinfo($name, PATHINFO_EXTENSION), self::TYPES, true) ?: 'image/png', 'version' => substr(md5($name), 0, 8)];
    }

    /* ---------- Respuestas ---------- */

    /** Rol legible de quien pregunta. */
    private function roleLabel(): string
    {
        $t = service('tenant');

        return match (true) {
            service('auth')->isSuperadmin() && ! $t->institution() => 'superadministrador en la consola de plataforma',
            $t->isStudent()                                         => 'estudiante',
            $t->isGuardian()                                        => 'acudiente (familia)',
            $t->can('tenant.users.manage')                          => 'administrador o coordinador de la institución',
            $t->can('content.edit')                                 => 'docente',
            default                                                 => 'persona de la institución',
        };
    }

    /** Enlaces de un artículo: «Ir a…» (si tiene) y «Ver en Ayuda». */
    private static function links(array $art): array
    {
        return ['link' => $art['link'] ?? null, 'article' => ['Ver en Ayuda: ' . $art['title'], 'ayuda/' . $art['slug']]];
    }

    /**
     * Respuesta desde la Ayuda (sin IA): el resumen y los pasos del artículo más pertinente. Si nada coincide lo
     * suficiente, sugiere temas o abrir la Ayuda.
     */
    public function guideAnswer(string $message): array
    {
        $hits = service('help')->search($message, 3);
        if (! $hits || $hits[0][1] < 3) {
            $sugg = array_map(static fn ($h) => '«' . $h[2]['title'] . '»', array_slice($hits, 0, 3));

            return ['reply' => 'No encontré una respuesta exacta en la Ayuda.' . ($sugg ? ' Quizás te sirva: ' . implode(', ', $sugg) . '.' : ' Prueba con otras palabras (por ejemplo: «cómo creo un reto», «dónde veo mi agenda», «cambiar mi contraseña»).'),
                'link' => null, 'article' => ['Abrir la Ayuda', 'ayuda'], 'source' => 'guide'];
        }
        $art   = $hits[0][2];
        $steps = array_slice($art['steps'] ?? [], 0, 5);
        $reply = $art['summary'] . ($steps ? "\n\n" . implode("\n", array_map(static fn ($st, $i) => ($i + 1) . '. ' . $st, $steps, array_keys($steps))) : '');

        return ['reply' => $reply, 'source' => 'guide'] + self::links($art);
    }

    /**
     * Responde una pregunta. $history: [['role' => 'user'|'assistant', 'text' => …], …] (últimos turnos, del navegador).
     * Devuelve [reply, link|null, article|null, source: ai|guide, simulated].
     */
    public function answer(string $message, array $history, string $page, string $title): array
    {
        $message = mb_substr(trim($message), 0, 600);
        if ($message === '') {
            return ['error' => 'Escribe tu pregunta.'];
        }
        $ai  = service('ai');
        $iid = (int) service('tenant')->institutionId();
        if (! $iid || ! $ai->available() || $ai->simulated()) {
            return $this->guideAnswer($message) + ['simulated' => false];
        }
        // Contexto: los artículos más pertinentes completos y los títulos del resto (la Ayuda es la única fuente).
        $help  = service('help');
        $hits  = $help->search($message, 4);
        $guide = implode("\n\n", array_map(static fn ($h) => '[' . $h[0] . '] ' . HelpCenter::text($h[2]), $hits))
            . "\n\nOtros artículos de la Ayuda: " . implode('; ', array_map(static fn ($a) => $a['title'], $help->articles()));
        $msgs = [];
        foreach (array_slice($history, -6) as $h) {
            if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && is_string($h['text'] ?? null)) {
                $msgs[] = ['role' => $h['role'], 'content' => mb_substr($h['text'], 0, 1200)];
            }
        }
        $msgs[] = ['role' => 'user', 'content' => $message];
        $r      = $ai->run('helpdesk', \App\Libraries\Ai\Prompts::helpdesk([
            'app' => config('AgendaEscolar')->appName, 'role' => $this->roleLabel(), 'page' => mb_substr($title . ' (' . $page . ')', 0, 200), 'guide' => $guide,
        ]), $msgs, ['prompt_version' => \App\Libraries\Ai\Prompts::HELPDESK_VERSION]);
        if (isset($r['error'])) { // sin cupo o falla: la Ayuda siempre responde
            return $this->guideAnswer($message) + ['simulated' => false, 'notice' => $r['error']];
        }

        return ['reply' => trim($r['text']), 'source' => 'ai', 'simulated' => $r['simulated']]
            + ($hits && $hits[0][1] >= 3 ? self::links($hits[0][2]) : ['link' => null, 'article' => ['Abrir la Ayuda', 'ayuda']]);
    }
}
