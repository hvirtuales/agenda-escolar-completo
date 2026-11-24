<?php

namespace App\Libraries;

use App\Models\ActivityModel;
use App\Models\QuestionModel;

/**
 * Plantillas de la biblioteca: copias reutilizables de retos, evaluaciones (con preguntas) y páginas del cuaderno.
 * Úsela con service('templates'). Crear y usar exige content.edit (y dictar la asignatura de origen o destino);
 * editar o eliminar, ser autor o coordinación (academic.structure.manage).
 */
class Templates
{
    public const KINDS = ['challenge' => 'Reto', 'quiz' => 'Evaluación', 'page' => 'Página'];

    private const MAX_PAYLOAD = 60000;

    /** Campos de la actividad que viajan en la plantilla (sin fechas, grupo ni estado). */
    private const ACTIVITY_FIELDS = ['objective', 'instructions', 'deliverable', 'steps', 'hints', 'rubric', 'difficulty', 'mode', 'ai_mode', 'points', 'retries',
        'time_limit', 'max_attempts', 'shuffle', 'results_mode'];

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

    /** Id de asignatura solo si es de la institución activa (SubjectModel filtra por institución); si no, null. */
    public static function validSubject(string $sid): ?int
    {
        return ctype_digit($sid) && (int) $sid > 0 && model(\App\Models\SubjectModel::class)->find((int) $sid) ? (int) $sid : null;
    }

    public function canManage(array $t): bool
    {
        return (int) $t['created_by'] === $this->uid() || service('tenant')->can('academic.structure.manage') || service('auth')->isSuperadmin();
    }

    /** Plantillas de la institución con asignatura y autor. $f: kind, subject (id, '0' = generales), q. */
    public function list(array $f = []): array
    {
        $q = $this->db()->table('library_templates t')->select('t.id, t.kind, t.title, t.description, t.subject_id, t.uses, t.created_by, t.created_at, s.name AS subject, u.name AS author')
            ->join('subjects s', 's.id = t.subject_id', 'left')->join('users u', 'u.id = t.created_by', 'left')
            ->where('t.institution_id', $this->iid())->where('t.deleted_at', null);
        if (isset(self::KINDS[$f['kind'] ?? ''])) {
            $q->where('t.kind', $f['kind']);
        }
        if (($f['subject'] ?? '') === '0') {
            $q->where('t.subject_id', null);
        } elseif (ctype_digit((string) ($f['subject'] ?? ''))) {
            $q->where('t.subject_id', (int) $f['subject']);
        }
        if (($f['q'] ?? '') !== '') {
            $q->groupStart()->like('t.title', $f['q'])->orLike('t.description', $f['q'])->groupEnd();
        }

        return array_map(fn ($t) => $t + ['can_manage' => $this->canManage($t)], $q->orderBy('t.uses', 'DESC')->orderBy('t.title')->get()->getResultArray());
    }

    public function get(int $id): ?array
    {
        $t = $this->db()->table('library_templates')->where(['id' => $id, 'institution_id' => $this->iid()])->where('deleted_at', null)->get()->getRowArray();
        if ($t) {
            $t['payload'] = json_decode((string) $t['payload'], true) ?: [];
        }

        return $t;
    }

    private function subjectOfOffering(int $offeringId): ?int
    {
        $o = $this->db()->table('subject_offerings')->select('subject_id')->where(['id' => $offeringId, 'institution_id' => $this->iid()])->get()->getRow();

        return $o ? (int) $o->subject_id : null;
    }

