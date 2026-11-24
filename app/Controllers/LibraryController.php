<?php

namespace App\Controllers;

/**
 * Biblioteca institucional (documento maestro 4.2 y 4.3): bancos de preguntas, plantillas (retos, evaluaciones y páginas)
 * y recursos (archivos y enlaces). Todo con datos reales.
 */
class LibraryController extends BaseController
{
    private function offerings(): array
    {
        return service('academic')->myOfferings();
    }

    public function index()
    {
        $f = ['subject' => (string) $this->request->getGet('asignatura'), 'q' => trim((string) $this->request->getGet('q'))];

        return view('library/index', $this->layoutData() + [
            'title'     => 'Biblioteca',
            'banks'     => service('banks')->list($f),
            'templates' => service('templates')->list($f),
            'resources' => service('resources')->list($f),
            'canTemplate' => service('tenant')->can('content.edit'),
            'shared'    => ($net = service('banks')->networkId()) ? service('network')->sharedBanks($net, (int) service('tenant')->institutionId()) : null,
            'filters'   => $f,
            'subjects'  => service('academic')->subjects(),
            'offerings' => $this->offerings(),
            'canCreate' => service('tenant')->can('activities.create'),
            'tab'       => in_array($this->request->getGet('t'), ['plantillas', 'recursos'], true) ? $this->request->getGet('t') : (service('tenant')->can('activities.create') ? 'bancos' : 'plantillas'),
        ]);
    }

    /* ---------- Plantillas ---------- */

    /** «Guardar como plantilla» desde una página del cuaderno (los retos y evaluaciones lo hacen al guardar). */
    public function templateFromPage()
    {
        $r = service('templates')->fromPage((int) $this->request->getPost('page_id'));

        return isset($r['error']) ? $this->backWith('error', $r['error'])
            : redirect()->to('biblioteca?t=plantillas')->with('toast', ['type' => 'ok', 'msg' => 'Plantilla creada. Agrégale una descripción para que otros docentes sepan cuándo usarla.']);
    }

    public function updateTemplate(int $id)
    {
        $e = service('templates')->update($id, $this->request->getPost());

        return redirect()->to('biblioteca?t=plantillas')->with('toast', $e ? ['type' => 'error', 'msg' => $e] : ['type' => 'ok', 'msg' => 'Plantilla actualizada.']);
    }

    public function deleteTemplate(int $id)
    {
        $e = service('templates')->delete($id);

        return redirect()->to('biblioteca?t=plantillas')->with('toast', $e ? ['type' => 'error', 'msg' => $e] : ['type' => 'ok', 'msg' => 'Plantilla eliminada.']);
    }

    public function useTemplate(int $id)
    {
        $r = service('templates')->use($id, (int) $this->request->getPost('offering_id'), (string) $this->request->getPost('title'));

        return isset($r['error']) ? redirect()->to('biblioteca?t=plantillas')->with('toast', ['type' => 'error', 'msg' => $r['error']])
            : redirect()->to($r['url'])->with('toast', ['type' => 'ok', 'msg' => 'Borrador creado desde la plantilla. Ajústalo y publícalo cuando esté listo.']);
    }

    /* ---------- Recursos ---------- */

    public function storeResource()
    {
        $r = service('resources')->create($this->request->getPost(), $this->request->getFile('file'));
        if (isset($r['errors'])) {
            return redirect()->to('biblioteca?t=recursos')->withInput()->with('errors', $r['errors'])->with('toast', ['type' => 'error', 'msg' => reset($r['errors'])]);
        }

        return redirect()->to('biblioteca?t=recursos')->with('toast', ['type' => 'ok', 'msg' => 'Recurso agregado a la biblioteca.']);
    }

    public function deleteResource(int $id)
    {
        $e = service('resources')->delete($id);

        return redirect()->to('biblioteca?t=recursos')->with('toast', $e ? ['type' => 'error', 'msg' => $e] : ['type' => 'ok', 'msg' => 'Recurso eliminado.']);
    }

