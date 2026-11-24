<?php

namespace App\Libraries;

/**
 * Notificaciones dentro de la plataforma. Úsela con service('notify').
 *
 * Avisos que se generan solos (cada uno solo a quien le corresponde):
 * - Actividad publicada → estudiantes del grupo y sus acudientes.
 * - Entrega de reto o evaluación con respuestas abiertas → docentes de la asignatura (por revisar).
 * - Entrega calificada o devuelta / evaluación revisada → el estudiante y sus acudientes.
 * - Vence mañana (estudiante) y venció sin entregar (estudiante y acudientes): barrido perezoso, como máximo cada 30 min
 *   por institución, al cargar una pantalla (no hay tareas programadas).
 * - IA al 80 % del cupo: la persona (su tope diario) y la administración (plan o rol).
 * - Acudiente vinculado → el acudiente.
 * El correo se agregará cuando la plataforma tenga configurado el envío; hoy los avisos se ven en la campana.
 */
class Notifier
{
    /**
     * Temas que la persona configura en Mi perfil (tabla notification_prefs) y los tipos de aviso (kind) que agrupan.
     * email = valor por defecto del correo cuando la persona no ha elegido.
     */
    public const TOPICS = [
        'activity'   => ['label' => 'Nuevas actividades', 'hint' => 'Retos y evaluaciones publicados para ti o para tus estudiantes vinculados', 'kinds' => ['activity'], 'email' => false],
        'submission' => ['label' => 'Entregas por revisar', 'hint' => 'Docentes: un estudiante entregó', 'kinds' => ['submission'], 'email' => false],
        'grade'      => ['label' => 'Calificaciones y devoluciones', 'hint' => 'Notas, retroalimentación y entregas devueltas para corregir', 'kinds' => ['grade', 'returned'], 'email' => true],
        'due'        => ['label' => 'Fechas límite', 'hint' => 'Vence pronto y venció sin entregar', 'kinds' => ['due', 'missed'], 'email' => true],
        'messages'   => ['label' => 'Mensajes', 'hint' => 'Mensajes de tus docentes y de la institución (los importantes llegan siempre)', 'kinds' => ['message'], 'email' => true],
        'news'       => ['label' => 'Noticias y eventos', 'hint' => 'Boletines publicados y eventos nuevos de la institución', 'kinds' => ['news', 'event'], 'email' => false],
        'agenda'     => ['label' => 'Notas de la agenda', 'hint' => 'Cuando un docente publica una nota para tu grupo', 'kinds' => ['agenda'], 'email' => false],
        'ai'         => ['label' => 'Cupo de IA al 80 %', 'hint' => 'Tu cupo diario o el de la institución', 'kinds' => ['ai'], 'email' => false],
        'guardian'   => ['label' => 'Vínculos de acudiente', 'hint' => 'Cuando la institución te vincula con un estudiante', 'kinds' => ['guardian'], 'email' => true],
    ];

    /** Tema de un tipo de aviso (null = no configurable: siempre se envía). */
    public static function topicOf(string $kind): ?string
    {
        foreach (self::TOPICS as $k => $t) {
            if (in_array($kind, $t['kinds'], true)) {
                return $k;
            }
        }

        return null;
    }

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    /** Envía a varias personas. Con $key, no repite el aviso a quien ya lo tiene. */
    public function send(array $userIds, string $kind, string $title, ?string $body = null, ?string $url = null, ?string $key = null, ?int $institutionId = null): void
    {
        $iid = $institutionId ?? ($this->iid() ?: null);
        $now = date('Y-m-d H:i:s');
        $ids = array_unique(array_filter(array_map('intval', $userIds)));
        if ($ids && ($topic = self::topicOf($kind))) { // quien apagó este tema en Mi perfil no lo recibe en la campana
            $off = array_map('intval', array_column($this->db()->table('notification_prefs')->select('user_id')->where('topic', $topic)->where('in_app', 0)
                ->whereIn('user_id', $ids)->get()->getResultArray(), 'user_id'));
            $ids = array_diff($ids, $off);
        }
        if ($ids && $key !== null) { // una sola consulta: quién ya tiene este aviso
            $have = array_map('intval', array_column($this->db()->table('notifications')->select('user_id')->where('dedupe_key', $key)
                ->whereIn('user_id', $ids)->get()->getResultArray(), 'user_id'));
            $ids  = array_diff($ids, $have);
        }
        if (! $ids) {
            return;
        }
        $row = ['institution_id' => $iid, 'kind' => $kind, 'title' => mb_substr($title, 0, 200), 'body' => $body !== null ? mb_substr($body, 0, 500) : null,
            'url' => $url, 'dedupe_key' => $key, 'created_at' => $now];
        $this->db()->table('notifications')->insertBatch(array_map(static fn ($uid) => ['user_id' => $uid] + $row, array_values($ids)));
    }