    private function insert(string $kind, string $title, ?int $subjectId, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (strlen($json) > self::MAX_PAYLOAD) {
            return ['error' => 'Es demasiado grande para guardarlo como plantilla (máximo unos 60 KB de contenido).'];
        }
        $now = date('Y-m-d H:i:s');
        $this->db()->table('library_templates')->insert(['institution_id' => $this->iid(), 'kind' => $kind, 'title' => mb_substr($title, 0, 160),
            'subject_id' => $subjectId, 'payload' => $json, 'created_by' => $this->uid(), 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $this->db()->insertID();
        service('audit')->log('library.template.create', ['plantilla' => $title, 'tipo' => self::KINDS[$kind]]);

        return ['id' => $id];
    }

    /** Plantilla a partir de un reto o una evaluación (lo guardado). */
    public function fromActivity(int $activityId): array
    {
        $acts = service('activities');
        $a    = $acts->get($activityId);
        if (! $acts->canManage($a)) {
            return ['error' => 'Esa actividad no existe o no puede usarla.'];
        }
        $payload = ['title' => $a['title']];
        foreach (self::ACTIVITY_FIELDS as $k) {
            $payload[$k] = $a[$k] ?? null;
        }
        if ($a['kind'] === 'quiz') {
            $payload['questions'] = array_map(static fn ($q) => ['type' => $q['type'], 'text' => $q['text'], 'options' => $q['options'], 'correct' => $q['correct'],
                'points' => $q['points'], 'topic' => $q['topic']], service('quizzes')->questions($activityId));
        }

        return $this->insert($a['kind'], $a['title'], $this->subjectOfOffering((int) $a['offering_id']), $payload);
    }

    /** Plantilla a partir de una página del cuaderno (su borrador actual: título, objetivo y bloques). */
    public function fromPage(int $pageId): array
    {
        $p = $this->db()->table('pages p')->select('p.id, p.workspace_id, w.offering_id')->join('workspaces w', 'w.id = p.workspace_id')
            ->where('p.institution_id', $this->iid())->where('p.id', $pageId)->where('p.deleted_at', null)->get()->getRowArray();
        if (! $p || ! service('content')->canEditOffering((int) $p['offering_id'])) {
            return ['error' => 'Esa página no existe o no puede usarla.'];
        }
        $page = service('content')->page($pageId, (int) $p['workspace_id']);

        return $this->insert('page', $page['title'], $this->subjectOfOffering((int) $p['offering_id']), [
            'title' => $page['title'], 'objective' => $page['objective'],
            'blocks' => array_map(static fn ($b) => ['type' => $b['type'], 'data' => $b['data']], $page['blocks']),
        ]);
    }

    public function update(int $id, array $in): ?string
    {
        $t = $this->get($id);
        if (! $t || ! $this->canManage($t)) {
            return 'Esa plantilla no existe o no puede editarla.';
        }
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) {
            return 'Escriba un título de hasta 160 caracteres.';
        }
        $sid = (string) ($in['subject_id'] ?? '');
        $this->db()->table('library_templates')->where('id', $id)->update([
            'title' => $title, 'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 500) ?: null,
            'subject_id' => self::validSubject($sid), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return null;
    }

    public function delete(int $id): ?string
    {
        $t = $this->get($id);
        if (! $t || ! $this->canManage($t)) {
            return 'Esa plantilla no existe o no puede eliminarla.';
        }
        $this->db()->table('library_templates')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        service('audit')->log('library.template.delete', ['plantilla' => $t['title']]);

        return null;
    }

    /**
     * Usa la plantilla en una asignatura: crea un borrador (reto, evaluación o página en la primera sección del cuaderno).
     * Devuelve ['url' => adónde ir] o ['error' => mensaje].
     */
    public function use(int $id, int $offeringId, ?string $title = null): array
    {
        $t = $this->get($id);
        if (! $t) {
            return ['error' => 'Esa plantilla no existe.'];
        }
        if (! service('content')->canEditOffering($offeringId)) {
            return ['error' => 'Solo puede usar plantillas en las asignaturas que dicta.'];
        }
        $p     = $t['payload'];
        $title = mb_substr(trim((string) ($title ?: ($p['title'] ?? $t['title']))), 0, 160) ?: $t['title'];

        if ($t['kind'] === 'page') {
            $o = $this->db()->table('subject_offerings o')->select('s.name AS subject, g.name AS grp')->join('subjects s', 's.id = o.subject_id')
                ->join('class_groups g', 'g.id = o.group_id')->where('o.id', $offeringId)->get()->getRowArray();
            $content = service('content');
            $ws      = $content->workspaceFor($offeringId, $o['subject'] . ' · ' . $o['grp']);
            $section = $content->tree((int) $ws['id'])[0]['id'] ?? null;
            if (! $section) {
                return ['error' => 'El cuaderno de esa asignatura no tiene secciones.'];
            }
            $r = $content->createPage((int) $ws['id'], (int) $section, $title);
            if (isset($r['error'])) {
                return $r;
            }
            $e = $content->savePage($r['id'], (int) $ws['id'], $title, $p['objective'] ?? null, (array) ($p['blocks'] ?? []));
            if ($e) {
                return ['error' => $e];
            }
            $url = 'cuadernos/o' . $offeringId . '/' . $r['id'];
        } else {
            $row = ['offering_id' => $offeringId, 'kind' => $t['kind'], 'title' => $title, 'status' => 'draft', 'created_by' => $this->uid(), 'updated_by' => $this->uid()];
            foreach (self::ACTIVITY_FIELDS as $k) {
                if (array_key_exists($k, $p) && $p[$k] !== null) {
                    $row[$k] = is_array($p[$k]) ? json_encode($p[$k], JSON_UNESCAPED_UNICODE) : $p[$k];
                }
            }
            $row += ['steps' => '[]', 'hints' => '[]'];
            $db = $this->db();
            $db->transStart();
            $aid = (int) model(ActivityModel::class)->insert($row);
            foreach (array_values((array) ($p['questions'] ?? [])) as $i => $q) {
                model(QuestionModel::class)->insert(['activity_id' => $aid, 'type' => $q['type'], 'text' => $q['text'], 'options' => json_encode($q['options'] ?? [], JSON_UNESCAPED_UNICODE),
                    'correct' => json_encode($q['correct'] ?? [], JSON_UNESCAPED_UNICODE), 'points' => $q['points'] ?? 1, 'topic' => $q['topic'] ?? null, 'sort_order' => $i]);
            }
            $db->transComplete();
            if (! $db->transStatus()) {
                return ['error' => 'No se pudo crear el borrador. Intente de nuevo.'];
            }
            $url = 'actividades/' . $aid;
        }
        $this->db()->table('library_templates')->set('uses', 'uses + 1', false)->where('id', $id)->update();

        return ['url' => $url];
    }
}
