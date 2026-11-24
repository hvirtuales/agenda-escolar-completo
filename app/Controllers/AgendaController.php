<?php

namespace App\Controllers;

/**
 * Mi agenda (docente en «agenda», estudiante en «estudiante/agenda»): calendario del mes con el detalle del día.
 * La vista de estudiante muestra la agenda del grupo previsualizado, en solo lectura.
 */
class AgendaController extends BaseController
{
    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private function isStudent(): bool
    {
        return service('studentPreview')->isStudent();
    }

    private function canTeach(): bool
    {
        return service('tenant')->can('content.edit');
    }

    /** Agenda del docente (o de coordinación). El estudiante va a la suya. */
    public function index()
    {
        if (service('tenant')->isGuardian()) { // el acudiente ve la agenda de su estudiante
            return redirect()->to('acudiente/agenda' . ($this->request->getUri()->getQuery() ? '?' . $this->request->getUri()->getQuery() : ''));
        }
        if ($this->isStudent()) {
            return redirect()->to('estudiante/agenda' . ($this->request->getUri()->getQuery() ? '?' . $this->request->getUri()->getQuery() : ''));
        }

        return $this->render('teacher', $this->layoutData() + ['quota' => service('aiQuota')->balance()], null);
    }

    /** Agenda del estudiante, o la del grupo en la vista de estudiante. */
    public function student()
    {
        $layout = $this->studentLayout();
        $group  = $this->isStudent() ? null : (service('studentPreview')->groupId() ?? 0);

        return $this->render('student', $layout, $group);
    }

    /** Agenda de un estudiante vinculado, para su acudiente (solo lectura). ?estudiante=N elige entre sus estudiantes. */
    public function guardian()
    {
        $kids = service('guardians')->studentsOf((int) service('auth')->id());
        if (! $kids) {
            return redirect()->to('acudiente')->with('toast', ['type' => 'info', 'msg' => t('Agenda.noKids')]);
        }
        $ids = array_map('intval', array_column($kids, 'id'));
        $sel = (int) $this->request->getGet('estudiante');
        $sel = in_array($sel, $ids, true) ? $sel : (in_array((int) session('guardian_kid'), $ids, true) ? (int) session('guardian_kid') : $ids[0]);
        session()->set('guardian_kid', $sel);
        $kid = current(array_filter($kids, static fn ($k) => (int) $k['id'] === $sel));

        return $this->render('student', $this->layoutData(), null, $sel, ['kid' => $kid, 'kids' => $kids]);
    }

    private function render(string $mode, array $layout, ?int $group, ?int $forUser = null, array $extra = [])
    {
        $mes = (string) $this->request->getGet('mes');
        $dia = (string) $this->request->getGet('dia');
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) && strtotime($dia) ? $dia : null;
        $month = preg_match('/^\d{4}-\d{2}$/', $mes) && strtotime($mes . '-01') ? $mes : substr($day ?? date('Y-m-d'), 0, 7);
        $first = $month . '-01';
        $last  = date('Y-m-t', strtotime($first));
        $day ??= substr(date('Y-m-d'), 0, 7) === $month ? date('Y-m-d') : $first;

        // Semanas de lunes a domingo que cubren el mes.
        $gridFrom = date('Y-m-d', strtotime($first . ' -' . (date('N', strtotime($first)) - 1) . ' days'));
        $gridTo   = date('Y-m-d', strtotime($last . ' +' . (7 - date('N', strtotime($last))) . ' days'));
        $events   = service('agenda')->events($gridFrom, max($gridTo, date('Y-m-d', strtotime($day . ' +14 days'))), $mode, $group, $forUser);
        $byDay    = [];
        foreach ($events as $e) {
            $byDay[$e['date']][] = $e;
        }
        $upcoming = array_values(array_filter($events, static fn ($e) => $e['date'] > $day && $e['date'] <= date('Y-m-d', strtotime($day . ' +14 days')) && ! $e['done']));

        $ts = strtotime($first);

