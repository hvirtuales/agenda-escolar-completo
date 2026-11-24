<?php

namespace App\Controllers;

use App\Libraries\Activities;

/** Actividades del docente (documento maestro 4.4 y 4.5): retos y evaluaciones, creación, edición y publicación. */
class ActivitiesController extends BaseController
{
    public function index()
    {
        $offerings = service('academic')->myOfferings();
        $rows      = service('activities')->listForTeacher();

        // Filtros por asignatura y grupo: las opciones salen solo de las ofertas de la persona (no se puede pedir otra).
        $subjects = $groups = [];
        foreach ($offerings as $o) {
            $subjects[$o['subject_id']] = $o['subject'];
            $groups[$o['group_id']]     = $o['group'];
        }
        $coll = class_exists(\Collator::class) ? new \Collator('es') : null;
        $sort = static fn (array &$a) => $coll ? uasort($a, [$coll, 'compare']) : natcasesort($a);
        $sort($subjects);
        $sort($groups);
        $subj  = (int) $this->request->getGet('asignatura');
        $group = (int) $this->request->getGet('grupo');
        $subj  = isset($subjects[$subj]) ? $subj : 0;
        $group = isset($groups[$group]) ? $group : 0;
        $state = (string) $this->request->getGet('estado');
        $state = isset(self::STATE_FILTERS[$state]) ? $state : '';
        if ($subj || $group || $state !== '') {
            $byId = array_column($offerings, null, 'id');
            $rows = array_values(array_filter($rows, static function ($r) use ($byId, $subj, $group, $state) {
                $o = $byId[$r['offering_id']] ?? null;

                return $o && (! $subj || $o['subject_id'] === $subj) && (! $group || $o['group_id'] === $group) && self::matchesState($r, $state);
            }));
        }

        return view('activities/index', $this->layoutData() + [
            'title'     => 'Actividades',
            'rows'      => $rows,
            'offerings' => $offerings,
            'subjects'  => $subjects,
            'groups'    => $groups,
            'filters'   => ['asignatura' => $subj, 'grupo' => $group, 'estado' => $state],
            'states'    => self::STATE_FILTERS,
        ]);
    }

    /** Filtro «Estado» de la lista: mismos estados que muestran las insignias de cada fila. */
    public const STATE_FILTERS = [
        'borrador' => 'Borrador', 'programado' => 'Programado', 'abierto' => 'Abierto', 'cerrado' => 'Cerrado', 'por-revisar' => 'Con entregas por revisar',
    ];

    private static function matchesState(array $r, string $state): bool
    {
        return match ($state) {
            ''            => true,
            'borrador'    => $r['status'] !== 'published',
            'programado'  => $r['status'] === 'published' && $r['state'] === 'scheduled',
            'abierto'     => $r['status'] === 'published' && $r['state'] === 'open',
            'cerrado'     => $r['status'] === 'published' && $r['state'] === 'closed',
            'por-revisar' => (int) $r['to_review'] > 0,
            default       => true,
        };
    }

    public function create()
    {
        $quiz = $this->request->getPost('kind') === 'quiz';
        $args = [(int) $this->request->getPost('offering_id'), (string) $this->request->getPost('title')];
        $r    = $quiz ? service('quizzes')->create(...$args) : service('activities')->create(...$args);
        if (isset($r['error'])) {
            return $this->backWith('error', $r['error']);
        }
        $msg = $quiz ? 'Evaluación creada como borrador. Agregue las preguntas y publíquela.' : 'Reto creado como borrador. Complete las instrucciones y publíquelo.';

        return redirect()->to('actividades/' . $r['id'])->with('toast', ['type' => 'ok', 'msg' => $msg]);
    }

    public function edit(int $id)
    {
        $act = service('activities')->get($id);
        if (! service('activities')->canManage($act)) {
            return redirect()->to('actividades')->with('toast', ['type' => 'error', 'msg' => 'Esa actividad no existe o no puede editarla.']);
        }
        $x     = $act['assignment'];
        $stats = null;
        if ($x) {
            $stats = $act['kind'] === 'quiz'
                ? ['submitted' => db_connect()->table('attempts')->select('COUNT(DISTINCT user_id) AS n', false)->where('institution_id', service('tenant')->institutionId())
                    ->where('assignment_id', $x['id'])->where('status !=', 'in_progress')->get()->getRow()->n ?? 0]
                : db_connect()->table('submissions')->select("SUM(CASE WHEN status IN ('submitted','reviewed','returned') THEN 1 ELSE 0 END) AS submitted, SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) AS to_review", false)
                    ->where('institution_id', service('tenant')->institutionId())->where('assignment_id', $x['id'])->get()->getRowArray();
        }
        $data = $this->layoutData() + ['title' => $act['title'], 'c' => $act, 'state' => Activities::assignmentState($x), 'stats' => $stats];
        if ($act['kind'] === 'quiz') {
            return view('activities/quiz_edit', $data + ['questions' => service('quizzes')->questions($id),
                'aiGen' => service('tenant')->can('ai.teacher.use'), 'quota' => service('aiQuota')->balance(),
                'pickerBanks' => array_values(array_filter(service('banks')->list(), static fn ($b) => $b['questions'] > 0))]);
        }

        return view('activities/challenge_edit', $data);
    }

    public function save(int $id)
    {
        $in  = $this->request->getPost();
        $act = service('activities')->get($id);
        $errors = ($act['kind'] ?? '') === 'quiz' ? service('quizzes')->save($id, $in) : service('activities')->save($id, $in);
        if ($errors) {
            if (isset($errors['_'])) {
                return $this->backWith('error', $errors['_']);
            }

            return redirect()->back()->withInput()->with('errors', $errors)->with('toast', ['type' => 'error', 'msg' => reset($errors)]);
        }
        if (($in['then'] ?? '') === 'template') {
            $r = service('templates')->fromActivity($id);

            return isset($r['error']) ? $this->backWith('error', $r['error'])
                : redirect()->to('biblioteca?t=plantillas')->with('toast', ['type' => 'ok', 'msg' => 'Cambios guardados y plantilla creada en la biblioteca.']);
        }
        if (($in['then'] ?? '') === 'publish') {
            $e = service('activities')->publish($id, (string) ($in['starts_at'] ?? ''), (string) ($in['due_at'] ?? ''), ! empty($in['allow_late']));
            if ($e) {
                return redirect()->back()->withInput()->with('errors', $e)->with('toast', ['type' => 'error', 'msg' => $e['_'] ?? reset($e)]);
            }

            return redirect()->to('actividades/' . $id)->with('toast', ['type' => 'ok', 'msg' => 'Publicada y asignada. Los estudiantes la verán desde la fecha de inicio.']);
        }

        return redirect()->to('actividades/' . $id)->with('toast', ['type' => 'ok', 'msg' => 'Cambios guardados.']);
    }
}
