<?php

namespace App\Libraries;

/**
 * Mi agenda: une en un calendario las fechas de retos y evaluaciones, las notas de agenda de los docentes para sus
 * grupos y los recordatorios personales. Úsela con service('agenda').
 *
 * Quién ve qué:
 * - estudiante: cierres de sus actividades (con su estado), notas de los docentes de su grupo y sus recordatorios;
 * - docente: aperturas y cierres de las actividades que asignó, las notas de sus asignaturas y sus recordatorios;
 * - vista de estudiante ($group): lo del grupo, sin estado de ningún estudiante ni recordatorios;
 * - acudiente ($forUser = estudiante vinculado): la agenda de ese estudiante en solo lectura, sin sus recordatorios personales.
 *
 * Cada evento: date, time (H:i o null), type (due | opens | note | personal), title, meta, url, color, entry (id de la
 * nota o null), done (bool), editable (bool).
 */
class Agenda
{
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

    /** Ofertas que dicta la persona (para publicar notas al grupo). Coordinación: todas las del periodo. */
    public function teacherOfferings(): array
    {
        return service('academic')->myOfferings(); // el superadministrador ya tiene academic.structure.manage
    }

    /**
     * Eventos entre $from y $to (Y-m-d, inclusive), ordenados por fecha y hora.
     * $mode: 'student' | 'teacher'. $group: grupo de la vista de estudiante. $forUser: estudiante que mira un acudiente
     * (el permiso del vínculo lo comprueba quien llama). Sin $group ni $forUser, es la agenda de la propia persona.
     */
    public function events(string $from, string $to, string $mode, ?int $group = null, ?int $forUser = null): array
    {
        $events = $mode === 'student' ? $this->studentActivities($from, $to, $group, $forUser) : $this->teacherActivities($from, $to);
        $events = array_merge($events, $this->entries($from, $to, $mode, $group, $forUser), $this->institutionEvents($from, $to, $group !== null ? 'student' : null));
        usort($events, static fn ($a, $b) => [$a['date'], $a['time'] ?? '99:99', $a['type'] === 'due' ? 1 : 0] <=> [$b['date'], $b['time'] ?? '99:99', $b['type'] === 'due' ? 1 : 0]);

        return $events;
    }

    /** Cierres de las actividades del estudiante (o del grupo previsualizado). */
    private function studentActivities(string $from, string $to, ?int $group, ?int $forUser = null): array
    {
        $who = $forUser ?? $this->uid();
        $labels = ['todo' => t('Agenda.stTodo'), 'in_progress' => t('Agenda.stInProgress'), 'late' => t('Agenda.stLate'), 'returned' => t('Agenda.stReturned'),
            'submitted' => t('Agenda.stSubmitted'), 'graded' => t('Agenda.stGraded'), 'missed' => t('Agenda.stMissed')];
        $out = [];
        foreach (service('myProgress')->activities($group === null ? $who : 0, $group) as $a) {
            $d = substr($a['due_at'], 0, 10);
            if ($d < $from || $d > $to) {
                continue;
            }
            $out[] = ['date' => $d, 'time' => substr($a['due_at'], 11, 5), 'type' => 'due', 'title' => $a['title'],
                'meta' => ($a['kind'] === 'quiz' ? t('Agenda.quiz') : t('Agenda.challenge')) . ' · ' . $a['subject'] . ($group === null ? ' · ' . ($labels[$a['state']] ?? '') : ''),
                'subject' => $a['subject'],
                'url' => $forUser ? 'acudiente/' . $forUser : $a['url'], 'color' => $a['color'], 'entry' => null, 'done' => $group === null && in_array($a['state'], ['submitted', 'graded'], true),
                'state' => $group === null ? $a['state'] : null, 'editable' => false];
        }

        return $out;
    }

