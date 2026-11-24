<?php

namespace App\Libraries;

/**
 * Mensajes de la institución: masivos (por rol o por grupo) y particulares (a personas). Úsela con service('messages').
 *
 * Quién escribe a quién (siempre dentro de la institución activa):
 * - Rectoría y coordinación (tenant.users.manage): a todos por rol, a cualquier grupo (estudiantes y/o familias) y a
 *   cualquier persona activa.
 * - Docentes (content.edit): a los estudiantes y familias de los grupos donde dictan, y a sus colegas (docentes y
 *   directivos). No a toda la institución.
 * - Estudiantes y familias: reciben; no envían.
 * Destinos: role:{teacher|student|guardian|staff} · group:{id}:{students|guardians} · user:{id}.
 * Lo importante (priority = important) llega aunque la persona haya apagado los avisos de mensajes.
 */
class Messages
{
    public const ROLES = ['teacher' => 'Todos los docentes', 'student' => 'Todos los estudiantes', 'guardian' => 'Todas las familias', 'staff' => 'Rectoría y coordinación'];

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    public function canSend(): bool
    {
        return service('tenant')->can('content.edit');
    }

    public function isManager(): bool
    {
        return service('tenant')->can('tenant.users.manage') || service('auth')->isSuperadmin();
    }

    /** Grupos a los que puede escribir: todos (directivos) o los suyos (docentes). [id, name] */
    public function groups(): array
    {
        $period = service('academic')->currentPeriod();
        if (! $period) {
            return [];
        }
        $all = service('academic')->groups((int) $period['id']);
        if ($this->isManager()) {
            return $all;
        }
        $mine = array_unique(array_column(service('academic')->myOfferings(), 'group_id'));

        return array_values(array_filter($all, static fn ($g) => in_array((int) $g['id'], array_map('intval', $mine), true)));
    }

    /** Miembros activos con la clave de su rol (teacher, student, guardian, admin, coordinator). */
    private function members()
    {
        return $this->db()->table('institution_users m')->select('m.user_id, r.template_key, u.name, u.email')
            ->join('roles r', 'r.id = m.role_id')->join('users u', 'u.id = m.user_id')
            ->where('m.institution_id', $this->iid())->where('m.status', 'active')->where('u.status', 'active');
    }

    private function studentsOfGroup(int $groupId): array
    {
        return array_map('intval', array_column($this->db()->table('class_group_members')->select('user_id')
            ->where('institution_id', $this->iid())->where('group_id', $groupId)->get()->getResultArray(), 'user_id'));
    }

    private function guardiansOf(array $students): array
    {
        return $students ? array_map('intval', array_column($this->db()->table('guardian_links')->select('guardian_id')
            ->where('institution_id', $this->iid())->whereIn('student_id', $students)->get()->getResultArray(), 'guardian_id')) : [];
    }

    /** Personas a las que puede escribir individualmente (ids). */
    private function reachable(): ?array
    {
        if ($this->isManager()) {
            return null; // todas
        }
        $students = [];
        foreach ($this->groups() as $g) {
            $students = array_merge($students, $this->studentsOfGroup((int) $g['id']));
        }
        $staff = array_map('intval', array_column($this->members()->whereIn('r.template_key', ['teacher', 'coordinator', 'admin'])->get()->getResultArray(), 'user_id'));

        return array_values(array_unique(array_merge($students, $this->guardiansOf($students), $staff)));
    }

    /** Búsqueda de personas para «Para» (máximo 12). */
    public function searchPeople(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $labels = ['teacher' => 'Docente', 'student' => 'Estudiante', 'guardian' => 'Acudiente', 'coordinator' => 'Coordinación', 'admin' => 'Rectoría'];
        $b      = $this->members()->groupStart()->like('u.name', $q)->orLike('u.email', $q)->groupEnd()->where('m.user_id !=', $this->uid());
        if (($ids = $this->reachable()) !== null) {
            $b->whereIn('m.user_id', $ids ?: [0]);
        }

        return array_map(static fn ($r) => ['id' => (int) $r['user_id'], 'name' => $r['name'], 'role' => $labels[$r['template_key']] ?? ''],
            $b->orderBy('u.name')->limit(12)->get()->getResultArray());
    }

    /**
     * Resuelve los destinos a personas. Devuelve [ids[], resumen legible] o [error]. Ignora lo que la persona no
     * puede alcanzar (un docente no puede enviar «role:*» ni a grupos ajenos).
     */
    public function resolve(array $targets): array
    {
        $ids   = [];
        $parts = [];
        $groups = array_column($this->groups(), 'name', 'id');
        $reach  = $this->reachable();
        $people = 0;
        foreach (array_unique(array_map('strval', $targets)) as $t) {
            $p = explode(':', $t);
            if ($p[0] === 'role' && $this->isManager() && isset(self::ROLES[$p[1] ?? ''])) {
                $keys  = $p[1] === 'staff' ? ['admin', 'coordinator'] : [$p[1]];
                $ids   = array_merge($ids, array_map('intval', array_column($this->members()->whereIn('r.template_key', $keys)->get()->getResultArray(), 'user_id')));
                $parts[] = self::ROLES[$p[1]];
            } elseif ($p[0] === 'group' && isset($groups[(int) ($p[1] ?? 0)]) && in_array($p[2] ?? '', ['students', 'guardians'], true)) {
                $st      = $this->studentsOfGroup((int) $p[1]);
                $ids     = array_merge($ids, $p[2] === 'students' ? $st : $this->guardiansOf($st));
                $parts[] = $groups[(int) $p[1]] . ($p[2] === 'students' ? ' (estudiantes)' : ' (familias)');
            } elseif ($p[0] === 'user' && ($u = (int) ($p[1] ?? 0)) && ($reach === null || in_array($u, $reach, true))) {
                if ($this->members()->where('m.user_id', $u)->countAllResults()) {
                    $ids[] = $u;
                    $people++;
                }
            }
        }
        if ($people) {
            $parts[] = $people === 1 ? '1 persona' : $people . ' personas';
        }
        $ids = array_values(array_diff(array_unique($ids), [$this->uid()]));

        return $ids ? ['ids' => $ids, 'audience' => mb_substr(implode(' · ', $parts), 0, 500)] : ['error' => 'Elige al menos un destinatario válido.'];
    }