    /* ---------- Bandeja ---------- */

    public function unread(int $userId): int
    {
        return $this->db()->table('notifications')->where('user_id', $userId)->where('read_at', null)->countAllResults();
    }

    public function latest(int $userId, int $limit = 8, bool $onlyUnread = false): array
    {
        $q = $this->db()->table('notifications n')->select('n.*, i.name AS institution')->join('institutions i', 'i.id = n.institution_id', 'left')
            ->where('n.user_id', $userId);
        if ($onlyUnread) {
            $q->where('n.read_at', null);
        }

        return $q->orderBy('n.id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /** Marca como leída y devuelve la notificación (o null si no es de la persona). */
    public function open(int $userId, int $id): ?array
    {
        $n = $this->db()->table('notifications')->where(['id' => $id, 'user_id' => $userId])->get()->getRowArray();
        if ($n && ! $n['read_at']) {
            $this->db()->table('notifications')->where('id', $id)->update(['read_at' => date('Y-m-d H:i:s')]);
        }

        return $n;
    }

    public function readAll(int $userId): void
    {
        $this->db()->table('notifications')->where('user_id', $userId)->where('read_at', null)->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    /* ---------- Destinatarios ---------- */

    private function guardiansOf(array $studentIds): array
    {
        if (! $studentIds) {
            return [];
        }

        return array_map('intval', array_column($this->db()->table('guardian_links')->select('guardian_id')->where('institution_id', $this->iid())
            ->whereIn('student_id', $studentIds)->get()->getResultArray(), 'guardian_id'));
    }

    /** Datos de una asignación: actividad, asignatura, grupo y docentes. */
    private function assignment(int $assignmentId): ?array
    {
        $x = $this->db()->table('activity_assignments x')->select('x.id, x.group_id, x.due_at, a.id AS activity_id, a.title, a.kind, a.offering_id, s.name AS subject')
            ->join('activities a', 'a.id = x.activity_id')->join('subject_offerings o', 'o.id = a.offering_id')->join('subjects s', 's.id = o.subject_id')
            ->where('x.institution_id', $this->iid())->where('x.id', $assignmentId)->get()->getRowArray();
        if ($x) {
            $x['teachers'] = array_map('intval', array_column($this->db()->table('teaching_assignments')->select('user_id')->where('institution_id', $this->iid())
                ->where('offering_id', $x['offering_id'])->get()->getResultArray(), 'user_id'));
        }

        return $x;
    }

    private static function studentUrl(array $x): string
    {
        return ($x['kind'] === 'quiz' ? 'estudiante/evaluaciones/' : 'estudiante/retos/') . $x['id'];
    }

    /* ---------- Eventos ---------- */

    public function activityPublished(int $activityId): void
    {
        $x = $this->db()->table('activity_assignments')->select('id')->where('institution_id', $this->iid())->where('activity_id', $activityId)->get()->getRow();
        $x = $x ? $this->assignment((int) $x->id) : null;
        if (! $x) {
            return;
        }
        $students = array_map('intval', array_column($this->db()->table('class_group_members')->select('user_id')->where('institution_id', $this->iid())
            ->where('group_id', $x['group_id'])->get()->getResultArray(), 'user_id'));
        $what = $x['kind'] === 'quiz' ? 'Nueva evaluación' : 'Nuevo reto';
        $due  = 'Cierra ' . date_short($x['due_at']);
        $this->send($students, 'activity', $what . ': ' . $x['title'], $x['subject'] . ' · ' . $due, self::studentUrl($x), 'pub:' . $x['id']);
        foreach ($students as $sid) {
            $this->send($this->guardiansOf([$sid]), 'activity', $what . ' para ' . $this->name($sid) . ': ' . $x['title'], $x['subject'] . ' · ' . $due, 'acudiente/' . $sid, 'pub:' . $x['id'] . ':' . $sid);
        }
    }

    /** Un estudiante entregó (reto) o terminó una evaluación que necesita revisión. */
    public function submitted(int $assignmentId, int $studentId): void
    {
        if (! $x = $this->assignment($assignmentId)) {
            return;
        }
        $this->send($x['teachers'], 'submission', 'Por revisar: ' . $x['title'], $this->name($studentId) . ' entregó · ' . $x['subject'], 'entregas/' . $x['id']);
    }

    /** Calificada (grade), devuelta (null + returned) o evaluación revisada. */
    public function graded(int $assignmentId, int $studentId, ?float $grade, bool $returned = false): void
    {
        if (! $x = $this->assignment($assignmentId)) {
            return;
        }
        $g = $grade !== null ? number_format($grade, 1, ',', '') : null;
        if ($returned) {
            $this->send([$studentId], 'returned', 'Tu docente devolvió «' . $x['title'] . '»', 'Revisa la retroalimentación y vuelve a entregar.', self::studentUrl($x));
            $this->send($this->guardiansOf([$studentId]), 'returned', 'Devuelta para corregir: ' . $x['title'], $this->name($studentId) . ' · ' . $x['subject'], 'acudiente/' . $studentId);

            return;
        }
        $this->send([$studentId], 'grade', 'Calificaron «' . $x['title'] . '»' . ($g ? ': ' . $g : ''), $x['subject'] . ' · Mira la retroalimentación de tu docente.', self::studentUrl($x));
        $this->send($this->guardiansOf([$studentId]), 'grade', 'Nota de ' . $this->name($studentId) . ($g ? ': ' . $g : ''), $x['title'] . ' · ' . $x['subject'], 'acudiente/' . $studentId);
    }

    public function guardianLinked(int $guardianId, int $studentId): void
    {
        $this->send([$guardianId], 'guardian', 'Ya puede seguir a ' . $this->name($studentId), 'La institución vinculó su cuenta. Vea su progreso y sus actividades.', 'acudiente/' . $studentId, 'link:' . $studentId);
    }

    /** Tras cada uso de IA: avisos al 80 % (una vez por día para la persona; una vez por mes para plan y rol). */
    public function aiThreshold(int $institutionId, int $userId, string $bucket): void
    {
        $q   = service('aiQuota');
        $lim = $q->limits($institutionId, $bucket, $userId);
        if (! (int) $q->settings($institutionId)['alert_80']) {
            return;
        }
        $day   = date('Y-m-d');
        $month = date('Y-m');
        if ($lim['user'] && $q->used($institutionId, 'day', null, $userId) >= 0.8 * $lim['user']) {
            $this->send([$userId], 'ai', 'Llevas el 80 % de tu cupo de IA de hoy', 'Se renueva mañana a las 00:00.', null, 'ai80:user:' . $day, $institutionId);
        }
        $admins = array_map('intval', array_column($this->db()->table('institution_users hu')->select('hu.user_id')->join('role_permissions rp', 'rp.role_id = hu.role_id')
            ->where('hu.institution_id', $institutionId)->where('hu.status', 'active')->where('rp.permission', 'ai.settings.manage')->get()->getResultArray(), 'user_id'));
        if ($lim['plan'] && $q->used($institutionId, 'month') >= 0.8 * $lim['plan']) {
            $this->send($admins, 'ai', 'La institución lleva el 80 % del cupo de IA del mes', 'Revise el consumo y el reparto en Ajustes › IA.', 'ajustes/ia', 'ai80:plan:' . $institutionId . ':' . $month, $institutionId);
        }
        if ($lim['role'] && $q->used($institutionId, 'month', $bucket) >= 0.8 * $lim['role']) {
            $this->send($admins, 'ai', config('Ai')->bucketLabels[$bucket] . ' llevan el 80 % de su parte del cupo de IA', 'Puede ajustar el reparto o conceder cupo adicional en Ajustes › IA.', 'ajustes/ia', 'ai80:' . $bucket . ':' . $institutionId . ':' . $month, $institutionId);
        }
    }

    /**
     * Barrido de fechas (sin tareas programadas): «vence mañana» y «venció sin entregar». Se ejecuta como máximo cada
     * 30 minutos por institución, al cargar una pantalla.
     */
    public function sweep(): void
    {
        $iid = $this->iid();
        if (! $iid || cache('notify_sweep_' . $iid)) {
            return;
        }
        cache()->save('notify_sweep_' . $iid, 1, 1800);
        $now  = date('Y-m-d H:i:s');
        // Actividades que abrieron después de publicarse (activityPublished no repite: clave pub:…).
        foreach ($this->db()->table('activity_assignments x')->select('x.activity_id')->join('activities a', 'a.id = x.activity_id')->where('x.institution_id', $iid)
            ->where('a.status', 'published')->where('a.deleted_at', null)->where('x.starts_at <=', $now)->where('x.starts_at >', date('Y-m-d H:i:s', time() - 7 * 86400))
            ->where('x.due_at >', $now)->get()->getResultArray() as $p) {
            $this->activityPublished((int) $p['activity_id']);
        }
        $soon = date('Y-m-d H:i:s', time() + 86400);
        $past = date('Y-m-d H:i:s', time() - 7 * 86400);
        $rows = $this->db()->table('activity_assignments x')->select('x.id')->join('activities a', 'a.id = x.activity_id')
            ->where('x.institution_id', $iid)->where('a.status', 'published')->where('a.deleted_at', null)
            ->where('x.starts_at <=', $now)->where('x.due_at >', $past)->where('x.due_at <=', $soon)->get()->getResultArray();
        foreach ($rows as $r) {
            $x       = $this->assignment((int) $r['id']);
            $members = array_map('intval', array_column($this->db()->table('class_group_members')->select('user_id')->where('institution_id', $iid)
                ->where('group_id', $x['group_id'])->get()->getResultArray(), 'user_id'));
            $done = $x['kind'] === 'quiz'
                ? array_column($this->db()->table('attempts')->select('user_id')->where('assignment_id', $x['id'])->where('status !=', 'in_progress')->get()->getResultArray(), 'user_id')
                : array_column($this->db()->table('submissions')->select('user_id')->where('assignment_id', $x['id'])->whereIn('status', ['submitted', 'reviewed'])->get()->getResultArray(), 'user_id');
            $missing = array_values(array_diff($members, array_map('intval', $done)));
            if (! $missing) {
                continue;
            }
            if ($x['due_at'] > $now) {
                $this->send($missing, 'due', 'Vence pronto: ' . $x['title'], $x['subject'] . ' · Cierra ' . date_short($x['due_at']), self::studentUrl($x), 'due:' . $x['id']);
            } else {
                $this->send($missing, 'missed', 'Venció sin entregar: ' . $x['title'], $x['subject'] . ' · Habla con tu docente si necesitas una nueva oportunidad.', self::studentUrl($x), 'missed:' . $x['id']);
                foreach ($missing as $sid) {
                    $this->send($this->guardiansOf([$sid]), 'missed', $this->name($sid) . ' no entregó «' . $x['title'] . '»', $x['subject'] . ' · Venció ' . date_short($x['due_at']), 'acudiente/' . $sid, 'missed:' . $x['id'] . ':' . $sid);
                }
            }
        }
    }

    private function name(int $userId): string
    {
        return (string) ($this->db()->table('users')->select('name')->where('id', $userId)->get()->getRow()->name ?? '');
    }
}