    /** Aperturas y cierres de las actividades publicadas en las asignaturas que dicta el docente. */
    private function teacherActivities(string $from, string $to): array
    {
        $offs = array_column(\App\Libraries\Academic::flatten(service('academic')->catalogFor($this->uid(), 'teacher')), null, 'id');
        if (! $offs) {
            return [];
        }
        $rows = $this->db()->table('activity_assignments x')
            ->select("x.id, x.starts_at, x.due_at, a.id AS activity_id, a.title, a.kind, a.offering_id,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = x.id AND s.status = 'submitted') AS to_review", false)
            ->join('activities a', 'a.id = x.activity_id')
            ->where('x.institution_id', $this->iid())->whereIn('a.offering_id', array_keys($offs))->where('a.status', 'published')->where('a.deleted_at', null)
            ->groupStart()->where('x.due_at >=', $from . ' 00:00:00')->where('x.due_at <=', $to . ' 23:59:59')
            ->orGroupStart()->where('x.starts_at >=', $from . ' 00:00:00')->where('x.starts_at <=', $to . ' 23:59:59')->groupEnd()->groupEnd()
            ->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $o    = $offs[(int) $r['offering_id']];
            $kind = $r['kind'] === 'quiz' ? t('Agenda.quiz') : t('Agenda.challenge');
            $base = ['title' => $r['title'], 'subject' => $o['subject'] . ' · ' . $o['group'], 'color' => $o['color'], 'entry' => null, 'done' => false, 'state' => null, 'editable' => false,
                'url' => 'actividades/' . $r['activity_id']];
            $ds   = substr($r['starts_at'], 0, 10);
            $dd   = substr($r['due_at'], 0, 10);
            if ($ds >= $from && $ds <= $to && $ds !== $dd) {
                $out[] = $base + ['date' => $ds, 'time' => substr($r['starts_at'], 11, 5), 'type' => 'opens', 'meta' => t('Agenda.opens') . ' · ' . $kind . ' · ' . $o['subject'] . ' · ' . $o['group']];
            }
            if ($dd >= $from && $dd <= $to) {
                $out[] = $base + ['date' => $dd, 'time' => substr($r['due_at'], 11, 5), 'type' => 'due',
                    'meta' => t('Agenda.closes') . ' · ' . $kind . ' · ' . $o['subject'] . ' · ' . $o['group'] . ((int) $r['to_review'] ? ' · ' . t('Agenda.toReview', [(int) $r['to_review']]) : ''),
                    'url' => (int) $r['to_review'] ? 'entregas/' . $r['id'] : $base['url']];
            }
        }

