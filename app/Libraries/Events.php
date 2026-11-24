<?php

namespace App\Libraries;

/**
 * Calendario de eventos de la institución. Úsela con service('events').
 * Crean, editan y eliminan rectoría y coordinación (tenant.users.manage); todos ven los de su público, en «Eventos» y
 * en «Mi agenda». Al crear un evento se avisa a su público.
 */
class Events
{
    public const CATEGORIES = [
        'academic' => ['Académico', 'graduation-cap', '#2563EB'], 'meeting' => ['Reunión', 'users', '#7C3AED'], 'cultural' => ['Cultural', 'drama', '#DB2777'],
        'sports' => ['Deportivo', 'trophy', '#16A34A'], 'holiday' => ['Festivo o sin clases', 'sun', '#D97706'], 'other' => ['Otro', 'calendar-check', '#475569'],
    ];
    public const AUDIENCES = News::AUDIENCES;

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    public function canManage(): bool
    {
        return service('tenant')->can('tenant.users.manage') || service('auth')->isSuperadmin();
    }

    /** Público de quien mira (null = todo). $as: forzar un público (vista de estudiante: student; acudiente: guardian). */
    private function key(?string $as = null): ?string
    {
        if ($as) {
            return $as;
        }
        $t = service('tenant');

        return match (true) {
            $this->canManage()      => null,
            $t->isStudent()         => 'student',
            $t->isGuardian()        => 'guardian',
            $t->can('content.edit') => 'teacher',
            default                 => 'none',
        };
    }

    /** Eventos que se cruzan con [$from, $to] (fechas Y-m-d), visibles para el público. */
    public function between(string $from, string $to, ?string $as = null): array
    {
        $b   = $this->db()->table('events e')->select('e.*, nw.name AS network_name')
            ->join('network_posts np', 'np.id = e.network_post_id', 'left')->join('networks nw', 'nw.id = np.network_id', 'left')
            ->where('e.institution_id', $this->iid())->where('e.deleted_at', null)
            ->where('e.starts_at <=', $to . ' 23:59:59')->groupStart()->where('e.ends_at >=', $from . ' 00:00:00')->orWhere('e.ends_at', null)->groupEnd()
            ->where('e.starts_at >=', date('Y-m-d', strtotime($from . ' -60 days')) . ' 00:00:00');
        $key = $this->key($as);
        if ($key !== null) {
            $b->groupStart()->where('e.audience', 'all')->orLike('e.audience', $key)->groupEnd();
        }
        $rows = $b->orderBy('e.starts_at')->get()->getResultArray();

        // Sin fecha de fin: solo el día de inicio.
        return array_values(array_filter($rows, static fn ($e) => $e['ends_at'] !== null || (substr($e['starts_at'], 0, 10) >= $from && substr($e['starts_at'], 0, 10) <= $to)));
    }

    public function upcoming(int $days = 120): array
    {
        return $this->between(date('Y-m-d'), date('Y-m-d', strtotime("+{$days} days")));
    }

    public function past(int $days = 90): array
    {
        return array_reverse($this->between(date('Y-m-d', strtotime("-{$days} days")), date('Y-m-d', strtotime('-1 day'))));
    }

    public function get(int $id): ?array
    {
        return $this->db()->table('events')->where(['id' => $id, 'institution_id' => $this->iid()])->where('deleted_at', null)->get()->getRowArray();
    }

    /** Crea o edita. Devuelve [id] o [error]. */
    public function save(?int $id, array $in): array
    {
        if (! $this->canManage()) {
            return ['error' => 'Solo rectoría y coordinación crean eventos.'];
        }
        $old = $id ? $this->get($id) : null;
        if ($id && ! $old) {
            return ['error' => 'Ese evento no existe.'];
        }
        if ($old && $old['network_post_id']) {
            return ['error' => 'Es un evento de la red: solo la red lo modifica.'];
        }
        $row = self::normalize($in);
        if (isset($row['error'])) {
            return $row;
        }
        $row['is_public'] = empty($in['is_public']) ? 0 : 1; // se muestra en la página pública del colegio
        $db = $this->db();
        if ($id) {
            $db->table('events')->where('id', $id)->update($row);
        } else {
            $db->table('events')->insert($row + ['institution_id' => $this->iid(), 'created_by' => service('auth')->id(), 'created_at' => date('Y-m-d H:i:s')]);
            $id = (int) $db->insertID();
            $this->notify($id, $row);
        }

        return ['id' => $id];
    }