    /** Descarga del archivo (solo con sesión en la institución). */
    public function download(int $id)
    {
        $res  = service('resources');
        $r    = $res->get($id);
        $path = $r ? $res->filePath($r) : null;
        if (! $path) {
            return redirect()->to('biblioteca?t=recursos')->with('toast', ['type' => 'error', 'msg' => 'Ese archivo ya no está disponible.']);
        }

        return $this->response->download($path, null)->setFileName((string) $r['file_name'])->setContentType((string) $r['mime'])
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function create()
    {
        $in = $this->postFields(['name', 'subject_id', 'description']);
        $r  = service('banks')->create($in);
        if (isset($r['errors'])) {
            return redirect()->back()->withInput()->with('errors', $r['errors'])->with('toast', ['type' => 'error', 'msg' => reset($r['errors'])]);
        }

        return redirect()->to('biblioteca/bancos/' . $r['id'])->with('toast', ['type' => 'ok', 'msg' => 'Banco creado. Agregue sus preguntas con tema y dificultad.']);
    }

    public function show(int $id)
    {
        $banks = service('banks');
        $bank  = $banks->get($id);
        if (! $bank) {
            return redirect()->to('biblioteca')->with('toast', ['type' => 'error', 'msg' => 'Ese banco no existe.']);
        }
        $quizzes = array_values(array_filter(service('activities')->listForTeacher(), static fn ($a) => $a['kind'] === 'quiz'));

        return view('library/bank', $this->layoutData() + [
            'title'          => $bank['name'],
            'bank'           => $bank,
            'canEdit'        => $banks->canEdit($bank),
            'questions'      => $banks->questions($id),
            'topics'         => $banks->topics($id),
            'subjects'       => service('academic')->subjects(),
            'offerings'      => $this->offerings(),
            'quizzes'        => $quizzes,
            'withDifficulty' => true,
            'aiGen'          => $banks->canEdit($bank) && service('tenant')->can('ai.teacher.use'),
            'quota'          => service('aiQuota')->balance(),
            'inNetwork'      => (bool) $banks->networkId(),
        ]);
    }

    public function save(int $id)
    {
        $errors = service('banks')->save($id, $this->request->getPost());
        if ($errors) {
            if (isset($errors['_'])) {
                return $this->backWith('error', $errors['_']);
            }

            return redirect()->back()->withInput()->with('errors', $errors)->with('toast', ['type' => 'error', 'msg' => reset($errors)]);
        }

        return redirect()->to('biblioteca/bancos/' . $id)->with('toast', ['type' => 'ok', 'msg' => 'Banco guardado.']);
    }

    public function delete(int $id)
    {
        $e = service('banks')->delete($id);

        return $e ? $this->backWith('error', $e)
            : redirect()->to('biblioteca')->with('toast', ['type' => 'ok', 'msg' => 'Banco eliminado. Las evaluaciones que usaron sus preguntas no cambian.']);
    }

    public function import(int $id)
    {
        $r = service('banks')->importFromQuiz($id, (int) $this->request->getPost('activity_id'));
        if (isset($r['error'])) {
            return $this->backWith('error', $r['error']);
        }
        $msg = $r['added'] ? 'Se agregaron ' . plural($r['added'], 'pregunta', 'preguntas') . ' al banco. Revise su tema y dificultad.' : 'Esas preguntas ya estaban en el banco.';

        return redirect()->to('biblioteca/bancos/' . $id)->with('toast', ['type' => $r['added'] ? 'ok' : 'info', 'msg' => $msg]);
    }

    public function random(int $id)
    {
        $r = service('banks')->randomQuiz($id, $this->request->getPost());
        if (isset($r['error'])) {
            return $this->backWith('error', $r['error']);
        }

        return redirect()->to('actividades/' . $r['id'])->with('toast', ['type' => 'ok',
            'msg' => 'Evaluación creada como borrador con ' . plural($r['count'], 'pregunta', 'preguntas') . ' al azar del banco. Revísela y publíquela.']);
    }

    /** JSON para «Agregar desde un banco» del editor de evaluaciones. */
    public function questionsJson(int $id)
    {
        $banks = service('banks');

        return $this->response->setJSON([
            'questions' => $banks->forEditor($id, ['topic' => (string) $this->request->getGet('topic'), 'difficulty' => (string) $this->request->getGet('difficulty')]),
            'topics'    => $banks->get($id) ? $banks->topics($id) : [],
        ]);
    }
    /** Banco que otro colegio de la red comparte (solo lectura). */
    public function shared(int $id)
    {
        $bank = service('banks')->shared($id);
        if (! $bank) {
            return redirect()->to('biblioteca')->with('toast', ['type' => 'error', 'msg' => 'Ese banco no está compartido con su red.']);
        }

        return view('library/shared', $this->layoutData() + ['title' => $bank['name'], 'bank' => $bank]);
    }

    public function copy(int $id)
    {
        $r = service('banks')->copyShared($id);
        if (isset($r['error'])) {
            return $this->backWith('error', $r['error']);
        }

        return redirect()->to('biblioteca/bancos/' . $r['id'])->with('toast', ['type' => 'ok', 'msg' => 'Banco copiado a su institución. Ahora es suyo: puede editarlo.']);
    }

    public function share(int $id)
    {
        $on = (bool) $this->request->getPost('on');
        $e  = service('banks')->setShared($id, $on);

        return $e ? $this->backWith('error', $e) : redirect()->to('biblioteca/bancos/' . $id)->with('toast', ['type' => 'ok',
            'msg' => $on ? 'Banco compartido con la red: los demás colegios pueden verlo y copiarlo.' : 'El banco dejó de compartirse con la red. Las copias ya hechas no cambian.']);
    }
}