        return view('agenda/index', $extra + $layout + [
            'title'    => t('Agenda.title'),
            'mode'     => $mode,
            'base'     => $forUser ? 'acudiente/agenda' : ($mode === 'student' ? 'estudiante/agenda' : 'agenda'),
            'baseQuery' => $forUser ? ['estudiante' => $forUser] : [],
            'readOnly' => $group !== null || $forUser !== null,
            'month'    => $month, 'monthLabel' => ucfirst(date_fmt('monthYear', $ts)),
            'prev'     => date('Y-m', strtotime($first . ' -1 month')), 'next' => date('Y-m', strtotime($first . ' +1 month')),
            'gridFrom' => $gridFrom, 'gridTo' => $gridTo, 'day' => $day, 'byDay' => $byDay, 'upcoming' => array_slice($upcoming, 0, 60),
            'offerings' => $mode === 'teacher' && $this->canTeach() ? service('agenda')->teacherOfferings() : [],
            // Asignatura opcional de un recordatorio personal del estudiante (solo en su propia agenda).
            'canEvents' => $canEvents = $group === null && $forUser === null && service('events')->canManage(),
            // Formulario de eventos dentro de la agenda (la antigua pantalla «Eventos» se unió aquí).
            'eventCategories' => $canEvents ? \App\Libraries\Events::CATEGORIES : [],
            'eventAudiences'  => $canEvents ? \App\Libraries\Events::AUDIENCES : [],
            'newEvent'        => $canEvents && $this->request->getGet('evento') === 'nuevo',
            'tagOfferings' => $mode === 'student' && $group === null && $forUser === null ? service('agenda')->tagOfferings() : [],
        ]);
    }

    /** Vuelve a la agenda en el día de la nota. */
    private function back(?string $date = null, ?array $toast = null)
    {
        $date = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
        $r    = redirect()->to(($this->isStudent() ? 'estudiante/agenda' : 'agenda') . ($date ? '?dia=' . $date : ''));

        return $toast ? $r->with('toast', $toast) : $r;
    }

    public function store()
    {
        // Cualquiera puede guardar recordatorios personales; las notas para un grupo exigen dictar esa asignatura.
        $r =service('agenda')->create($this->request->getPost(), $this->canTeach());
        if (isset($r['errors'])) {
            return redirect()->back()->withInput()->with('errors', $r['errors'])->with('toast', ['type' => 'error', 'msg' => reset($r['errors'])]);
        }

        return $this->back((string) $this->request->getPost('entry_date'), ['type' => 'ok', 'msg' => $this->request->getPost('offering_id')
            ? t('Agenda.notePublished') : t('Agenda.reminderSaved')]);
    }

    public function update(int $id)
    {
        $r = service('agenda')->update($id, $this->request->getPost(), $this->canTeach());
        if (isset($r['errors'])) {
            return redirect()->back()->withInput()->with('toast', ['type' => 'error', 'msg' => reset($r['errors'])]);
        }

        return $this->back((string) $this->request->getPost('entry_date'), ['type' => 'ok', 'msg' => t('Agenda.noteUpdated')]);
    }

    public function delete(int $id)
    {
        $ok = service('agenda')->delete($id);

        return $this->back((string) $this->request->getPost('dia'), $ok ? ['type' => 'ok', 'msg' => t('Agenda.noteDeleted')] : ['type' => 'error', 'msg' => t('Agenda.noteNotYours')]);
    }

    /** Marca o desmarca como hecha (JSON si llega por fetch). */
    public function toggle(int $id)
    {
        $done = service('agenda')->toggleDone($id, $this->isStudent() ? 'student' : 'teacher');
        if ($this->request->isAJAX()) {
            return $this->response->setJSON($done === null ? ['ok' => false, 'error' => 'No puedes marcar esa nota.'] : ['ok' => true, 'done' => $done]);
        }

        return $this->back((string) $this->request->getPost('dia'), $done === null ? ['type' => 'error', 'msg' => t('Agenda.cantMark')] : null);
    }
}