    /** Valida los datos del formulario y devuelve la fila (sin institución) o [error]. También lo usa la red. */
    public static function normalize(array $in): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        $date  = (string) ($in['date'] ?? '');
        $end   = (string) ($in['end_date'] ?? '');
        $allDay = ! empty($in['all_day']);
        $t1    = (string) ($in['start_time'] ?? '');
        $t2    = (string) ($in['end_time'] ?? '');
        if ($title === '' || mb_strlen($title) > 160) {
            return ['error' => 'Escribe un título de hasta 160 caracteres.'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            return ['error' => 'Elige la fecha del evento.'];
        }
        if (! $allDay && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t1)) {
            return ['error' => 'Escribe la hora de inicio (o marca «Todo el día»).'];
        }
        $starts = $date . ' ' . ($allDay ? '00:00' : $t1) . ':00';
        $endDay = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && $end >= $date ? $end : $date;
        $ends   = $allDay ? ($endDay !== $date ? $endDay . ' 23:59:00' : null)
            : (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t2) ? $endDay . ' ' . $t2 . ':00' : null);
        if ($ends !== null && $ends < $starts) {
            return ['error' => 'La hora de fin debe ser posterior al inicio.'];
        }
        $aud = array_values(array_intersect(array_keys(self::AUDIENCES), (array) ($in['audience'] ?? [])));
        return [
            'title' => $title, 'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 2000) ?: null,
            'location' => mb_substr(trim((string) ($in['location'] ?? '')), 0, 160) ?: null,
            'category' => isset(self::CATEGORIES[$in['category'] ?? '']) ? $in['category'] : 'other',
            'starts_at' => $starts, 'ends_at' => $ends, 'all_day' => $allDay ? 1 : 0,
            'audience' => ! $aud || count($aud) === count(self::AUDIENCES) ? 'all' : implode(',', $aud), 'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function delete(int $id): ?string
    {
        $e = $this->get($id);
        if (! $e || ! $this->canManage() || $e['network_post_id']) {
            return 'Ese evento no existe o no puedes eliminarlo.';
        }
        $this->db()->table('events')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        return null;
    }

    private function notify(int $id, array $e): void
    {
        self::notifyAudience($this->iid(), $id, $e);
    }

    /** Avisa al público de un evento en un colegio (también lo usa la red). */
    public static function notifyAudience(int $iid, int $id, array $e): void
    {
        $ids = array_values(array_diff(News::audienceUsers($iid, $e['audience']), [(int) service('auth')->id()]));
        service('notify')->send($ids, 'event', 'Evento: ' . $e['title'], self::when($e) . ($e['location'] ? ' · ' . $e['location'] : ''), 'agenda?dia=' . substr($e['starts_at'], 0, 10), 'event:' . $id, $iid);
    }

    /** Texto de la fecha y hora de un evento («sáb 22 nov, 8:00 a. m. – 12:00 p. m.»). */
    public static function when(array $e): string
    {
        $d1 = substr($e['starts_at'], 0, 10);
        $d2 = $e['ends_at'] ? substr($e['ends_at'], 0, 10) : $d1;
        $t  = static fn ($dt) => date('g:i a', strtotime($dt));
        if ((int) $e['all_day']) {
            return $d1 === $d2 ? date_short($d1) . ' · todo el día' : date_short($d1) . ' a ' . date_short($d2);
        }

        return date_short($d1) . ', ' . $t($e['starts_at']) . ($e['ends_at'] ? ' – ' . ($d2 !== $d1 ? date_short($d2) . ', ' : '') . $t($e['ends_at']) : '');
    }
}