        return $out;
    }

    /** Eventos de la institución (service('events')), uno por cada día que abarcan dentro del rango. */
    private function institutionEvents(string $from, string $to, ?string $as): array
    {
        $out    = [];
        $manage = service('events')->canManage();
        foreach (service('events')->between($from, $to, $as) as $e) {
            [$cat, , $color] = Events::CATEGORIES[$e['category']] ?? Events::CATEGORIES['other'];
            $d1 = max($from, substr($e['starts_at'], 0, 10));
            $d2 = min($to, $e['ends_at'] ? substr($e['ends_at'], 0, 10) : substr($e['starts_at'], 0, 10));
            for ($d = $d1, $n = 0; $d <= $d2 && $n < 31; $d = date('Y-m-d', strtotime($d . ' +1 day')), $n++) {
                $first = $d === substr($e['starts_at'], 0, 10);
                $out[] = ['date' => $d, 'time' => $first && ! (int) $e['all_day'] ? substr($e['starts_at'], 11, 5) : null, 'type' => 'event', 'title' => $e['title'],
                    'meta' => $cat . ($e['location'] ? ' · ' . $e['location'] : '') . ' · ' . t('Agenda.typeEvent'), 'notes' => (string) $e['description'], 'subject' => null,
                    'url' => null, 'color' => $color, 'entry' => null, 'done' => false, 'state' => null, 'editable' => false,
                    // Quien gestiona los eventos los edita y elimina desde la ventana de detalle (formulario events/_form).
                    'manage' => $manage && empty($e['network_post_id']) ? self::eventForm($e) : null,
                    // Para la ventana de detalle de la agenda: el evento completo (puede abarcar varios días).
                    'event' => ['starts_at' => $e['starts_at'], 'ends_at' => $e['ends_at'], 'all_day' => (bool) $e['all_day'], 'location' => (string) $e['location'], 'category' => $cat]];
            }
        }

        return $out;
    }

    /** Datos del formulario de un evento (events/_form) a partir de su fila. */
    public static function eventForm(array $e): array
    {
        $allDay = (bool) (int) $e['all_day'];

        return ['id' => (int) $e['id'], 'title' => $e['title'], 'description' => (string) $e['description'], 'location' => (string) $e['location'],
            'category' => $e['category'], 'date' => substr($e['starts_at'], 0, 10),
            'end_date' => $e['ends_at'] && substr($e['ends_at'], 0, 10) !== substr($e['starts_at'], 0, 10) ? substr($e['ends_at'], 0, 10) : '',
            'start_time' => $allDay ? '' : substr($e['starts_at'], 11, 5), 'end_time' => ! $allDay && $e['ends_at'] ? substr($e['ends_at'], 11, 5) : '',
            'all_day' => $allDay, 'audience' => $e['audience'] === 'all' ? array_keys(Events::AUDIENCES) : explode(',', $e['audience']),
            'is_public' => (bool) (int) ($e['is_public'] ?? 0)];
    }

    /** Notas de agenda (del grupo) y recordatorios personales. */
    private function entries(string $from, string $to, string $mode, ?int $group, ?int $forUser = null): array
    {
        $uid     = $this->uid();
        $who     = $forUser ?? $uid;                       // de quién es la agenda
        $own     = $group === null && $forUser === null;   // solo en la propia hay recordatorios y edición
        $catalog = $mode === 'student'
            ? service('academic')->catalogFor($who, $group === null ? 'student' : 'group', $group)
            : service('academic')->catalogFor($uid, 'teacher');
        $offs = array_column(\App\Libraries\Academic::flatten($catalog), null, 'id');

        $q = $this->db()->table('agenda_entries e')
            ->select('e.*, u.name AS author, d.done_at')
            ->join('users u', 'u.id = e.created_by', 'left')
            ->join('agenda_done d', 'd.entry_id = e.id AND d.user_id = ' . ($group === null ? $who : 0), 'left')
            ->where('e.institution_id', $this->iid())->where('e.deleted_at', null)
            ->where('e.entry_date >=', $from)->where('e.entry_date <=', $to)
            ->groupStart();
        if ($offs) {
            $q->whereIn('e.offering_id', array_keys($offs));
        } else {
            $q->where('1 = 0', null, false);
        }
        if ($own) {
            $q->orGroupStart()->where('e.offering_id', null)->where('e.created_by', $uid)->groupEnd();
        }
        $q->groupEnd();

        return array_map(static function ($e) use ($offs, $uid, $own, $mode) {
            $label = static fn (?array $o): ?string => $o ? ($mode === 'student' ? $o['subject'] : $o['subject'] . ' · ' . $o['group']) : null;
            $o   = $e['offering_id'] ? ($offs[(int) $e['offering_id']] ?? null) : null;
            // Recordatorio personal con asignatura (etiqueta privada): muestra la asignatura y usa su color.
            $tag = ! $o && $e['tag_offering_id'] ? ($offs[(int) $e['tag_offering_id']] ?? null) : null;

            return [
                'date' => $e['entry_date'], 'time' => $e['entry_time'] ? substr($e['entry_time'], 0, 5) : null,
                'type' => $o ? 'note' : 'personal', 'title' => $e['title'],
                'meta' => $o ? $o['subject'] . ' · ' . $o['group'] . ((int) $e['created_by'] !== $uid ? ' · ' . $e['author'] : '')
                    : t('Agenda.typePersonal') . ($tag ? ' · ' . $tag['subject'] : ''),
                'subject' => $label($o ?? $tag),
                'notes' => (string) $e['notes'], 'url' => null, 'color' => $o['color'] ?? ($tag['color'] ?? null), 'entry' => (int) $e['id'],
                'done' => $e['done_at'] !== null, 'state' => null, 'editable' => $own && (int) $e['created_by'] === $uid,
                'offering_id' => $e['offering_id'] ? (int) $e['offering_id'] : null,
                'tag_offering_id' => $tag ? (int) $e['tag_offering_id'] : null,
            ];
        }, $q->orderBy('e.entry_date')->orderBy('e.entry_time')->get()->getResultArray());
    }

    /* ---------- Escrituras ---------- */

    /** Valida y normaliza los datos de una nota. Devuelve [datos] o [errores]. */
    private function clean(array $in, bool $canTeach): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        $date  = (string) ($in['entry_date'] ?? '');
        $time  = (string) ($in['entry_time'] ?? '');
        $off   = (int) ($in['offering_id'] ?? 0);
        $err   = [];
        if ($title === '' || mb_strlen($title) > 160) {
            $err['title'] = t('Agenda.errTitle');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            $err['entry_date'] = t('Agenda.errDate');
        }
        if ($time !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $err['entry_time'] = t('Agenda.errTime');
        }
        if ($off && (! $canTeach || ! service('content')->canEditOffering($off))) {
            $err['offering_id'] = t('Agenda.errOffering');
        }
        $tag = $off ? 0 : (int) ($in['tag_offering_id'] ?? 0); // una nota de grupo ya tiene su asignatura
        if ($tag && ! isset($this->tagOfferings()[$tag])) {
            $err['tag_offering_id'] = t('Agenda.errTag');
        }

        return $err ? ['errors' => $err] : ['data' => [
            'title' => $title, 'entry_date' => $date, 'entry_time' => $time !== '' ? $time . ':00' : null,
            'notes' => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 1000) ?: null, 'offering_id' => $off ?: null,
            'tag_offering_id' => $tag ?: null,
        ]];
    }

    /**
     * Asignaturas que la persona puede ponerle a un recordatorio personal: las suyas en el periodo actual
     * (estudiante: las de su grupo; docente: las que dicta). [offering_id => ['subject', 'color', …]], por nombre.
     */
    public function tagOfferings(): array
    {
        $mode  = service('tenant')->isStudent() ? 'student' : 'teacher';
        $items = \App\Libraries\Academic::flatten(service('academic')->catalogFor($this->uid(), $mode));
        $coll  = class_exists(\Collator::class) ? new \Collator('es') : null;
        usort($items, static fn ($a, $b) => $coll ? $coll->compare($a['subject'], $b['subject']) : strcasecmp($a['subject'], $b['subject']));

        return array_column($items, null, 'id');
    }

    /** Crea una nota. Si es para un grupo, avisa a sus estudiantes. Devuelve [id] o [errors]. */
    public function create(array $in, bool $canTeach): array
    {
        $r = $this->clean($in, $canTeach);
        if (isset($r['errors'])) {
            return $r;
        }
        $now = date('Y-m-d H:i:s');
        $this->db()->table('agenda_entries')->insert($r['data'] + ['institution_id' => $this->iid(), 'created_by' => $this->uid(), 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $this->db()->insertID();
        if ($r['data']['offering_id']) {
            $this->notifyGroup($id, $r['data']);
        }

        return ['id' => $id];
    }

    /** Nota de la persona (solo quien la creó la edita o la borra). */
    private function own(int $id): ?array
    {
        return $this->db()->table('agenda_entries')->where(['id' => $id, 'institution_id' => $this->iid(), 'created_by' => $this->uid()])
            ->where('deleted_at', null)->get()->getRowArray();
    }

    public function update(int $id, array $in, bool $canTeach): array
    {
        if (! $this->own($id)) {
            return ['errors' => ['title' => t('Agenda.noteNotYours')]];
        }
        $r = $this->clean($in, $canTeach);
        if (isset($r['errors'])) {
            return $r;
        }
        $this->db()->table('agenda_entries')->where('id', $id)->update($r['data'] + ['updated_at' => date('Y-m-d H:i:s')]);

        return ['id' => $id];
    }

    public function delete(int $id): bool
    {
        if (! $this->own($id)) {
            return false;
        }
        $this->db()->table('agenda_entries')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    /** Marca o desmarca una nota como hecha, si la persona la puede ver. */
    public function toggleDone(int $id, string $mode): ?bool
    {
        $e = $this->db()->table('agenda_entries')->where(['id' => $id, 'institution_id' => $this->iid()])->where('deleted_at', null)->get()->getRowArray();
        if (! $e) {
            return null;
        }
        $visible = array_filter($this->entries($e['entry_date'], $e['entry_date'], $mode, null), static fn ($x) => $x['entry'] === $id);
        if (! $visible) {
            return null;
        }
        $t = $this->db()->table('agenda_done');
        if ($t->where(['entry_id' => $id, 'user_id' => $this->uid()])->countAllResults()) {
            $this->db()->table('agenda_done')->where(['entry_id' => $id, 'user_id' => $this->uid()])->delete();

            return false;
        }
        $this->db()->table('agenda_done')->insert(['entry_id' => $id, 'user_id' => $this->uid(), 'done_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    private function notifyGroup(int $id, array $d): void
    {
        $o = $this->db()->table('subject_offerings o')->select('o.group_id, s.name AS subject')->join('subjects s', 's.id = o.subject_id')
            ->where('o.id', $d['offering_id'])->get()->getRowArray();
        if (! $o) {
            return;
        }
        $students = array_column($this->db()->table('class_group_members')->select('user_id')->where('institution_id', $this->iid())
            ->where('group_id', $o['group_id'])->get()->getResultArray(), 'user_id');
        service('notify')->send($students, 'agenda', 'Nueva nota en tu agenda: ' . $d['title'], $o['subject'] . ' · ' . date_short($d['entry_date']),
            'estudiante/agenda?dia=' . $d['entry_date'], 'agenda:' . $id);
        // Acudientes: un aviso por estudiante vinculado del grupo.
        if ($students) {
            foreach ($this->db()->table('guardian_links l')->select('l.guardian_id, l.student_id, u.name')->join('users u', 'u.id = l.student_id')
                ->where('l.institution_id', $this->iid())->whereIn('l.student_id', $students)->get()->getResultArray() as $l) {
                service('notify')->send([(int) $l['guardian_id']], 'agenda', 'Nota en la agenda de ' . explode(' ', $l['name'])[0] . ': ' . $d['title'],
                    $o['subject'] . ' · ' . date_short($d['entry_date']), 'acudiente/agenda?estudiante=' . $l['student_id'] . '&dia=' . $d['entry_date'], 'agenda:' . $id . ':' . $l['student_id']);
            }
        }
    }
}