    /** Envía el mensaje: lo guarda, crea un destinatario por persona y avisa. Devuelve [id, count] o [error]. */
    public function send(array $targets, string $subject, string $body, bool $important): array
    {
        if (! $this->canSend()) {
            return ['error' => 'Tu rol no envía mensajes.'];
        }
        $subject = trim(preg_replace('/\s+/u', ' ', $subject));
        $body    = trim(str_replace("\r", '', $body));
        if ($subject === '' || mb_strlen($subject) > 160) {
            return ['error' => 'Escribe un asunto de hasta 160 caracteres.'];
        }
        if ($body === '' || mb_strlen($body) > 5000) {
            return ['error' => 'Escribe el mensaje (hasta 5.000 caracteres).'];
        }
        $r = $this->resolve($targets);
        if (isset($r['error'])) {
            return $r;
        }
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('messages')->insert(['institution_id' => $this->iid(), 'sender_id' => $this->uid(), 'subject' => $subject, 'body' => $body,
            'audience' => $r['audience'], 'priority' => $important ? 'important' : 'normal', 'recipients' => count($r['ids']), 'created_at' => $now]);
        $id = (int) $db->insertID();
        foreach (array_chunk($r['ids'], 500) as $chunk) {
            $db->table('message_recipients')->insertBatch(array_map(fn ($u) => ['institution_id' => $this->iid(), 'message_id' => $id, 'user_id' => $u], $chunk));
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            return ['error' => 'No se pudo enviar. Intenta de nuevo.'];
        }
        $from = (string) (service('auth')->user()['name'] ?? '');
        service('notify')->send($r['ids'], $important ? 'message_important' : 'message', ($important ? 'Importante: ' : 'Mensaje: ') . $subject,
            $from . ' · ' . mb_substr(preg_replace('/\s+/u', ' ', $body), 0, 140), 'mensajes/' . $id, 'msg:' . $id);
        service('audit')->log('messages.send', ['asunto' => $subject, 'destinatarios' => count($r['ids']), 'para' => $r['audience']]);

        return ['id' => $id, 'count' => count($r['ids'])];
    }

    /* ---------- Bandeja ---------- */

    /** Recibidos (con estado de lectura) o enviados (con conteo de lectura). */
    public function inbox(string $box, string $q = ''): array
    {
        $db = $this->db();
        if ($box === 'sent') {
            $b = $db->table('messages m')->select("m.*, (SELECT COUNT(*) FROM message_recipients r WHERE r.message_id = m.id AND r.read_at IS NOT NULL) AS read_count", false)
                ->where('m.institution_id', $this->iid())->where('m.sender_id', $this->uid())->where('m.network_post_id', null);
        } else {
            $b = $db->table('message_recipients r')->select('m.*, r.read_at, u.name AS sender, nw.name AS network_name')->join('messages m', 'm.id = r.message_id')->join('users u', 'u.id = m.sender_id', 'left')
                ->join('network_posts np', 'np.id = m.network_post_id', 'left')->join('networks nw', 'nw.id = np.network_id', 'left')
                ->where('r.institution_id', $this->iid())->where('r.user_id', $this->uid());
        }
        if ($q !== '') {
            $b->groupStart()->like('m.subject', $q)->orLike('m.body', $q)->groupEnd();
        }

        return $b->orderBy('m.id', 'DESC')->limit(200)->get()->getResultArray();
    }

    public function unread(): int
    {
        return $this->db()->table('message_recipients')->where('institution_id', $this->iid())->where('user_id', $this->uid())->where('read_at', null)->countAllResults();
    }

    /** Un mensaje para quien lo recibió (lo marca leído) o para quien lo envió (con destinatarios). Null si no le corresponde. */
    public function open(int $id): ?array
    {
        $db = $this->db();
        $m  = $db->table('messages m')->select('m.*, u.name AS sender, u.email AS sender_email, nw.name AS network_name')->join('users u', 'u.id = m.sender_id', 'left')
            ->join('network_posts np', 'np.id = m.network_post_id', 'left')->join('networks nw', 'nw.id = np.network_id', 'left')
            ->where('m.institution_id', $this->iid())->where('m.id', $id)->get()->getRowArray();
        if (! $m) {
            return null;
        }
        if ((int) $m['sender_id'] === $this->uid() && ! $m['network_post_id']) {
            $m['recipients_list'] = $db->table('message_recipients r')->select('u.name, r.read_at')->join('users u', 'u.id = r.user_id')
                ->where('r.message_id', $id)->orderBy('r.read_at IS NULL', '', false)->orderBy('u.name')->limit(300)->get()->getResultArray();
            $m['read_count'] = $db->table('message_recipients')->where('message_id', $id)->where('read_at IS NOT NULL', null, false)->countAllResults();
            $m['mine']       = true;

            return $m;
        }
        $rec = $db->table('message_recipients')->where(['message_id' => $id, 'user_id' => $this->uid()])->get()->getRowArray();
        if (! $rec) {
            return null;
        }
        if (! $rec['read_at']) {
            $db->table('message_recipients')->where('id', $rec['id'])->update(['read_at' => date('Y-m-d H:i:s')]);
        }

        return $m + ['mine' => false];
    }
}
